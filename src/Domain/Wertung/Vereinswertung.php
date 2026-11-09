<?php

declare(strict_types=1);

namespace Wettkampf\Domain\Wertung;

/**
 * Vereinswertung aus bestätigten Platzierungen: 3/2/1 Punkte für Platz 1/2/3.
 * Reihenfolge: Punkte, dann Anzahl Gold, dann Silber. Gleicher Rang nur bei völligem
 * Gleichstand (Punkte und Medaillen), danach wird übersprungen (1, 2, 2, 4).
 */
final class Vereinswertung
{
    /**
     * @param iterable<array{platz: int|string, verein: string}> $platzierungen
     * @return list<array{rang: int, verein: string, punkte: int, gold: int, silber: int, bronze: int}>
     */
    public static function berechnen(iterable $platzierungen): array
    {
        $vereine = [];
        foreach ($platzierungen as $p) {
            $platz = (int) $p['platz'];
            if ($platz < 1 || $platz > 3 || $p['verein'] === '') {
                continue;
            }
            $v = $vereine[$p['verein']] ?? ['verein' => $p['verein'], 'punkte' => 0, 'gold' => 0, 'silber' => 0, 'bronze' => 0];
            $v['punkte'] += 4 - $platz;
            $v[[1 => 'gold', 2 => 'silber', 3 => 'bronze'][$platz]]++;
            $vereine[$p['verein']] = $v;
        }
        $schluessel = static fn(array $v) => [$v['punkte'], $v['gold'], $v['silber']];
        usort($vereine, static fn($a, $b) => $schluessel($b) <=> $schluessel($a) ?: strcmp($a['verein'], $b['verein']));
        $liste = [];
        foreach ($vereine as $i => $v) {
            $gleich = $i > 0 && $schluessel($vereine[$i - 1]) === $schluessel($v);
            $liste[] = ['rang' => $gleich ? $liste[$i - 1]['rang'] : $i + 1] + $v;
        }
        return $liste;
    }
}
