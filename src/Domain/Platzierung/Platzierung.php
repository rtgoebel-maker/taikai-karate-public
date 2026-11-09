<?php

declare(strict_types=1);

namespace Wettkampf\Domain\Platzierung;

/**
 * Ermittelt Platzierungen aus K.-o., „jeder gegen jeden“ und Punkterunden.
 * Ergebnis immer: Liste von ['platz' => int, 'start_id' => int, …].
 * Nicht auflösbare Gleichstände werden gemeldet; die Wettkampfleitung
 * entscheidet dann per Stichentscheid (Entscheidungskampf/Kampfrichterentscheid).
 */
final class Platzierung
{
    /**
     * Platz 1/2 aus dem Finale, Platz 3/4 aus dem Kampf um Platz 3.
     *
     * @param array{sieger: int, verlierer: int}|null $finale
     * @param array{sieger: int, verlierer: int}|null $platz3
     * @return list<array{platz: int, start_id: int}>
     */
    public static function ausKo(?array $finale, ?array $platz3): array
    {
        $plaetze = [];
        if ($finale !== null) {
            $plaetze[] = ['platz' => 1, 'start_id' => $finale['sieger']];
            $plaetze[] = ['platz' => 2, 'start_id' => $finale['verlierer']];
        }
        if ($platz3 !== null) {
            $plaetze[] = ['platz' => 3, 'start_id' => $platz3['sieger']];
            $plaetze[] = ['platz' => 4, 'start_id' => $platz3['verlierer']];
        }
        return $plaetze;
    }

    /**
     * Tabelle „jeder gegen jeden“. Reihenfolge: Siege → direkter Vergleich
     * (bei genau zwei Gleichen) → Ippon → Wazaari → Flaggen → Stichentscheid.
     *
     * @param list<int> $startIds
     * @param list<array{aka: int, shiro: int, sieger: int, aka_ippon?: int, aka_wazaari?: int, shiro_ippon?: int, shiro_wazaari?: int, aka_flaggen?: int, shiro_flaggen?: int}> $begegnungen
     * @param array<int, int> $stichentscheid Start-ID → Rang innerhalb des Gleichstands (1 = vorne)
     * @return array{tabelle: list<array<string, int>>, gleichstand: list<list<int>>}
     */
    public static function ausJederGegenJeden(array $startIds, array $begegnungen, array $stichentscheid = []): array
    {
        $tabelle = [];
        foreach ($startIds as $id) {
            $tabelle[$id] = ['start_id' => $id, 'kaempfe' => 0, 'siege' => 0, 'ippon' => 0, 'wazaari' => 0, 'flaggen' => 0];
        }
        foreach ($begegnungen as $b) {
            foreach (['aka', 'shiro'] as $seite) {
                $id = $b[$seite];
                if (!isset($tabelle[$id])) {
                    continue;
                }
                $tabelle[$id]['kaempfe']++;
                $tabelle[$id]['siege'] += $b['sieger'] === $id ? 1 : 0;
                $tabelle[$id]['ippon'] += $b["{$seite}_ippon"] ?? 0;
                $tabelle[$id]['wazaari'] += $b["{$seite}_wazaari"] ?? 0;
                $tabelle[$id]['flaggen'] += $b["{$seite}_flaggen"] ?? 0;
            }
        }

        $direkt = static function (int $a, int $b) use ($begegnungen): int {
            foreach ($begegnungen as $x) {
                if (($x['aka'] === $a && $x['shiro'] === $b) || ($x['aka'] === $b && $x['shiro'] === $a)) {
                    return $x['sieger'] === $a ? -1 : 1;
                }
            }
            return 0;
        };
        $schluessel = static fn(array $z) => [-$z['siege'], -$z['ippon'], -$z['wazaari'], -$z['flaggen']];

        $zeilen = array_values($tabelle);
        usort($zeilen, static function ($x, $y) use ($schluessel, $direkt, $zeilen, $stichentscheid) {
            if ($x['siege'] === $y['siege']) {
                // direkter Vergleich nur, wenn genau diese beiden siegesgleich sind
                $gleich = array_filter($zeilen, static fn($z) => $z['siege'] === $x['siege']);
                if (count($gleich) === 2 && ($d = $direkt($x['start_id'], $y['start_id'])) !== 0) {
                    return $d;
                }
            }
            return [$schluessel($x), $stichentscheid[$x['start_id']] ?? PHP_INT_MAX]
                <=> [$schluessel($y), $stichentscheid[$y['start_id']] ?? PHP_INT_MAX];
        });

        // Gleichstände erkennen (gleiche Kennzahlen, kein Direktvergleich, kein Stichentscheid)
        $gleichstand = [];
        $gruppe = [$zeilen[0]['start_id'] ?? 0];
        for ($i = 1; $i <= count($zeilen); $i++) {
            $vorher = $zeilen[$i - 1];
            $aktuell = $zeilen[$i] ?? null;
            $gleich = $aktuell !== null
                && $schluessel($vorher) === $schluessel($aktuell)
                && !(count(array_filter($zeilen, static fn($z) => $z['siege'] === $aktuell['siege'])) === 2 && $direkt($vorher['start_id'], $aktuell['start_id']) !== 0)
                && (($stichentscheid[$vorher['start_id']] ?? null) === null || ($stichentscheid[$aktuell['start_id']] ?? null) === null
                    || $stichentscheid[$vorher['start_id']] === $stichentscheid[$aktuell['start_id']]);
            if ($gleich) {
                $gruppe[] = $aktuell['start_id'];
                continue;
            }
            if (count($gruppe) > 1) {
                $gleichstand[] = $gruppe;
            }
            $gruppe = $aktuell === null ? [] : [$aktuell['start_id']];
        }

        foreach ($zeilen as $i => &$z) {
            $z['platz'] = $i + 1;
        }
        return ['tabelle' => $zeilen, 'gleichstand' => $gleichstand];
    }

