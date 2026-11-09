<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;

/**
 * Tatami-Plan: Phasen (Pool, Finalrunde, K.-o.-Runde …) werden Tatamis
 * zugeordnet und dort in eine Reihenfolge gebracht. Klassen mit
 * „gleiche_flaeche“ bleiben bis zum Finale komplett auf einer Tatami – außer Kata-Klassen mit
 * mehreren Punkte-Pools, deren Pools laufen getrennt (siehe GLEICHE_FLAECHE).
 * Startzeiten werden aus der geschätzten Kampfdauer hochgerechnet.
 *
 * Verteilung „dynamisch“ (Standard): Jede Tatami hat nur ihre aktuelle Gruppe; die
 * übrigen warten in der Warteschlange (nicht zugeordnet) und gehen an die nächste
 * Tatami, die frei wird (siehe nachziehen). „fest“: alles wird vorab verteilt.
 * Gesperrte Tatamis bekommen keine neuen Gruppen.
 */
final class PlanService
{
    /** Standard-Minuten je Begegnung bzw. Vortrag (inkl. Wechsel) */
    public const STANDARD_MINUTEN = ['kata' => 2.5, 'kumite' => 3.5, 'kata_team' => 4.0, 'kumite_team' => 12.0];
    /**
     * „Bleibt auf derselben Tatami“ wirkt bei Kata (Punkte) nur mit einem Vorrunden-Block: Hat die
     * Klasse wegen vieler Teilnehmer mehrere Pools (automatisch, ab 9 Startern), laufen diese immer
     * getrennt (parallel) auf verschiedenen Tatamis; das Finale folgt danach. Kumite-Pools legt die
     * Leitung selbst fest – dort gilt die Einstellung unverändert.
     */
    private const GLEICHE_FLAECHE = "(CASE WHEN k.gleiche_flaeche = 1 AND (SELECT COUNT(*) FROM phase v WHERE v.klasse_id = k.id AND v.typ = 'punkte_vorrunde') <= 1 THEN 1 ELSE 0 END) AS gleiche_flaeche";

    /**
     * Reihenfolge der Warteschlange: zuerst Kata, dann Kumite; innerhalb davon die jüngeren und
     * niedrigeren Klassen zuerst, dann die Klassenreihenfolge. Von Hand Sortiertes (reihenfolge > 0)
     * steht davor.
     */
    private const NATUERLICH = "CASE WHEN k.disziplin LIKE 'kata%' THEN 0 ELSE 1 END, COALESCE(k.alter_min, 0), COALESCE(k.grad_min, 0), k.sortierung, k.code, p.id";
    private const REIHENFOLGE = 'CASE WHEN p.reihenfolge > 0 THEN 0 ELSE 1 END, p.reihenfolge, ' . self::NATUERLICH;

    public const MODI = ['dynamisch' => 'dynamisch – nächste Gruppe an die Tatami, die frei wird', 'fest' => 'fester Plan – alle Gruppen vorab verteilen'];

    private readonly Protokoll $protokoll;

    public function __construct(private readonly Database $db)
    {
        $this->protokoll = new Protokoll($db);
    }

