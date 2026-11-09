<?php

declare(strict_types=1);

namespace Wettkampf\Domain\Auslosung;

/**
 * Verteilt Starter auf die Positionen eines K.-o.-Baums.
 *
 *  - Baumgröße = nächste Zweierpotenz ≥ Starterzahl.
 *  - Freilose liegen auf den Positionen der höchsten Setznummern der
 *    Standard-Setzliste: Sie treten nur in Runde 1 auf, verteilen sich
 *    gleichmäßig auf Hälften/Viertel, und kein Kampf hat zwei Freilose.
 *  - Optional Gruppentrennung (Dojo): Starter derselben Gruppe werden so
 *    platziert, dass sie sich möglichst spät treffen (Hälften, dann Viertel …).
 */
final class Losung
{
    /**
     * Standard-Setzliste: Position (0-basiert) → Setznummer (1-basiert).
     * Größe 8 ⇒ [1, 8, 4, 5, 2, 7, 3, 6].
     *
     * @return list<int>
     */
    public static function setzliste(int $groesse): array
    {
        if ($groesse < 2 || ($groesse & ($groesse - 1)) !== 0) {
            throw new \InvalidArgumentException('Baumgröße muss eine Zweierpotenz ≥ 2 sein.');
        }
        $liste = [1, 2];
        while (count($liste) < $groesse) {
            $summe = 2 * count($liste) + 1;
            $neu = [];
            foreach ($liste as $setz) {
                $neu[] = $setz;
                $neu[] = $summe - $setz;
            }
            $liste = $neu;
        }
        return $liste;
    }

    public static function baumGroesse(int $anzahl): int
    {
        $groesse = 2;
        while ($groesse < $anzahl) {
            $groesse *= 2;
        }
        return $groesse;
    }

    /** Runde (1 = erste Runde), in der sich die Positionen $a und $b frühestens treffen. */
    public static function treffRunde(int $a, int $b): int
    {
        $x = $a ^ $b;
        $runde = 0;
        while ($x > 0) {
            $runde++;
            $x >>= 1;
        }
        return $runde;
    }

    /**
     * @param list<array{id: int, gruppe: int|string|null}> $starter
     * @return list<int|null> Position → Start-ID (null = Freilos)
     */
    public static function positionen(array $starter, Zufall $zufall, bool $gruppenTrennung): array
    {
        $n = count($starter);
        if ($n === 0) {
            return [];
        }
        $groesse = self::baumGroesse($n);
        $setzliste = self::setzliste($groesse);

        // Positionen der Starter, sortiert nach Setznummer (Freilose = Setznummern > n)
        $starterPositionen = [];
        foreach ($setzliste as $position => $setz) {
            if ($setz <= $n) {
                $starterPositionen[$setz] = $position;
            }
        }
        ksort($starterPositionen);
        $starterPositionen = array_values($starterPositionen);

        $ergebnis = array_fill(0, $groesse, null);
        if (!$gruppenTrennung) {
            foreach ($zufall->mischen($starter) as $i => $s) {
                $ergebnis[$starterPositionen[$i]] = $s['id'];
            }
            return $ergebnis;
        }

        $frei = $starterPositionen;
        $belegtJeGruppe = [];
        foreach (self::reihenfolgeNachGruppen($starter, $zufall) as $s) {
            $gruppe = $s['gruppe'] === null ? 'id:' . $s['id'] : (string) $s['gruppe'];
            $eigene = $belegtJeGruppe[$gruppe] ?? [];

            // beste Position: späteste Begegnung mit eigenen Gruppenmitgliedern,
            // danach möglichst wenige eigene Mitglieder in der Nähe
            $beste = [];
            $besterWert = null;
            foreach ($frei as $position) {
                $minimum = PHP_INT_MAX;
                $summe = 0;
                foreach ($eigene as $andere) {
                    $r = self::treffRunde($position, $andere);
                    $minimum = min($minimum, $r);
                    $summe += $r;
                }
                $wert = [$minimum, $summe];
                if ($besterWert === null || $wert > $besterWert) {
                    $besterWert = $wert;
                    $beste = [$position];
                } elseif ($wert === $besterWert) {
                    $beste[] = $position;
                }
            }
            $gewaehlt = $beste[$zufall->zahl(count($beste))];
            $ergebnis[$gewaehlt] = $s['id'];
            $belegtJeGruppe[$gruppe][] = $gewaehlt;
            $frei = array_values(array_diff($frei, [$gewaehlt]));
        }
        return $ergebnis;
    }

    /**
     * Anzahl Pools für Kumite zwischen $min und $max: alle Pools gleich groß, möglichst mit gerader
     * Größe, möglichst nah an der gewünschten Anzahl – in dieser Rangfolge. Geht keine gleiche
     * Aufteilung, bleibt es beim Wunsch (Größen unterscheiden sich dann höchstens um 1).
     */
    public static function poolAnzahl(int $starter, int $wunsch, int $min, int $max): int
    {
        $wunsch = max($min, min($wunsch, $max));
        $beste = null;
        $besterWert = null;
        for ($p = $min; $p <= $max; $p++) {
            $gleich = $starter % $p === 0;
            $wert = [$gleich ? 0 : 1, $gleich && intdiv($starter, $p) % 2 === 0 ? 0 : 1, abs($p - $wunsch), $p];
            if ($besterWert === null || $wert < $besterWert) {
                [$beste, $besterWert] = [$p, $wert];
            }
        }
        return $beste !== null && $besterWert[0] === 0 ? $beste : $wunsch;
    }

    /**
     * Teilt Starter auf Pools auf. Mit Gruppentrennung werden Gruppen (größte
     * zuerst) in Schlangenreihenfolge verteilt, sodass sich Dojo-Mitglieder
     * möglichst auf verschiedene Pools verteilen. Poolgrößen unterscheiden sich
     * höchstens um 1.
     *
     * @param list<array{id: int, gruppe: int|string|null}> $starter
     * @return list<list<array{id: int, gruppe: int|string|null}>>
     */
    public static function poolsBilden(array $starter, int $anzahl, Zufall $zufall, bool $gruppenTrennung): array
    {
        $anzahl = max(1, min($anzahl, count($starter)));
        $reihenfolge = $gruppenTrennung ? self::reihenfolgeNachGruppen($starter, $zufall) : $zufall->mischen($starter);
        $pools = array_fill(0, $anzahl, []);
        foreach ($reihenfolge as $i => $s) {
            $runde = intdiv($i, $anzahl);
            $index = $i % $anzahl;
            $pools[$runde % 2 === 0 ? $index : $anzahl - 1 - $index][] = $s;
        }
        return $pools;
    }

    /**
     * Gruppen nach Größe absteigend (Gleichstand zufällig), innerhalb der Gruppe zufällig.
     *
     * @param list<array{id: int, gruppe: int|string|null}> $starter
     * @return list<array{id: int, gruppe: int|string|null}>
     */
    private static function reihenfolgeNachGruppen(array $starter, Zufall $zufall): array
    {
        $gruppen = [];
        foreach ($starter as $s) {
            $gruppen[$s['gruppe'] === null ? 'id:' . $s['id'] : (string) $s['gruppe']][] = $s;
        }
        $liste = [];
        foreach ($zufall->mischen(array_values($gruppen)) as $i => $mitglieder) {
            $liste[] = ['mitglieder' => $zufall->mischen($mitglieder), 'zufall' => $i];
        }
        usort($liste, static fn($a, $b) => [count($b['mitglieder']), $a['zufall']] <=> [count($a['mitglieder']), $b['zufall']]);
        return array_merge(...array_column($liste, 'mitglieder'));
    }
}
