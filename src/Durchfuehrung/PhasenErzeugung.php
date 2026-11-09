<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung;

use Wettkampf\Domain\Auslosung\Losung;
use Wettkampf\Domain\Auslosung\Zufall;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Domain\Klasse\Rundenregel;
use Wettkampf\Domain\Turnierbaum\JederGegenJeden;
use Wettkampf\Domain\Turnierbaum\KoBaum;
use Wettkampf\Kern\Db\Database;

/**
 * Legt Phasen, Begegnungen und Auftritte einer Klasse nach ihrem Modus an.
 *
 * Modi:
 *   auto    1 Starter: kampflos · 2: ein Kampf · 3: jeder gegen jeden · ab 4: K.-o. + Kampf um Platz 3
 *   jgj     jeder gegen jeden
 *   pools   K.-o. in Pools bis auf N Aufsteiger, danach Finalrunde (K.-o. oder Punkte);
 *           Pool-Anzahl so, dass alle Pools gleich groß (möglichst gerade) sind
 *   punkte  Punkte-Vorrunde, die besten N ins Punkte-Finale; ab 9 Startern in Pools zu
 *           höchstens 8, dann immer die besten zwei je Pool ins Finale
 */
final class PhasenErzeugung
{
    /** Kata im Punktesystem: höchstens so viele Starter je Pool … */
    public const MAX_PUNKTE_POOL = 8;
    /** … und bei mehreren Pools so viele Finalisten je Pool */
    public const FINALISTEN_JE_PUNKTE_POOL = 2;

    /** @var list<array<string, mixed>> */
    private array $regeln = [];

    public function __construct(private readonly Database $db) {}