    /**
     * Ordnet eine Phase einer Tatami zu (null = nicht zugeordnet) und setzt sie
     * an die gewünschte Position (0-basiert; null = ans Ende).
     */
    public function verschieben(int $turnierId, int $phaseId, ?int $tatamiId, ?int $position = null): void
    {
        $this->db->transaktion(function () use ($turnierId, $phaseId, $tatamiId, $position) {
            $phase = $this->phase($turnierId, $phaseId);
            if ($tatamiId !== null && $this->db->wert('SELECT id FROM tatami WHERE id = ? AND turnier_id = ?', [$tatamiId, $turnierId]) === null) {
                throw new FachFehler('Tatami nicht gefunden.');
            }
            // „Bis zum Finale auf derselben Fläche“: alle Phasen der Klasse wandern mit
            $phasen = (int) $phase['gleiche_flaeche'] === 1
                ? array_map('intval', $this->db->spalte('SELECT id FROM phase WHERE klasse_id = ? ORDER BY id', [$phase['klasse_id']]))
                : [$phaseId];

            if ($tatamiId === null && $position === null) {
                // zurück in die Warteschlange: an den Platz laut Klassenreihenfolge, nicht ans Ende
                $this->inSchlangeEinordnen($turnierId, $phasen);
                $this->protokoll->eintragen($turnierId, 'phase', $phaseId, 'geplant', ['tatami_id' => null, 'position' => 'einsortiert']);
                return;
            }
            $liste = array_map('intval', $this->db->spalte(
                'SELECT p.id FROM phase p JOIN klasse k ON k.id = p.klasse_id WHERE ' . ($tatamiId === null ? 'p.tatami_id IS NULL' : 'p.tatami_id = ?')
                . ' AND k.turnier_id = ? ORDER BY ' . self::REIHENFOLGE,
                $tatamiId === null ? [$turnierId] : [$tatamiId, $turnierId],
            ));
            $liste = array_values(array_diff($liste, $phasen));
            $position = $position === null ? count($liste) : max(0, min($position, count($liste)));
            array_splice($liste, $position, 0, $phasen);

            foreach ($liste as $i => $id) {
                $this->db->update('phase', ['tatami_id' => $tatamiId, 'reihenfolge' => ($i + 1) * 10], ['id' => $id]);
            }
            $this->protokoll->eintragen($turnierId, 'phase', $phaseId, 'geplant', ['tatami_id' => $tatamiId, 'position' => $position]);
        });
    }

    /**
     * Verteilt alle noch nicht zugeordneten Phasen auf die Tatamis: Klassen in
     * Sortierreihenfolge, jeweils auf die Tatami mit der geringsten Auslastung.
     * Pools einer Klasse werden parallel auf verschiedene Tatamis gelegt
     * (außer bei „gleiche Fläche“), die Finalrunde folgt auf der Tatami des ersten Pools.
     *
     * Mit $kataKumiteGetrennt kommen Kata-Gruppen (Einzel/Team) auf die erste
     * Hälfte der Tatamis und Kumite-Gruppen auf die zweite – so wechselt eine
     * Tatami nicht ständig zwischen Punktwertung und Flaggen bzw. Kumite.
     *
     * @return int Anzahl verteilter Phasen
     */
    public function automatischVerteilen(int $turnierId, bool $kataKumiteGetrennt = false): int
    {
        return $this->db->transaktion(function () use ($turnierId, $kataKumiteGetrennt) {
            $tatamis = array_map('intval', $this->db->spalte('SELECT id FROM tatami WHERE turnier_id = ? AND gesperrt_am IS NULL ORDER BY nummer', [$turnierId]));
            if ($tatamis === []) {
                throw new FachFehler('Das Turnier hat keine (freie) Tatami.');
            }
            $minuten = $this->minutenJeEinheit($turnierId);
            $last = array_fill_keys($tatamis, 0.0);
            $reihenfolge = array_fill_keys($tatamis, 0);
            foreach ($this->db->alle(
                'SELECT p.tatami_id, MAX(p.reihenfolge) AS r FROM phase p JOIN klasse k ON k.id = p.klasse_id
                  WHERE k.turnier_id = ? AND p.tatami_id IS NOT NULL GROUP BY p.tatami_id',
                [$turnierId],
            ) as $z) {
                $reihenfolge[(int) $z['tatami_id']] = (int) $z['r'];
            }
            foreach ($this->plan($turnierId) as $tatamiId => $eintraege) {
                if ($tatamiId !== 0) {
                    $last[$tatamiId] = array_sum(array_column($eintraege, 'dauer'));
                }
            }

            $offen = $this->db->alle(
                "SELECT p.*, k.disziplin, " . self::GLEICHE_FLAECHE . ", k.sortierung FROM phase p JOIN klasse k ON k.id = p.klasse_id
                  WHERE k.turnier_id = ? AND p.tatami_id IS NULL ORDER BY " . self::REIHENFOLGE,
                [$turnierId],
            );
            $jeKlasse = [];
            foreach ($offen as $p) {
                $jeKlasse[(int) $p['klasse_id']][] = $p;
            }
            // Kata-Tatamis: erste Hälfte (aufgerundet), Kumite-Tatamis: Rest
            $kataTatamis = array_slice($tatamis, 0, (int) ceil(count($tatamis) / 2));
            $kumiteTatamis = array_slice($tatamis, count($kataTatamis)) ?: $kataTatamis;

            $anzahl = 0;
            foreach ($jeKlasse as $phasen) {
                $ersteTatami = null;
                $erlaubt = !$kataKumiteGetrennt || count($tatamis) < 2
                    ? $tatamis
                    : (str_starts_with($phasen[0]['disziplin'], 'kata') ? $kataTatamis : $kumiteTatamis);
                foreach ($phasen as $p) {
                    $dauer = $this->dauer($p, $minuten[$p['disziplin']]);
                    if ($ersteTatami !== null && ((int) $p['gleiche_flaeche'] === 1 || in_array($p['typ'], ['finalrunde', 'punkte_finale'], true))) {
                        $ziel = $ersteTatami;
                    } else {
                        $kandidaten = array_intersect_key($last, array_flip($erlaubt));
                        asort($kandidaten);
                        $ziel = (int) array_key_first($kandidaten);
                    }
                    $ersteTatami ??= $ziel;
                    $reihenfolge[$ziel] += 10;
                    $this->db->update('phase', ['tatami_id' => $ziel, 'reihenfolge' => $reihenfolge[$ziel]], ['id' => $p['id']]);
                    $last[$ziel] += $dauer;
                    $anzahl++;
                }
            }
            $this->protokoll->eintragen($turnierId, 'plan', null, 'automatisch', ['phasen' => $anzahl, 'kata_kumite_getrennt' => $kataKumiteGetrennt]);
            return $anzahl;
        });
    }

