<?php

declare(strict_types=1);

namespace Wettkampf\Domain;

/**
 * Graduierung als ordinale Zahl, damit Vergleiche („ab 3. Kyu“) direkt
 * funktionieren:  10. Kyu = 1 … 1. Kyu = 10, 1. Dan = 11 … 10. Dan = 20.
 */
final class Graduierung
{
    public const MIN = 1;
    public const MAX = 20;

    public static function kyu(int $kyu): int
    {
        if ($kyu < 1 || $kyu > 10) {
            throw new \InvalidArgumentException("Ungültiger Kyu-Grad: $kyu");
        }
        return 11 - $kyu;
    }

    public static function dan(int $dan): int
    {
        if ($dan < 1 || $dan > 10) {
            throw new \InvalidArgumentException("Ungültiger Dan-Grad: $dan");
        }
        return 10 + $dan;
    }

    /**
     * Liest Angaben wie „5. Kyu“, „5 Kyu“, „5K“, „5kyu“, „1. Dan“, „1D“,
     * „Shodan“. Gibt null zurück, wenn nichts erkannt wird.
     */
    public static function parse(string $text): ?int
    {
        $t = mb_strtolower(trim($text));
        if ($t === '') {
            return null;
        }
        $namen = ['shodan' => 1, 'nidan' => 2, 'sandan' => 3, 'yondan' => 4, 'godan' => 5];
        if (isset($namen[$t])) {
            return self::dan($namen[$t]);
        }
        if (preg_match('/^(\d{1,2})\s*\.?\s*(kyu|k|dan|d)$/u', $t, $m)) {
            $zahl = (int) $m[1];
            if ($zahl < 1 || $zahl > 10) {
                return null;
            }
            return str_starts_with($m[2], 'k') ? self::kyu($zahl) : self::dan($zahl);
        }
        return null;
    }

    public static function text(int $grad): string
    {
        if ($grad >= 11) {
            return ($grad - 10) . '. Dan';
        }
        if ($grad >= 1) {
            return (11 - $grad) . '. Kyu';
        }
        return '–';
    }

    /** Beschreibung eines Bereichs, z. B. „6.–4. Kyu“, „ab 3. Kyu“, „bis 5. Kyu“. */
    public static function bereichText(?int $min, ?int $max): string
    {
        if ($min === null && $max === null) {
            return 'alle Grade';
        }
        if ($max === null) {
            return 'ab ' . self::text($min);
        }
        if ($min === null) {
            return 'bis ' . self::text($max);
        }
        if ($min === $max) {
            return self::text($min);
        }
        if ($min <= 10 && $max <= 10) {
            return (11 - $min) . '.–' . self::text($max);
        }
        return self::text($min) . ' – ' . self::text($max);
    }

    /** @return array<int, string> für Auswahllisten */
    public static function auswahl(): array
    {
        $liste = [];
        for ($g = self::MIN; $g <= self::MAX; $g++) {
            $liste[$g] = self::text($g);
        }
        return $liste;
    }
}
