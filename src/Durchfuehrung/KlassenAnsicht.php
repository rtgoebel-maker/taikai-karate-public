<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung;

use Wettkampf\Domain\Platzierung\Platzierung;
use Wettkampf\Domain\Turnierbaum\KoBaum;
use Wettkampf\Domain\Wertung\Wertung;
use Wettkampf\Kern\Db\Database;

/**
 * Lesemodell einer Klasse für Anzeige, Druck und Statusseite:
 * Phasen mit Begegnungen (inkl. Namen/Vereine), Auftritten, Tabellen
 * und Platzierungen. Enthält bewusst keine Geburtsdaten.
 */
final class KlassenAnsicht
{
    /** @var array<int, array{name: string, verein: string, verein_voll: string}> */
    private array $namen = [];
    /** @var array<int, int> Begegnungs-ID → fortlaufende Kampfnummer der Klasse */
    private array $nummern = [];

    public function __construct(private readonly Database $db) {}

    /** @return array<string, mixed> */
    public function laden(int $klasseId): array
    {
        $klasse = $this->db->eine(
            'SELECT k.*, t.name AS turnier_name, t.datum AS turnier_datum FROM klasse k JOIN turnier t ON t.id = k.turnier_id WHERE k.id = ?',
            [$klasseId],
        );
        if ($klasse === null) {
            throw new \Wettkampf\Kern\Http\NichtGefunden('Klasse nicht gefunden.');
        }
        $this->namenLaden($klasseId);
        $this->nummern = self::kampfNummern($this->db, $klasseId);

        $phasen = [];
        foreach ($this->db->alle(
            'SELECT p.*, ta.bezeichnung AS tatami, ta.nummer AS tatami_nummer FROM phase p LEFT JOIN tatami ta ON ta.id = p.tatami_id WHERE p.klasse_id = ? ORDER BY p.id',
            [$klasseId],
        ) as $p) {
            $p['begegnungen'] = array_map(fn($b) => $this->begegnung($b, $p), $this->db->alle(
                'SELECT * FROM begegnung WHERE phase_id = ? ORDER BY runde, position',
                [$p['id']],
            ));
            $p['auftritte'] = array_map(fn($a) => $a + $this->person((int) $a['start_id']) + [
                'wertung_daten' => json_decode((string) $a['wertung'], true) ?: [],
            ], $this->db->alle('SELECT * FROM auftritt WHERE phase_id = ? ORDER BY reihenfolge', [$p['id']]));
            $p['runden'] = [];
            foreach ($p['begegnungen'] as $b) {
                $p['runden'][(int) $b['runde']][] = $b;
            }
            $p['art'] = $p['auftritte'] !== [] ? 'auftritte' : (($p['begegnungen'][0]['art'] ?? '') === 'jgj' ? 'jgj' : 'ko');
            if ($p['art'] === 'jgj') {
                $p['tabelle'] = $this->jgjTabelle($p);
            }
            if ($p['art'] === 'auftritte') {
                $p['rangliste'] = $this->rangliste($p['auftritte']);
            }
            $p['starter_liste'] = array_map(fn($id) => ['start_id' => (int) $id] + $this->person((int) $id), json_decode((string) $p['starter'], true) ?: []);
            $phasen[] = $p;
        }

        $platzierungen = array_map(
            fn($pl) => $pl + $this->person((int) $pl['start_id']),
            $this->db->alle('SELECT * FROM platzierung WHERE klasse_id = ? ORDER BY platz', [$klasseId]),
        );
        $auslosung = $this->db->eine('SELECT * FROM auslosung WHERE klasse_id = ? AND aktiv = 1', [$klasseId]);

        return ['klasse' => $klasse, 'phasen' => $phasen, 'platzierungen' => $platzierungen, 'auslosung' => $auslosung];
    }

    /**
     * Name und Verein eines Starts. 'verein' ist der Kurzname (falls gepflegt) – für Turnierbaum, Tablet, Ausruf und Statusseite,
     * wo der Platz knapp ist; 'verein_voll' der volle Name (Urkunden).
     *
     * @return array{name: string, verein: string, verein_voll: string}
     */
    public function person(?int $startId): array
    {
        if ($startId === null) {
            return ['name' => '', 'verein' => '', 'verein_voll' => ''];
        }
        if (!isset($this->namen[$startId])) {
            $this->namenLaden(null, $startId);
        }
        return $this->namen[$startId] ?? ['name' => '?', 'verein' => '', 'verein_voll' => ''];
    }

    /**
     * @param array<string, mixed> $b
     * @param array<string, mixed> $phase
     * @return array<string, mixed>
     */
    private function begegnung(array $b, array $phase): array
    {
        $aka = $this->person($b['aka_start_id'] === null ? null : (int) $b['aka_start_id']);
        $shiro = $this->person($b['shiro_start_id'] === null ? null : (int) $b['shiro_start_id']);
        $b['aka_name'] = $aka['name'];
        $b['aka_verein'] = $aka['verein'];
        $b['shiro_name'] = $shiro['name'];
        $b['shiro_verein'] = $shiro['verein'];
        $b['nr'] = $this->nummern[(int) $b['id']] ?? null;
        // Spalte heißt „wertungssystem“ (SYSTEM ist in MySQL 8 reserviert)
        $b['system'] = $b['wertungssystem'];
        $b['aka_platzhalter'] = $this->platzhalter($b['aka_quelle']);
        $b['shiro_platzhalter'] = $this->platzhalter($b['shiro_quelle']);
        $b['wertung_daten'] = json_decode((string) $b['wertung'], true) ?: [];
        $b['runden_name'] = $b['art'] === 'jgj'
            ? 'Runde ' . $b['runde']
            : KoBaum::rundenName((int) ($phase['baum_groesse'] ?: 2), (int) $b['runde'], $b['art']);
        if ($phase['typ'] === 'pool' && $b['art'] === 'ko') {
            $b['runden_name'] = 'Pool-Runde ' . $b['runde'];
        }
        return $b;
    }