    /** @return array{modus: string, getrennt: bool} Verteilung der Gruppen auf die Tatamis */
    public function verteilung(int $turnierId): array
    {
        $e = json_decode((string) $this->db->wert('SELECT einstellungen FROM turnier WHERE id = ?', [$turnierId]), true) ?: [];
        $v = is_array($e['verteilung'] ?? null) ? $e['verteilung'] : [];
        return ['modus' => ($v['modus'] ?? 'dynamisch') === 'fest' ? 'fest' : 'dynamisch', 'getrennt' => (bool) ($v['getrennt'] ?? false)];
    }

    public function verteilungSpeichern(int $turnierId, string $modus, bool $getrennt): void
    {
        if (!isset(self::MODI[$modus])) {
            throw new FachFehler('Unbekannte Verteilung.');
        }
        $e = json_decode((string) $this->db->wert('SELECT einstellungen FROM turnier WHERE id = ?', [$turnierId]), true) ?: [];
        $e['verteilung'] = ['modus' => $modus, 'getrennt' => $getrennt];
        $this->db->update('turnier', ['einstellungen' => json_encode($e, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)], ['id' => $turnierId]);
        $this->protokoll->eintragen($turnierId, 'plan', null, 'verteilung', ['modus' => $modus, 'getrennt' => $getrennt]);
        $this->protokoll->revisionErhoehen($turnierId);
        $this->nachziehen($turnierId);
    }

