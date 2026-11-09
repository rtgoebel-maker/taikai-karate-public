<?php

declare(strict_types=1);

namespace Wettkampf\Oeffentlich;

use Wettkampf\Domain\Graduierung;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Durchfuehrung\KlassenAnsicht;

/**
 * Lesemodell für den öffentlichen Aushang „Starterlisten & Wettkampfbäume“:
 * alle belegten Gruppen mit Regeln, Tatamis, Bäumen bzw. Starterlisten
 * und eine Personenliste für die Namenssuche. Nur Name und Verein.
 */
final class StarterlistenAnsicht
{
    public function __construct(private readonly Database $db) {}

    /** Stabiler Anker einer Gruppe (#g-…), unabhängig von Code-Änderungen. */
    public static function anker(string $uuid): string
    {
        return 'g-' . substr(sha1($uuid), 0, 12);
    }

    /** @return array<string, mixed> */
    public function laden(int $turnierId): array
    {
        $ansicht = new KlassenAnsicht($this->db);
        $finaleKata = [];
        foreach ($this->db->alle(
            "SELECT r.klasse_id, r.erlaubte_kata FROM klasse_regel r JOIN klasse k ON k.id = r.klasse_id
              WHERE k.turnier_id = ? AND r.ab_runde = 'finale' AND r.erlaubte_kata IS NOT NULL",
            [$turnierId],
        ) as $r) {
            $finaleKata[(int) $r['klasse_id']] = $r['erlaubte_kata'];
        }

        $gruppen = [];
        $personen = [];
        foreach ($this->db->alle(
            "SELECT k.*, (SELECT COUNT(*) FROM start s WHERE s.klasse_id = k.id AND s.status <> 'zurueckgezogen') AS anzahl
               FROM klasse k WHERE k.turnier_id = ? AND k.status <> 'zusammengelegt' ORDER BY k.sortierung, k.code",
            [$turnierId],
        ) as $k) {
            if ((int) $k['anzahl'] === 0) {
                continue;
            }
            $gruppe = [
                'id' => (int) $k['id'],
                'code' => $k['code'],
                'anker' => self::anker($k['uuid']),
                'disziplin' => $k['disziplin'],
                'finale_art' => $k['finale_art'],
                'bezeichnung' => $k['bezeichnung'],
                'regeln' => $this->regeln($k, $finaleKata[(int) $k['id']] ?? null),
                'anzahl' => (int) $k['anzahl'],
                'status' => $k['status'],
                'phasen' => [],
                'starter' => [],
                'tatamis' => [],
            ];
            if ($k['status'] === 'offen') {
                $gruppe['starter'] = $this->starterOffen((int) $k['id'], $ansicht);
            } else {
                $daten = $ansicht->laden((int) $k['id']);
                $gruppe['phasen'] = $daten['phasen'];
                $gruppe['platzierungen'] = $k['status'] === 'bestaetigt' ? $daten['platzierungen'] : [];
                foreach ($daten['phasen'] as $p) {
                    if ($p['tatami_nummer'] !== null) {
                        $gruppe['tatamis'][(int) $p['tatami_nummer']] = $p['tatami'];
                    }
                }
                ksort($gruppe['tatamis']);
            }
            $gruppen[] = $gruppe;

            foreach ($this->db->alle(
                "SELECT s.id FROM start s WHERE s.klasse_id = ? AND s.status <> 'zurueckgezogen'",
                [$k['id']],
            ) as $s) {
                $personen[] = $ansicht->person((int) $s['id']) + [
                    'code' => $k['code'],
                    'anker' => $gruppe['anker'],
                    'tatami' => implode(', ', $gruppe['tatamis']),
                ];
            }
        }
        usort($personen, static fn($a, $b) => strcoll($a['name'], $b['name']));

        $turnier = $this->db->eine('SELECT revision FROM turnier WHERE id = ?', [$turnierId]);
        $stand = $this->db->wert('SELECT MAX(zeit) FROM audit_log WHERE turnier_id = ?', [$turnierId]);
        return [
            'gruppen' => $gruppen,
            'personen' => $personen,
            'starts' => array_sum(array_column($gruppen, 'anzahl')),
            'revision' => (int) $turnier['revision'],
            'stand' => $stand ? (string) $stand : date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Regelzeile, z. B. „männlich · E3: 6.–4. Kyu, 15–17 Jahre; Vorrunde: Heian Shodan, …; Finale: frei“.
     *
     * @param array<string, mixed> $k
     */
    private function regeln(array $k, ?string $finaleKata): string
    {
        $geschlecht = ['w' => 'weiblich', 'm' => 'männlich', 'mixed' => 'gemischt'][$k['geschlecht']] ?? $k['geschlecht'];
        $alter = match (true) {
            $k['alter_min'] === null && $k['alter_max'] === null => 'alle Alter',
            $k['alter_max'] === null => "ab {$k['alter_min']} Jahre",
            $k['alter_min'] === null => "bis {$k['alter_max']} Jahre",
            default => "{$k['alter_min']}–{$k['alter_max']} Jahre",
        };
        $teile = ["$geschlecht · {$k['code']}: "
            . Graduierung::bereichText($k['grad_min'] === null ? null : (int) $k['grad_min'], $k['grad_max'] === null ? null : (int) $k['grad_max'])
            . ", $alter"];
        if (str_starts_with($k['disziplin'], 'kata')) {
            $teile[] = 'Vorrunde: ' . ($k['erlaubte_kata'] ?: 'frei');
            if ($finaleKata !== null) {
                $teile[] = 'Finale: ' . ($finaleKata === '' ? 'frei (Tokui)' : $finaleKata);
            }
        } elseif ($k['kumite_form']) {
            $teile[] = ['kihon_ippon' => 'Kihon-Ippon-Kumite', 'jiyu_ippon' => 'Jiyu-Ippon-Kumite', 'jiyu' => 'Jiyu-Kumite'][$k['kumite_form']] ?? '';
        }
        return implode('; ', $teile);
    }

    /** @return list<array{name: string, verein: string}> */
    private function starterOffen(int $klasseId, KlassenAnsicht $ansicht): array
    {
        $liste = array_map(
            static fn($id) => $ansicht->person((int) $id),
            $this->db->spalte("SELECT id FROM start WHERE klasse_id = ? AND status <> 'zurueckgezogen'", [$klasseId]),
        );
        usort($liste, static fn($a, $b) => [$a['verein'], $a['name']] <=> [$b['verein'], $b['name']]);
        return $liste;
    }
}
