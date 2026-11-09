<?php

declare(strict_types=1);

namespace Wettkampf\Domain\Turnierbaum;

/**
 * Erzeugt die Begegnungen eines K.-o.-Baums aus den gelosten Positionen.
 *
 * Quellen einer Seite:
 *   ['typ' => 'start', 'id' => Start-ID]
 *   ['typ' => 'frei']                          Freilos
 *   ['typ' => 'sieger',    'von' => Schlüssel] Sieger einer Begegnung
 *   ['typ' => 'verlierer', 'von' => Schlüssel] Verlierer (Kampf um Platz 3)
 */
final class KoBaum
{
    /**
     * @param list<int|null> $positionen Ergebnis von Losung::positionen()
     * @param int $bisAnzahl Baum endet, wenn so viele übrig sind (1 = bis zum Sieger; Pools: 2, 4 …)
     * @param bool $platz3 Kampf um Platz 3 zwischen den Halbfinal-Verlierern
     * @return list<array{schluessel: string, runde: int, position: int, art: string, aka: array<string, mixed>, shiro: array<string, mixed>}>
     */
    public static function begegnungen(array $positionen, int $bisAnzahl = 1, bool $platz3 = true): array
    {
        $groesse = count($positionen);
        if ($groesse < 2 || ($groesse & ($groesse - 1)) !== 0) {
            throw new \InvalidArgumentException('Positionsliste muss eine Zweierpotenz lang sein.');
        }
        if ($bisAnzahl < 1 || ($bisAnzahl & ($bisAnzahl - 1)) !== 0) {
            throw new \InvalidArgumentException('Aufsteigerzahl muss eine Zweierpotenz sein.');
        }

        $quellen = array_map(
            static fn(?int $id) => $id === null ? ['typ' => 'frei'] : ['typ' => 'start', 'id' => $id],
            $positionen,
        );
        $begegnungen = [];
        $runde = 0;
        while (count($quellen) > $bisAnzahl) {
            $runde++;
            $naechste = [];
            for ($i = 0; $i < count($quellen); $i += 2) {
                $position = intdiv($i, 2) + 1;
                $schluessel = "R{$runde}-{$position}";
                $begegnungen[] = [
                    'schluessel' => $schluessel,
                    'runde' => $runde,
                    'position' => $position,
                    'art' => count($quellen) === 2 ? 'finale' : 'ko',
                    'aka' => $quellen[$i],
                    'shiro' => $quellen[$i + 1],
                ];
                $naechste[] = ['typ' => 'sieger', 'von' => $schluessel];
            }
            $quellen = $naechste;
        }

        // Kampf um Platz 3: Verlierer der beiden Halbfinals (nur bei vollständigem Baum ab 4)
        if ($platz3 && $bisAnzahl === 1 && $groesse >= 4) {
            $halbfinale = $runde - 1;
            $begegnungen[] = [
                'schluessel' => 'P3',
                'runde' => $runde,
                'position' => 0,
                'art' => 'platz3',
                'aka' => ['typ' => 'verlierer', 'von' => "R{$halbfinale}-1"],
                'shiro' => ['typ' => 'verlierer', 'von' => "R{$halbfinale}-2"],
            ];
        }
        return $begegnungen;
    }

    /**
     * Bezeichnung einer Runde anhand der Teilnehmerzahl in dieser Runde.
     */
    public static function rundenName(int $baumGroesse, int $runde, string $art = 'ko'): string
    {
        if ($art === 'platz3') {
            return 'Kampf um Platz 3';
        }
        $teilnehmer = intdiv($baumGroesse, 2 ** ($runde - 1));
        return match ($teilnehmer) {
            2 => 'Finale',
            4 => 'Halbfinale',
            8 => 'Viertelfinale',
            16 => 'Achtelfinale',
            default => "Runde $runde",
        };
    }
}