    /**
     * Dynamische Verteilung: Jede freie, nicht gesperrte Tatami (keine unbeendete Phase
     * zugeordnet) bekommt die nächste bereite Gruppe aus der Warteschlange. Klassen mit
     * „gleiche Fläche“ wandern komplett, wartende Finalrunden erst, wenn sie bereit sind.
     * Bei „Kata/Kumite getrennt“ bevorzugt eine Tatami ihre Art, nimmt aber lieber die
     * andere als leer zu stehen. Im festen Plan passiert nichts.
     *
     * @return int Anzahl neu zugeteilter Phasen
     */
    public function nachziehen(int $turnierId): int
    {
        $verteilung = $this->verteilung($turnierId);
        if ($verteilung['modus'] !== 'dynamisch') {
            return 0;
        }
        return $this->db->transaktion(function () use ($turnierId, $verteilung) {
            $alle = array_map('intval', $this->db->spalte('SELECT id FROM tatami WHERE turnier_id = ? ORDER BY nummer', [$turnierId]));
            $offenAuf = array_map('intval', $this->db->spalte(
                "SELECT DISTINCT p.tatami_id FROM phase p JOIN klasse k ON k.id = p.klasse_id
                  WHERE k.turnier_id = ? AND p.tatami_id IS NOT NULL AND p.status <> 'beendet'",
                [$turnierId],
            ));
            $gesperrt = array_map('intval', $this->db->spalte('SELECT id FROM tatami WHERE turnier_id = ? AND gesperrt_am IS NOT NULL', [$turnierId]));
            $frei = array_values(array_diff($alle, $offenAuf, $gesperrt));
            if ($frei === []) {
                return 0;
            }
            // Warteschlange: von Hand sortierte zuerst, dann Kata vor Kumite, jüngere/niedrigere Klassen zuerst
            $schlange = $this->db->alle(
                "SELECT p.id, p.klasse_id, p.status, k.disziplin, " . self::GLEICHE_FLAECHE . " FROM phase p JOIN klasse k ON k.id = p.klasse_id
                  WHERE k.turnier_id = ? AND p.tatami_id IS NULL AND p.status <> 'beendet' AND k.status NOT IN ('offen', 'zusammengelegt')
                  ORDER BY " . self::REIHENFOLGE,
                [$turnierId],
            );
            $einheiten = [];
            foreach ($schlange as $p) {
                $schluessel = (int) $p['gleiche_flaeche'] === 1 ? 'k' . $p['klasse_id'] : 'p' . $p['id'];
                $einheiten[$schluessel] ??= ['phasen' => [], 'bereit' => $p['status'] !== 'wartet', 'kata' => str_starts_with($p['disziplin'], 'kata')];
                $einheiten[$schluessel]['phasen'][] = (int) $p['id'];
            }
            $einheiten = array_values(array_filter($einheiten, static fn($e) => $e['bereit']));
            $kataTatamis = array_slice($alle, 0, (int) ceil(count($alle) / 2));

            $anzahl = 0;
            $zuteilung = [];
            foreach ($frei as $tatami) {
                if ($einheiten === []) {
                    break;
                }
                $index = 0;
                if ($verteilung['getrennt'] && count($alle) > 1) {
                    $kata = in_array($tatami, $kataTatamis, true);
                    foreach ($einheiten as $i => $e) {
                        if ($e['kata'] === $kata) {
                            $index = $i;
                            break;
                        }
                    }
                }
                $einheit = array_splice($einheiten, $index, 1)[0];
                $r = (int) $this->db->wert('SELECT MAX(reihenfolge) FROM phase WHERE tatami_id = ?', [$tatami]);
                foreach ($einheit['phasen'] as $phaseId) {
                    $r += 10;
                    $this->db->update('phase', ['tatami_id' => $tatami, 'reihenfolge' => $r], ['id' => $phaseId]);
                    $zuteilung[] = ['phase' => $phaseId, 'tatami' => $tatami];
                    $anzahl++;
                }
            }
            if ($anzahl > 0) {
                $this->protokoll->eintragen($turnierId, 'plan', null, 'nachgezogen', ['zuteilung' => $zuteilung]);
                $this->protokoll->revisionErhoehen($turnierId);
            }
            return $anzahl;
        });
    }

    /**
     * Tatami sperren (keine neuen Gruppen) bzw. freigeben. Eine laufende Gruppe darf
     * zu Ende laufen; noch nicht begonnene Gruppen dieser Tatami gehen zurück in die
     * Warteschlange und werden neu verteilt.
     *
     * @return int Anzahl zurückgestellter Phasen
     */
    public function tatamiSperren(int $turnierId, int $tatamiId, bool $sperren, string $grund = ''): int
    {
        if ($this->db->wert('SELECT id FROM tatami WHERE id = ? AND turnier_id = ?', [$tatamiId, $turnierId]) === null) {
            throw new FachFehler('Tatami nicht gefunden.');
        }
        $zurueck = $this->db->transaktion(function () use ($turnierId, $tatamiId, $sperren, $grund) {
            $this->db->update('tatami', [
                'gesperrt_am' => $sperren ? date('Y-m-d H:i:s') : null,
                'sperr_grund' => $sperren ? (mb_substr(trim($grund), 0, 200) ?: null) : null,
            ], ['id' => $tatamiId]);
            $zurueck = $sperren ? $this->nichtBegonneneZurueckstellen($tatamiId) : 0;
            $this->protokoll->eintragen($turnierId, 'tatami', $tatamiId, $sperren ? 'gesperrt' : 'entsperrt', ['grund' => $grund, 'zurueckgestellt' => $zurueck]);
            $this->protokoll->revisionErhoehen($turnierId);
            return $zurueck;
        });
        if ($this->verteilung($turnierId)['modus'] === 'dynamisch') {
            $this->nachziehen($turnierId);
        } elseif ($zurueck > 0) {
            $this->automatischVerteilen($turnierId, $this->verteilung($turnierId)['getrennt']);
        }
        return $zurueck;
    }

