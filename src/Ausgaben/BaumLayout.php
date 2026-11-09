<?php

declare(strict_types=1);

namespace Wettkampf\Ausgaben;

/**
 * Berechnet die Darstellung eines K.-o.-Baums als Spalten je Runde mit
 * Verbindungslinien (Aushang und Druck, A4 quer).
 *
 *  - Pro Teil höchstens 4 Kämpfe der ersten Runde (eine Druckseite).
 *  - Kämpfe, deren Teilbaum über mehrere Teile reicht (Halbfinale, Finale …),
 *    erscheinen im ersten Teil rechts neben dessen Wurzel.
 *  - Kampf um Platz 3 steht unter dem Finale.
 *  - Optional eine Zielkarte je Kampf der letzten Runde (z. B. „Punktefinale“
 *    bei Pools), damit sichtbar ist, wohin die Sieger weitergehen.
 */
final class BaumLayout
{
    public const BREITE = 230;
    public const HOEHE = 112;
    public const ZEILE = 128;
    public const SPALTENABSTAND = 34;
    public const ERSTRUNDE_PRO_TEIL = 4;

    /**
     * @param list<array<string, mixed>> $begegnungen Begegnungen einer Phase (id, runde, position, art, *_quelle …)
     * @param string|null $zielText Beschriftung der Zielkarten (null = keine)
     * @return list<array{karten: list<array<string, mixed>>, linien: list<string>, breite: int, hoehe: int}>
     */
    public static function teile(array $begegnungen, ?string $zielText = null): array
    {
        $ko = array_values(array_filter($begegnungen, static fn($b) => $b['art'] !== 'platz3'));
        $platz3 = current(array_filter($begegnungen, static fn($b) => $b['art'] === 'platz3')) ?: null;
        if ($ko === []) {
            return $platz3 ? [self::einzelkarte($platz3)] : [];
        }
        $ersteRunde = count(array_filter($ko, static fn($b) => (int) $b['runde'] === 1));
        $anzahlTeile = max(1, (int) ceil($ersteRunde / self::ERSTRUNDE_PRO_TEIL));
        $maxRunde = max(array_map(static fn($b) => (int) $b['runde'], $ko));

        // Zuordnung: Teil und Position (Mitte, gemessen in Erstrunden-Zeilen) je Kampf
        $teile = array_fill(0, $anzahlTeile, ['karten' => [], 'linien' => []]);
        $lage = [];
        foreach ($ko as $b) {
            $r = (int) $b['runde'];
            $p = (int) $b['position'];
            $breite = 2 ** ($r - 1);
            $von = ($p - 1) * $breite;            // erste Erstrunden-Zeile (0-basiert)
            $bis = $von + $breite - 1;
            $teilVon = intdiv($von, self::ERSTRUNDE_PRO_TEIL);
            $teilBis = intdiv($bis, self::ERSTRUNDE_PRO_TEIL);
            if ($teilVon === $teilBis) {
                $teil = $teilVon;
                $mitte = (($von + $bis) / 2) - $teil * self::ERSTRUNDE_PRO_TEIL;
            } else {
                $teil = 0;                        // übergreifender Kampf → erster Teil, rechts der Wurzel
                $mitte = null;
            }
            $lage[(int) $b['id']] = ['teil' => $teil, 'spalte' => $r - 1, 'mitte' => $mitte, 'b' => $b];
        }
        // Übergreifende Kämpfe auf Höhe der Wurzel des ersten Teils; mehrere
        // Kämpfe derselben Runde (sehr große Bäume) untereinander
        $zeilenTeil0 = min(self::ERSTRUNDE_PRO_TEIL, $ersteRunde);
        foreach ($lage as &$l) {
            if ($l['mitte'] === null) {
                $l['mitte'] = ($zeilenTeil0 - 1) / 2 + ((int) $l['b']['position'] - 1);
            }
        }
        unset($l);

        foreach ($lage as $id => $l) {
            $teile[$l['teil']]['karten'][] = self::karte($l['b'], $l['spalte'], $l['mitte'], $l['b']['art'] === 'finale' ? 'ziel' : 'normal');
        }
        $spalten = $maxRunde;

        // Kampf um Platz 3 unter dem Finale
        if ($platz3 !== null) {
            $finale = current(array_filter($ko, static fn($b) => $b['art'] === 'finale')) ?: null;
            $mitte = $finale !== null ? $lage[(int) $finale['id']]['mitte'] + 1.15 : 0;
            $teile[0]['karten'][] = self::karte($platz3, $maxRunde - 1, $mitte, 'ziel');
        }

        // Zielkarten (z. B. Punktefinale) rechts neben jedem Kampf der letzten Runde
        if ($zielText !== null) {
            foreach ($lage as $id => $l) {
                if ((int) $l['b']['runde'] === $maxRunde) {
                    $teile[$l['teil']]['karten'][] = [
                        'art' => 'zielplatz', 'x' => self::x($maxRunde + 1), 'y' => self::y($l['mitte']),
                        'text' => $zielText, 'quelle' => $id, 'id' => null,
                    ];
                }
            }
            $spalten++;
        }

        // Linien: von jedem Vorgänger (gleicher Teil) zur Folgekarte
        foreach ($teile as $t => &$teil) {
            $position = [];
            foreach ($teil['karten'] as $k) {
                if ($k['id'] !== null) {
                    $position[$k['id']] = $k;
                }
            }
            foreach ($teil['karten'] as $k) {
                $vorgaenger = [];
                if ($k['art'] === 'zielplatz') {
                    $vorgaenger[] = $k['quelle'];
                } elseif ($k['b']['art'] !== 'platz3') {
                    foreach (['aka_quelle', 'shiro_quelle'] as $q) {
                        if (preg_match('/^W:(\d+)$/', (string) $k['b'][$q], $m)) {
                            $vorgaenger[] = (int) $m[1];
                        }
                    }
                }
                foreach ($vorgaenger as $vid) {
                    if (!isset($position[$vid])) {
                        continue;
                    }
                    $v = $position[$vid];
                    $x1 = $v['x'] + self::BREITE;
                    $y1 = $v['y'] + self::HOEHE / 2;
                    $x2 = $k['x'];
                    $y2 = $k['y'] + self::HOEHE / 2;
                    $xm = $x1 + self::SPALTENABSTAND / 2;
                    $teil['linien'][] = sprintf('M%d %d H%d V%d H%d', $x1, $y1, $xm, $y2, $x2);
                }
            }
            $maxY = max(array_map(static fn($k) => $k['y'] + self::HOEHE, $teil['karten']) ?: [self::HOEHE]);
            $teil['breite'] = self::x($spalten + 1) - self::SPALTENABSTAND;
            $teil['hoehe'] = (int) $maxY;
        }
        unset($teil);
        return array_values(array_filter($teile, static fn($t) => $t['karten'] !== []));
    }

    /** @param array<string, mixed> $b @return array<string, mixed> */
    private static function karte(array $b, int $spalte, float $mitte, string $art): array
    {
        return ['art' => $art, 'x' => self::x($spalte + 1), 'y' => self::y($mitte), 'b' => $b, 'id' => (int) $b['id']];
    }

    /** @param array<string, mixed> $b @return array{karten: list<array<string, mixed>>, linien: list<string>, breite: int, hoehe: int} */
    private static function einzelkarte(array $b): array
    {
        return ['karten' => [self::karte($b, 0, 0, 'ziel')], 'linien' => [], 'breite' => self::BREITE, 'hoehe' => self::HOEHE];
    }

    /** Linke Kante der Spalte (1-basiert). */
    private static function x(int $spalte): int
    {
        return ($spalte - 1) * (self::BREITE + self::SPALTENABSTAND);
    }

    /** Obere Kante einer Karte, deren Mitte auf Erstrunden-Zeile $mitte liegt. */
    private static function y(float $mitte): int
    {
        return (int) round($mitte * self::ZEILE + (self::ZEILE - self::HOEHE) / 2);
    }
}