    /**
     * Rangfolge nach Punkten: Gesamtpunkte → Nebenwertung (Summe inkl.
     * gestrichener Wertungen) → Stichentscheid.
     *
     * @param list<array{start_id: int, gesamt: float, nebenwertung: float, stichentscheid?: int|null}> $auftritte
     * @return array{tabelle: list<array<string, mixed>>, gleichstand: list<list<int>>}
     */
    public static function ausPunkten(array $auftritte): array
    {
        // Gleichstand: gewertete Summe → plus niedrigste Wertung → plus höchste (alle) → Stechen (stichentscheid)
        $schluessel = static fn(array $a) => [-round($a['gesamt'], 2), -round($a['mit_niedrigster'] ?? $a['gesamt'], 2), -round($a['nebenwertung'], 2)];
        usort($auftritte, static fn($x, $y) => [$schluessel($x), $x['stichentscheid'] ?? PHP_INT_MAX]
            <=> [$schluessel($y), $y['stichentscheid'] ?? PHP_INT_MAX]);

        $gleichstand = [];
        $gruppe = [];
        foreach ($auftritte as $i => $a) {
            $vorher = $auftritte[$i - 1] ?? null;
            $gleich = $vorher !== null && $schluessel($vorher) === $schluessel($a)
                && (($vorher['stichentscheid'] ?? null) === null || ($a['stichentscheid'] ?? null) === null
                    || $vorher['stichentscheid'] === $a['stichentscheid']);
            if ($gleich) {
                $gruppe[] = $a['start_id'];
            } else {
                if (count($gruppe) > 1) {
                    $gleichstand[] = $gruppe;
                }
                $gruppe = [$a['start_id']];
            }
        }
        if (count($gruppe) > 1) {
            $gleichstand[] = $gruppe;
        }
        foreach ($auftritte as $i => &$a) {
            $a['platz'] = $i + 1;
        }
        return ['tabelle' => $auftritte, 'gleichstand' => $gleichstand];
    }

    /**
     * Ist ein Gleichstand für die Vergabe relevant? (berührt einen Platz ≤ $bis)
     *
     * @param list<array<string, mixed>> $tabelle
     * @param list<list<int>> $gleichstand
     */
    public static function relevanterGleichstand(array $tabelle, array $gleichstand, int $bis): bool
    {
        $platzVon = array_column($tabelle, 'platz', 'start_id');
        foreach ($gleichstand as $gruppe) {
            if (min(array_map(static fn($id) => $platzVon[$id], $gruppe)) <= $bis) {
                return true;
            }
        }
        return false;
    }
}