    /** Warteschlange wieder nach Klassenreihenfolge ordnen (von Hand Sortiertes verwerfen). */
    public function schlangeSortieren(int $turnierId): void
    {
        $this->db->transaktion(function () use ($turnierId) {
            $this->db->query(
                'UPDATE phase SET reihenfolge = 0 WHERE tatami_id IS NULL AND klasse_id IN (SELECT id FROM klasse WHERE turnier_id = ?)',
                [$turnierId],
            );
            $this->protokoll->eintragen($turnierId, 'plan', null, 'schlange_sortiert');
            $this->protokoll->revisionErhoehen($turnierId);
        });
    }

    /**
     * Alle noch nicht begonnenen Gruppen zurück in die Warteschlange (z. B. beim Wechsel
     * auf die dynamische Verteilung während des Turniers).
     *
     * @return int Anzahl zurückgestellter Phasen
     */
    public function nichtBegonneneZurueck(int $turnierId): int
    {
        $anzahl = $this->db->transaktion(function () use ($turnierId) {
            $anzahl = 0;
            foreach ($this->db->spalte('SELECT id FROM tatami WHERE turnier_id = ?', [$turnierId]) as $t) {
                $anzahl += $this->nichtBegonneneZurueckstellen((int) $t);
            }
            $this->protokoll->eintragen($turnierId, 'plan', null, 'zurueckgestellt', ['phasen' => $anzahl]);
            $this->protokoll->revisionErhoehen($turnierId);
            return $anzahl;
        });
        $this->nachziehen($turnierId);
        return $anzahl;
    }