    /**
     * @param array<string, mixed> $klasse
     * @param list<array{id: int, gruppe: int|string|null}> $starter
     * @return array<string, mixed> Protokoll der Auslosung (Positionen je Phase)
     */
    public function klasseErzeugen(array $klasse, array $starter, Zufall $zufall): array
    {
        $n = count($starter);
        if ($n === 0) {
            throw new FachFehler("Klasse {$klasse['code']} hat keine Starter.");
        }
        $this->regeln = $this->db->alle('SELECT * FROM klasse_regel WHERE klasse_id = ?', [$klasse['id']]);
        $trennung = (bool) $klasse['dojo_trennung'];
        $protokoll = ['phasen' => []];

        switch ($klasse['modus']) {
            case 'pools':
                $aufsteiger = (int) $klasse['aufsteiger_pro_pool'];
                // jeder Pool braucht mehr Starter als Aufsteiger …
                $anzahl = max(1, min((int) $klasse['anzahl_pools'], intdiv($n, $aufsteiger + 1)));
                // … und die Finalrunde mindestens 4 Starter, damit Platz 1–4 ausgekämpft werden
                while ($anzahl * $aufsteiger < 4 && $aufsteiger < 8) {
                    $aufsteiger *= 2;
                }
                $anzahl = max(1, min($anzahl, intdiv($n, $aufsteiger + 1)));
                // gleich große, möglichst gerade Pools – höchstens ein Pool mehr oder weniger als gewünscht
                if ($anzahl >= 2) {
                    $anzahl = Losung::poolAnzahl($n, $anzahl, max(2, $anzahl - 1), max(2, min($anzahl + 1, intdiv($n, $aufsteiger + 1))));
                }
                if ($n <= max(4, $anzahl * $aufsteiger)) {
                    // zu wenige Starter für Pools: direkt in die Finalrunde
                    $phase = $this->phase($klasse, 'finalrunde', 1, 'Finalrunde', $this->finalWertung($klasse), null);
                    $protokoll['phasen'][] = $this->finalrundeErzeugen($phase, $klasse, $starter, $zufall);
                    break;
                }
                foreach (Losung::poolsBilden($starter, $anzahl, $zufall, $trennung) as $i => $pool) {
                    $phase = $this->phase($klasse, 'pool', $i + 1, self::poolName($i), $klasse['wertung'], $aufsteiger);
                    $protokoll['phasen'][] = $this->koErzeugen($phase, $klasse, $pool, $zufall, $trennung, $aufsteiger, false, $anzahl);
                }
                $this->phase($klasse, 'finalrunde', 1, 'Finalrunde', $this->finalWertung($klasse), null, 'wartet');
                break;

            case 'punkte':
                $finalisten = (int) $klasse['finalisten'];
                if ($n <= $finalisten) {
                    $phase = $this->phase($klasse, 'punkte_finale', 1, 'Finale', $this->finalWertung($klasse), null);
                    $protokoll['phasen'][] = $this->auftritteErzeugen($phase, $klasse, $zufall->mischen($starter), true);
                } else {
                    $pools = (int) ceil($n / self::MAX_PUNKTE_POOL);
                    if ($pools <= 1) {
                        $phase = $this->phase($klasse, 'punkte_vorrunde', 1, 'Vorrunde', $klasse['wertung'], $finalisten);
                        $protokoll['phasen'][] = $this->auftritteErzeugen($phase, $klasse, $zufall->mischen($starter), false);
                    } else {
                        // immer die besten zwei je Pool – Punkte verschiedener Pools (andere Kampfrichter) nicht vergleichen
                        foreach (Losung::poolsBilden($starter, $pools, $zufall, $trennung) as $i => $pool) {
                            $phase = $this->phase($klasse, 'punkte_vorrunde', $i + 1, self::poolName($i), $klasse['wertung'], self::FINALISTEN_JE_PUNKTE_POOL);
                            $protokoll['phasen'][] = $this->auftritteErzeugen($phase, $klasse, $zufall->mischen($pool), false);
                        }
                    }
                    $this->phase($klasse, 'punkte_finale', 1, 'Finale', $this->finalWertung($klasse), null, 'wartet');
                }
                break;

            case 'jgj':
                $phase = $this->phase($klasse, 'jgj', 1, 'Jeder gegen jeden', $klasse['wertung'], null);
                $protokoll['phasen'][] = $this->jgjErzeugen($phase, $klasse, $zufall->mischen($starter), true);
                break;

            default: // auto
                if ($n <= 1 || $n === 3) {
                    $phase = $this->phase($klasse, 'jgj', 1, $n === 1 ? 'Kampflos' : 'Jeder gegen jeden', $klasse['wertung'], null);
                    $protokoll['phasen'][] = $this->jgjErzeugen($phase, $klasse, $zufall->mischen($starter), true);
                } else {
                    $phase = $this->phase($klasse, 'ko', 1, 'K.-o.-Runde', $klasse['wertung'], null);
                    $protokoll['phasen'][] = $this->koErzeugen($phase, $klasse, $starter, $zufall, $trennung, 1, true, 1);
                }
        }
        return $protokoll;
    }

    /**
     * Füllt eine (wartende) Finalrunde mit den Aufsteigern.
     *
     * @param array<string, mixed> $phase
     * @param array<string, mixed> $klasse
     * @param list<array{id: int, gruppe: int|string|null}> $starter in Ranglisten- bzw. Pool-Reihenfolge
     * @return array<string, mixed>
     */
    public function finalrundeErzeugen(array $phase, array $klasse, array $starter, Zufall $zufall): array
    {
        $this->regeln = $this->db->alle('SELECT * FROM klasse_regel WHERE klasse_id = ?', [$klasse['id']]);
        $n = count($starter);

        if ($phase['typ'] === 'punkte_finale') {
            // nach der Punkte-Vorrunde: Bester zuletzt – bei Pools nach Rang verschränkt (je Pool die 1., dann die 2. …)
            $rang = [];
            $zaehler = [];
            foreach ($starter as $i => $s) {
                $gruppe = (string) ($s['gruppe'] ?? '');
                $zaehler[$gruppe] = ($zaehler[$gruppe] ?? 0) + 1;
                $rang[] = [$zaehler[$gruppe], $i, $s];
            }
            usort($rang, static fn($x, $y) => [$x[0], $x[1]] <=> [$y[0], $y[1]]);
            return $this->auftritteErzeugen($phase, $klasse, array_reverse(array_column($rang, 2)), true);
        }
        if ($klasse['finale_art'] === 'punkte') {
            return $this->auftritteErzeugen($phase, $klasse, $zufall->mischen($starter), true);
        }
        if ($n <= 1 || $n === 3) {
            return $this->jgjErzeugen($phase, $klasse, $zufall->mischen($starter), true);
        }
        // Gruppen = Herkunfts-Pool: Kreuzung, damit Pool-Kollegen sich erst im Finale treffen
        return $this->koErzeugen($phase, $klasse, $starter, $zufall, true, 1, true, 1);
    }