    private function platzhalter(?string $quelle): string
    {
        if ($quelle === null) {
            return '';
        }
        if ($quelle === 'F') {
            return 'Freilos';
        }
        [$typ, $id] = explode(':', $quelle) + [1 => ''];
        $nr = $this->nummern[(int) $id] ?? '?';
        return match ($typ) {
            'W' => 'Sieger Kampf ' . $nr,
            'L' => 'Verlierer Kampf ' . $nr,
            default => '',
        };
    }

    /**
     * Fortlaufende Kampfnummern einer Klasse (Freilose zählen nicht).
     *
     * @return array<int, int>
     */
    public static function kampfNummern(Database $db, int $klasseId): array
    {
        $nummern = [];
        $nr = 0;
        foreach ($db->spalte(
            "SELECT b.id FROM begegnung b JOIN phase p ON p.id = b.phase_id
              WHERE p.klasse_id = ? AND b.status <> 'freilos' ORDER BY p.id, b.runde, b.position",
            [$klasseId],
        ) as $id) {
            $nummern[(int) $id] = ++$nr;
        }
        return $nummern;
    }

    /**
     * @param array<string, mixed> $phase
     * @return list<array<string, mixed>>
     */
    private function jgjTabelle(array $phase): array
    {
        $startIds = array_map('intval', json_decode((string) $phase['starter'], true) ?: []);
        $daten = [];
        foreach ($phase['begegnungen'] as $b) {
            if ($b['status'] !== 'beendet') {
                continue;
            }
            $w = $b['wertung_daten'];
            $daten[] = [
                'aka' => (int) $b['aka_start_id'], 'shiro' => (int) $b['shiro_start_id'], 'sieger' => (int) $b['sieger_start_id'],
                'aka_ippon' => (int) ($w['aka']['ippon'] ?? 0), 'aka_wazaari' => (int) ($w['aka']['wazaari'] ?? 0),
                'shiro_ippon' => (int) ($w['shiro']['ippon'] ?? 0), 'shiro_wazaari' => (int) ($w['shiro']['wazaari'] ?? 0),
                'aka_flaggen' => (int) ($w['flaggen']['aka'] ?? 0), 'shiro_flaggen' => (int) ($w['flaggen']['shiro'] ?? 0),
            ];
        }
        $stich = [];
        foreach ($startIds ? $this->db->alle('SELECT id, stichentscheid FROM start WHERE id IN (' . implode(',', $startIds) . ')') : [] as $s) {
            if ($s['stichentscheid'] !== null) {
                $stich[(int) $s['id']] = (int) $s['stichentscheid'];
            }
        }
        $r = Platzierung::ausJederGegenJeden($startIds, $daten, $stich);
        $gleich = array_merge(...($r['gleichstand'] ?: [[]]));
        return array_map(fn($z) => $z + $this->person($z['start_id']) + ['gleichstand' => in_array($z['start_id'], $gleich, true)], $r['tabelle']);
    }

    /**
     * @param list<array<string, mixed>> $auftritte
     * @return list<array<string, mixed>>
     */
    private function rangliste(array $auftritte): array
    {
        $fertig = array_values(array_filter($auftritte, static fn($a) => $a['status'] === 'beendet'));
        $r = Platzierung::ausPunkten(array_map(static fn($a) => [
            'start_id' => (int) $a['start_id'],
            'gesamt' => (float) $a['gesamt'],
            'mit_niedrigster' => Wertung::mitNiedrigster($a['wertung'], (float) $a['gesamt']),
            'nebenwertung' => (float) $a['nebenwertung'],
            'stichentscheid' => $a['stichentscheid'] === null ? null : (int) $a['stichentscheid'],
            'auftritt_id' => (int) $a['id'],
        ], $fertig));
        $gleich = array_merge(...($r['gleichstand'] ?: [[]]));
        return array_map(fn($z) => $z + $this->person($z['start_id']) + ['gleichstand' => in_array($z['start_id'], $gleich, true)], $r['tabelle']);
    }

    private function namenLaden(?int $klasseId, ?int $startId = null): void
    {
        $sql = "SELECT s.id, a.vorname, a.nachname, tm.name AS team, COALESCE(v.name, vt.name) AS verein_voll,
                       CASE WHEN v.id IS NOT NULL THEN COALESCE(NULLIF(v.kurzname, ''), v.name) ELSE COALESCE(NULLIF(vt.kurzname, ''), vt.name) END AS verein
                  FROM start s
                  LEFT JOIN teilnahme t ON t.id = s.teilnahme_id LEFT JOIN athlet a ON a.id = t.athlet_id
                  LEFT JOIN verein v ON v.id = a.verein_id LEFT JOIN team tm ON tm.id = s.team_id
                  LEFT JOIN verein vt ON vt.id = tm.verein_id
                 WHERE " . ($klasseId !== null ? 's.klasse_id = ?' : 's.id = ?');
        foreach ($this->db->alle($sql, [$klasseId ?? $startId]) as $s) {
            $this->namen[(int) $s['id']] = [
                'name' => $s['team'] ?? trim("{$s['vorname']} {$s['nachname']}"),
                'verein' => (string) $s['verein'],
                'verein_voll' => (string) $s['verein_voll'],
            ];
        }
    }
}