    /**
     * Phasen einer Tatami ohne laufenden oder entschiedenen Kampf/Vortrag zurück in die
     * Warteschlange – außer die Klasse hat dort schon begonnen und bleibt auf derselben Fläche.
     */
    private function nichtBegonneneZurueckstellen(int $tatamiId): int
    {
        $zurueck = [];
        foreach ($this->db->alle(
            "SELECT p.id, p.klasse_id, " . self::GLEICHE_FLAECHE . " FROM phase p JOIN klasse k ON k.id = p.klasse_id
              WHERE p.tatami_id = ? AND p.status <> 'beendet'
                AND NOT EXISTS (SELECT 1 FROM begegnung b WHERE b.phase_id = p.id AND b.status IN ('laeuft', 'beendet'))
                AND NOT EXISTS (SELECT 1 FROM auftritt a WHERE a.phase_id = p.id AND a.status = 'beendet')",
            [$tatamiId],
        ) as $p) {
            $begonnen = (int) $p['gleiche_flaeche'] === 1 && (int) $this->db->wert(
                "SELECT COUNT(*) FROM phase p2 WHERE p2.klasse_id = ? AND p2.tatami_id = ? AND (p2.status = 'beendet'
                    OR EXISTS (SELECT 1 FROM begegnung b WHERE b.phase_id = p2.id AND b.status IN ('laeuft', 'beendet'))
                    OR EXISTS (SELECT 1 FROM auftritt a WHERE a.phase_id = p2.id AND a.status = 'beendet'))",
                [$p['klasse_id'], $tatamiId],
            ) > 0;
            if (!$begonnen) {
                $zurueck[] = (int) $p['id'];
            }
        }
        if ($zurueck !== []) {
            $this->inSchlangeEinordnen((int) $this->db->wert('SELECT turnier_id FROM tatami WHERE id = ?', [$tatamiId]), $zurueck);
        }
        return count($zurueck);
    }

    /**
     * Phasen in die Warteschlange zurückstellen – jeweils vor die nächste Gruppe, die laut
     * Klassenreihenfolge (Kata vor Kumite, jüngere/niedrigere zuerst) nach ihr kommt. Eine von
     * Hand geänderte Reihenfolge der übrigen Warteschlange bleibt erhalten.
     *
     * @param list<int> $phasen
     */
    private function inSchlangeEinordnen(int $turnierId, array $phasen): void
    {
        $neu = array_flip($phasen);
        foreach ($phasen as $id) {
            $this->db->update('phase', ['tatami_id' => null], ['id' => $id]);
        }
        $sql = 'SELECT p.id FROM phase p JOIN klasse k ON k.id = p.klasse_id WHERE k.turnier_id = ? AND p.tatami_id IS NULL ORDER BY ';
        $natuerlich = array_map('intval', $this->db->spalte($sql . self::NATUERLICH, [$turnierId]));
        $bisher = array_values(array_filter(
            array_map('intval', $this->db->spalte($sql . self::REIHENFOLGE, [$turnierId])),
            static fn(int $id) => !isset($neu[$id]),
        ));
        // je neue Phase: die nächste bisherige, die laut Klassenreihenfolge folgt (0 = keine → ans Ende)
        $vor = [0 => []];
        $folgend = 0;
        foreach (array_reverse($natuerlich) as $id) {
            if (isset($neu[$id])) {
                array_unshift($vor[$folgend], $id);
            } else {
                $folgend = $id;
                $vor[$id] ??= [];
            }
        }
        $liste = [];
        foreach ($bisher as $id) {
            array_push($liste, ...($vor[$id] ?? []), ...[$id]);
        }
        array_push($liste, ...($vor[0] ?? []));
        foreach ($liste as $i => $id) {
            $this->db->update('phase', ['reihenfolge' => ($i + 1) * 10], ['id' => $id]);
        }
    }


    /**
     * Hebt die Zuordnung aller noch nicht begonnenen Gruppen auf. Laufende und beendete
     * bleiben auf der Tatami, auf der sie ausgetragen werden bzw. wurden.
     *
     * @return int Anzahl zurückgestellter Phasen
     */
    public function leeren(int $turnierId): int
    {
        return $this->db->transaktion(function () use ($turnierId) {
            $anzahl = 0;
            foreach ($this->db->spalte('SELECT id FROM tatami WHERE turnier_id = ?', [$turnierId]) as $t) {
                $anzahl += $this->nichtBegonneneZurueckstellen((int) $t);
            }
            $this->protokoll->eintragen($turnierId, 'plan', null, 'geleert', ['phasen' => $anzahl]);
            $this->protokoll->revisionErhoehen($turnierId);
            return $anzahl;
        });
    }

    /**
     * Plan je Tatami (Schlüssel 0 = nicht zugeordnet) mit geschätzter Startzeit.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function plan(int $turnierId): array
    {
        $turnier = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$turnierId]);
        $einstellungen = json_decode((string) $turnier['einstellungen'], true) ?: [];
        $beginn = $this->minutenAusUhrzeit($einstellungen['beginn'] ?? '10:00');
        $minuten = $this->minutenJeEinheit($turnierId);

        $plan = [0 => []];
        foreach ($this->db->spalte('SELECT id FROM tatami WHERE turnier_id = ? ORDER BY nummer', [$turnierId]) as $id) {
            $plan[(int) $id] = [];
        }
        foreach ($this->db->alle(
            "SELECT p.*, k.code, k.bezeichnung AS klasse_name, k.disziplin, k.geschlecht, " . self::GLEICHE_FLAECHE . ", k.status AS klasse_status,
                    (SELECT COUNT(*) FROM begegnung b WHERE b.phase_id = p.id AND b.status <> 'freilos') AS kaempfe,
                    (SELECT COUNT(*) FROM begegnung b WHERE b.phase_id = p.id AND b.status = 'beendet') AS kaempfe_beendet,
                    (SELECT COUNT(*) FROM auftritt a WHERE a.phase_id = p.id) AS auftritte,
                    (SELECT COUNT(*) FROM auftritt a WHERE a.phase_id = p.id AND a.status = 'beendet') AS auftritte_beendet,
                    (SELECT COUNT(*) FROM start s WHERE s.klasse_id = k.id AND s.status <> 'zurueckgezogen') AS starter
               FROM phase p JOIN klasse k ON k.id = p.klasse_id
              WHERE k.turnier_id = ?
              ORDER BY " . self::REIHENFOLGE,
            [$turnierId],
        ) as $p) {
            $p['dauer'] = $this->dauer($p, $minuten[$p['disziplin']]);
            $plan[(int) ($p['tatami_id'] ?? 0)][] = $p;
        }
        foreach ($plan as $tatamiId => &$eintraege) {
            $zeit = $beginn;
            foreach ($eintraege as &$e) {
                $e['beginn'] = $tatamiId === 0 ? null : sprintf('%02d:%02d', intdiv((int) $zeit, 60) % 24, (int) $zeit % 60);
                $zeit += $e['dauer'];
            }
            unset($e);
        }
        return $plan;
    }

    /** @param array<string, mixed> $daten beginn (HH:MM), minuten_kata, minuten_kumite … */
    public function einstellungenSpeichern(int $turnierId, array $daten): void
    {
        $turnier = $this->db->eine('SELECT einstellungen FROM turnier WHERE id = ?', [$turnierId]);
        $e = json_decode((string) ($turnier['einstellungen'] ?? ''), true) ?: [];
        if (isset($daten['beginn']) && preg_match('/^\d{1,2}:\d{2}$/', (string) $daten['beginn'])) {
            $e['beginn'] = $daten['beginn'];
        }
        foreach (array_keys(self::STANDARD_MINUTEN) as $d) {
            $wert = str_replace(',', '.', (string) ($daten["minuten_$d"] ?? ''));
            if (is_numeric($wert) && (float) $wert > 0 && (float) $wert < 60) {
                $e['minuten'][$d] = (float) $wert;
            }
        }
        $this->db->update('turnier', ['einstellungen' => json_encode($e)], ['id' => $turnierId]);
        $this->protokoll->revisionErhoehen($turnierId);
    }

    /** @return array<string, float> */
    public function minutenJeEinheit(int $turnierId): array
    {
        $e = json_decode((string) $this->db->wert('SELECT einstellungen FROM turnier WHERE id = ?', [$turnierId]), true) ?: [];
        return array_merge(self::STANDARD_MINUTEN, array_map('floatval', $e['minuten'] ?? []));
    }

    /** @param array<string, mixed> $p */
    private function dauer(array $p, float $minutenJeEinheit): float
    {
        $einheiten = (int) ($p['kaempfe'] ?? 0) + (int) ($p['auftritte'] ?? 0);
        if ($einheiten === 0 && in_array($p['status'], ['wartet'], true)) {
            // wartende Finalrunde: Schätzung 4 Einheiten (Halbfinals, Platz 3, Finale bzw. 4 Vorträge)
            $einheiten = 4;
        }
        if (!isset($p['kaempfe'])) {
            $einheiten = (int) $this->db->wert("SELECT COUNT(*) FROM begegnung WHERE phase_id = ? AND status <> 'freilos'", [$p['id']])
                + (int) $this->db->wert('SELECT COUNT(*) FROM auftritt WHERE phase_id = ?', [$p['id']]);
            $einheiten = $einheiten ?: ($p['status'] === 'wartet' ? 4 : 0);
        }
        return $einheiten * $minutenJeEinheit;
    }

    private function minutenAusUhrzeit(string $uhrzeit): int
    {
        [$h, $m] = array_map('intval', explode(':', $uhrzeit) + [1 => 0]);
        return $h * 60 + $m;
    }

    /** @return array<string, mixed> */
    private function phase(int $turnierId, int $phaseId): array
    {
        $p = $this->db->eine(
            'SELECT p.*, ' . self::GLEICHE_FLAECHE . ', k.turnier_id FROM phase p JOIN klasse k ON k.id = p.klasse_id WHERE p.id = ?',
            [$phaseId],
        );
        if ($p === null || (int) $p['turnier_id'] !== $turnierId) {
            throw new FachFehler('Phase nicht gefunden.');
        }
        return $p;
    }
}