    /**
     * @param array<string, mixed> $phase
     * @param array<string, mixed> $klasse
     * @param list<array{id: int, gruppe: int|string|null}> $starter
     * @return array<string, mixed>
     */
    private function koErzeugen(array $phase, array $klasse, array $starter, Zufall $zufall, bool $trennung, int $bisAnzahl, bool $platz3, int $poolAnzahl): array
    {
        $positionen = Losung::positionen($starter, $zufall, $trennung);
        $groesse = count($positionen);
        $this->db->update('phase', [
            'baum_groesse' => $groesse,
            'starter' => json_encode(array_column($starter, 'id')),
        ], ['id' => $phase['id']]);
        if (count($starter) <= $bisAnzahl) {
            return ['phase' => $phase['bezeichnung'], 'positionen' => $positionen];
        }

        $istFinalPhase = $phase['typ'] !== 'pool';
        $ids = [];
        foreach (KoBaum::begegnungen($positionen, min($bisAnzahl, $groesse), $platz3) as $b) {
            $teilnehmer = intdiv($groesse, 2 ** ($b['runde'] - 1));
            $istFinale = in_array($b['art'], ['finale', 'platz3'], true);
            $basis = $istFinale && $istFinalPhase ? $this->finalWertung($klasse) : $phase['wertung'];
            $regel = Rundenregel::fuer($klasse, $this->regeln, $teilnehmer * $poolAnzahl, $istFinale && $istFinalPhase, $basis);
            $ids[$b['schluessel']] = $this->db->insert('begegnung', [
                'phase_id' => $phase['id'],
                'runde' => $b['runde'],
                'position' => $b['position'],
                'art' => $b['art'],
                'status' => 'wartet',
                'wertungssystem' => $regel['system'],
                'kumite_form' => $regel['kumite_form'],
                'kata_liste' => $regel['kata_liste'],
            ] + $this->quellen($b, null));
        }
        // Quellen mit IDs auflösen (W:/L:), danach Freilose auswerten
        foreach (KoBaum::begegnungen($positionen, min($bisAnzahl, $groesse), $platz3) as $b) {
            $this->db->update('begegnung', $this->quellen($b, $ids), ['id' => $ids[$b['schluessel']]]);
        }
        foreach ($this->db->alle('SELECT * FROM begegnung WHERE phase_id = ? AND runde = 1', [$phase['id']]) as $b) {
            if ($b['aka_quelle'] === 'F' || $b['shiro_quelle'] === 'F') {
                $sieger = $b['aka_quelle'] === 'F' ? $b['shiro_start_id'] : $b['aka_start_id'];
                $this->db->update('begegnung', ['status' => 'freilos', 'sieger_start_id' => $sieger], ['id' => $b['id']]);
            } elseif ($b['aka_start_id'] !== null && $b['shiro_start_id'] !== null) {
                $this->db->update('begegnung', ['status' => 'bereit'], ['id' => $b['id']]);
            }
        }
        return ['phase' => $phase['bezeichnung'], 'positionen' => $positionen];
    }

