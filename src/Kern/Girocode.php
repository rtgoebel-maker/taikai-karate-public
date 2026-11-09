<?php

declare(strict_types=1);

namespace Wettkampf\Kern;

/**
 * Überweisungs-QR-Code für Banking-Apps („GiroCode“, EPC-QR-Code nach EPC069-12, Version 002, UTF-8):
 * Empfänger, IBAN, Betrag und Verwendungszweck werden beim Scannen in die Überweisung übernommen.
 *
 * Die Bankverbindung ist in Taikai Freitext – IBAN (mit Prüfziffer) und BIC werden daraus gelesen.
 */
final class Girocode
{
    /**
     * Nutzdaten des QR-Codes, oder null, wenn Name oder gültige IBAN fehlen.
     *
     * @param string $empfaenger Anschrift des Empfängers – die erste Zeile ist der Name
     * @param int $cent Betrag in Cent (0 = Betrag gibt der Zahler selbst ein)
     */
    public static function nutzdaten(string $empfaenger, string $bankText, int $cent, string $zweck): ?string
    {
        $name = self::text(self::ersteZeile($empfaenger), 70);
        $iban = self::iban($bankText);
        if ($name === '' || $iban === null || $cent < 0 || $cent > 99_999_999_999) {
            return null;
        }
        return implode("\n", [
            'BCD',
            '002',
            '1', // UTF-8
            'SCT',
            self::bic($bankText) ?? '',
            $name,
            $iban,
            $cent > 0 ? 'EUR' . number_format($cent / 100, 2, '.', '') : '',
            '', // Purpose-Code
            '', // strukturierte Referenz
            self::text($zweck, 140),
        ]);
    }

    /** Erste gültige IBAN im Text (Leerzeichen erlaubt, Prüfziffer nach ISO 13616), ohne Leerzeichen. */
    public static function iban(string $text): ?string
    {
        if (!preg_match_all('/\b[A-Z]{2}\d{2}(?:[ ]?[A-Z0-9]){11,30}\b/i', $text, $treffer)) {
            return null;
        }
        foreach ($treffer[0] as $kandidat) {
            $iban = strtoupper(str_replace(' ', '', $kandidat));
            if (strlen($iban) >= 15 && strlen($iban) <= 34 && self::ibanPruefziffer($iban)) {
                return $iban;
            }
        }
        return null;
    }

    /** BIC nach „BIC“/„SWIFT“ im Text (8 oder 11 Zeichen). */
    public static function bic(string $text): ?string
    {
        return preg_match('/\b(?:BIC|SWIFT)\s*:?\s*([A-Z]{4}[A-Z]{2}[A-Z0-9]{2}(?:[A-Z0-9]{3})?)\b/i', $text, $m) ? strtoupper($m[1]) : null;
    }

    /** Lesbare IBAN in Vierergruppen. */
    public static function ibanLesbar(string $iban): string
    {
        return implode(' ', str_split($iban, 4));
    }

    /** Erste nicht leere Zeile, gekürzt (Empfängername aus der Anschrift). */
    public static function ersteZeile(string $text): string
    {
        foreach (explode("\n", $text) as $zeile) {
            if (trim($zeile) !== '') {
                return trim($zeile);
            }
        }
        return '';
    }

    private static function ibanPruefziffer(string $iban): bool
    {
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/', $iban)) {
            return false;
        }
        $umgestellt = substr($iban, 4) . substr($iban, 0, 4);
        $rest = 0;
        foreach (str_split($umgestellt) as $zeichen) {
            $zahl = ctype_alpha($zeichen) ? (string) (ord($zeichen) - 55) : $zeichen;
            foreach (str_split($zahl) as $ziffer) {
                $rest = ($rest * 10 + (int) $ziffer) % 97;
            }
        }
        return $rest === 1;
    }

    /** Eine Zeile, ohne Steuerzeichen, auf $max Zeichen gekürzt. */
    private static function text(string $text, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text)), 0, $max);
    }
}