    /**
     * @param array<string, mixed> $phase
     * @param array<string, mixed> $klasse
     * @param list<array{id: int, gruppe: int|string|null}> $starter
     * @return array<string, mixed>
     */
    private function jgjErzeugen(array $phase, array $klasse, array $starter, bool $istFinale): array
    {
        $ids = array_column($starter, 'id');
        $this->db->update('phase', ['starter' => json_encode($ids)], ['id' => $phase['id']]);
        $basis = $istFinale ? $this->finalWertung($klasse) : $phase['wertung'];
        $regel = Rundenregel::fuer($klasse, $this->regeln, count($ids), $istFinale, $basis);
        foreach (JederGegenJeden::paarungen($ids) as $p) {
            $this->db->insert('begegnung', [
                'phase_id' => $phase['id'],
                'runde' => $p['runde'],
                'position' => $p['position'],
                'art' => 'jgj',
                'aka_start_id' => $p['aka'],
                'shiro_start_id' => $p['shiro'],
                'aka_quelle' => 'S:' . $p['aka'],
                'shiro_quelle' => 'S:' . $p['shiro'],
                'status' => 'bereit',
                'wertungssystem' => $regel['system'],
                'kumite_form' => $regel['kumite_form'],
                'kata_liste' => $regel['kata_liste'],
            ]);
        }
        return ['phase' => $phase['bezeichnung'], 'reihenfolge' => $ids];
    }

    /**
     * @param array<string, mixed> $phase
     * @param array<string, mixed> $klasse
     * @param list<array{id: int, gruppe: int|string|null}> $starter
     * @return array<string, mixed>
     */
    private function auftritteErzeugen(array $phase, array $klasse, array $starter, bool $istFinale): array
    {
        $ids = array_column($starter, 'id');
        $this->db->update('phase', ['starter' => json_encode($ids)], ['id' => $phase['id']]);
        $regel = Rundenregel::fuer($klasse, $this->regeln, count($ids), $istFinale, 'punkte');
        foreach ($ids as $i => $startId) {
            $this->db->insert('auftritt', [
                'phase_id' => $phase['id'],
                'start_id' => $startId,
                'reihenfolge' => $i + 1,
                'kata_liste' => $regel['kata_liste'],
                'status' => 'offen',
            ]);
        }
        return ['phase' => $phase['bezeichnung'], 'reihenfolge' => $ids];
    }

    /**
     * @param array<string, mixed> $klasse
     * @return array<string, mixed>
     */
    private function phase(array $klasse, string $typ, int $nummer, string $bezeichnung, string $wertung, ?int $aufsteiger, string $status = 'bereit'): array
    {
        $daten = [
            'klasse_id' => $klasse['id'],
            'typ' => $typ,
            'nummer' => $nummer,
            'bezeichnung' => $bezeichnung,
            'wertung' => $wertung,
            'aufsteiger' => $aufsteiger,
            'status' => $status,
        ];
        $daten['id'] = $this->db->insert('phase', $daten);
        return $daten;
    }

    /** Pools mit Buchstaben: Pool A, Pool B, … (ab dem 27. Pool mit Nummer) */
    public static function poolName(int $index): string
    {
        return 'Pool ' . ($index < 26 ? chr(65 + $index) : (string) ($index + 1));
    }

    /** @param array<string, mixed> $klasse */
    private function finalWertung(array $klasse): string
    {
        if ($klasse['modus'] === 'punkte' || ($klasse['modus'] === 'pools' && $klasse['finale_art'] === 'punkte')) {
            return 'punkte';
        }
        return $klasse['wertung_finale'] ?: $klasse['wertung'];
    }

    /**
     * @param array<string, mixed> $b Begegnung aus KoBaum
     * @param array<string, int>|null $ids Schlüssel → Begegnungs-ID (null: nur feste Starter setzen)
     * @return array<string, mixed>
     */
    private function quellen(array $b, ?array $ids): array
    {
        $felder = [];
        foreach (['aka', 'shiro'] as $seite) {
            $q = $b[$seite];
            [$quelle, $start] = match ($q['typ']) {
                'start' => ['S:' . $q['id'], $q['id']],
                'frei' => ['F', null],
                'sieger' => [$ids === null ? null : 'W:' . $ids[$q['von']], null],
                'verlierer' => [$ids === null ? null : 'L:' . $ids[$q['von']], null],
            };
            $felder["{$seite}_quelle"] = $quelle;
            $felder["{$seite}_start_id"] = $start;
        }
        return $felder;
    }
}
