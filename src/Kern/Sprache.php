<?php

declare(strict_types=1);

namespace Wettkampf\Kern;

/**
 * Sprache der Oberfläche (Deutsch oder Englisch), je Person umschaltbar (Cookie), ohne Wahl nach der Browsersprache.
 *
 * Übersetzt wird mit dem deutschen Text als Schlüssel: t('Turnier starten') liefert auf Englisch den Eintrag aus dem
 * Katalog lang/en/*.php, sonst den deutschen Text (nie eine Lücke). Platzhalter in geschweiften Klammern:
 * t('Gruppe {klasse} ist fertig', ['klasse' => 'K3']). Schlüssel stehen immer in einfachen Anführungszeichen, damit
 * der Katalogtest (tests/SprachKatalogTest.php) sie findet. Karate-Begriffe (Kata, Kumite, Ippon …) bleiben unverändert.
 * Hat dasselbe deutsche Wort mehrere Bedeutungen, unterscheidet ein Kontext nach „||“: t('Anmeldung||Login').
 *
 * Mails, PDFs und Drucke entstehen in der Sprache der Person, die sie auslöst (z. B. das Dojo beim Absenden der Anmeldung).
 */
final class Sprache
{
    public const SPRACHEN = ['de' => 'Deutsch', 'en' => 'English'];
    public const STANDARD = 'de';
    public const COOKIE = 'sprache';

    private static string $aktuell = self::STANDARD;
    /** @var array<string, array<string, string>> Sprache → (deutscher Text → Übersetzung) */
    private static array $kataloge = [];

    public static function aktuell(): string
    {
        return self::$aktuell;
    }

    public static function setzen(string $code): void
    {
        self::$aktuell = isset(self::SPRACHEN[$code]) ? $code : self::STANDARD;
    }

    /**
     * Sprache aus Cookie (Wahl der Person) bzw. Browser: Englisch, wenn der Browser zuerst eine andere Sprache als Deutsch
     * nennt (internationale Gäste), sonst Deutsch.
     */
    public static function erkennen(?string $cookie, ?string $acceptLanguage): string
    {
        if ($cookie !== null && isset(self::SPRACHEN[$cookie])) {
            return $cookie;
        }
        $erste = strtolower(trim(explode(',', (string) $acceptLanguage)[0]));
        if ($erste === '' || $erste === '*' || str_starts_with($erste, 'de')) {
            return self::STANDARD;
        }
        return 'en';
    }

    /**
     * Übersetzen (reiner Text, nicht escaped).
     *
     * @param array<string, scalar|null> $werte Platzhalter {name} → Wert
     */
    public static function t(string $de, array $werte = [], ?string $sprache = null): string
    {
        $sprache ??= self::$aktuell;
        // Gleiches deutsches Wort, verschiedene Bedeutungen: Kontext nach „||“ (z. B. 'Anmeldung||Login') – Deutsch zeigt nur den Teil davor
        $deutsch = str_contains($de, '||') ? strstr($de, '||', true) : $de;
        $text = $sprache === self::STANDARD ? $deutsch : (self::katalog($sprache)[$de] ?? $deutsch);
        if ($werte === []) {
            return $text;
        }
        $ersetzen = [];
        foreach ($werte as $name => $wert) {
            $ersetzen['{' . $name . '}'] = (string) $wert;
        }
        return strtr($text, $ersetzen);
    }

    /**
     * Katalog einer Sprache: alle Dateien lang/<sprache>/*.php (je Bereich eine, gibt ein Array deutsch → übersetzt zurück).
     *
     * @return array<string, string>
     */
    public static function katalog(string $sprache): array
    {
        if ($sprache === self::STANDARD || !isset(self::SPRACHEN[$sprache])) {
            return [];
        }
        if (!isset(self::$kataloge[$sprache])) {
            // Ordnername nur aus der festen Liste, nie aus der Eingabe (Pfad-Manipulation, OWASP A01; Psalm TaintedFile)
            $ordner = self::STANDARD;
            foreach (array_keys(self::SPRACHEN) as $bekannt) {
                if ($bekannt === $sprache) {
                    $ordner = $bekannt;
                }
            }
            $katalog = [];
            foreach (glob(dirname(__DIR__, 2) . '/lang/' . $ordner . '/*.php') ?: [] as $datei) {
                $teil = require $datei;
                if (is_array($teil)) {
                    $katalog = array_merge($katalog, array_filter($teil, static fn($v, $k) => is_string($k) && is_string($v) && $v !== '', ARRAY_FILTER_USE_BOTH));
                }
            }
            self::$kataloge[$sprache] = $katalog;
        }
        return self::$kataloge[$sprache];
    }

    /** Kurzer Prüfwert des Katalogs (Cache-Busting für sprache/texte.js). */
    public static function katalogVersion(string $sprache): string
    {
        return substr(hash('crc32b', (string) json_encode(self::katalog($sprache))), 0, 8);
    }

    /** Datum (JJJJ-MM-TT) in der aktuellen Sprache: 14.03.2026 bzw. 14 Mar 2026. */
    public static function datum(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '';
        }
        $teile = explode('-', substr($iso, 0, 10));
        if (count($teile) !== 3 || !checkdate((int) $teile[1], (int) $teile[2], (int) $teile[0])) {
            return $iso;
        }
        if (self::$aktuell === 'en') {
            return (int) $teile[2] . ' ' . ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][(int) $teile[1] - 1] . ' ' . $teile[0];
        }
        return "$teile[2].$teile[1].$teile[0]";
    }

    /** Geldbetrag: 12,50 € bzw. €12.50. */
    public static function euro(int $cent): string
    {
        return self::$aktuell === 'en'
            ? ($cent < 0 ? '-' : '') . '€' . number_format(abs($cent) / 100, 2, '.', ',')
            : number_format($cent / 100, 2, ',', '.') . ' €';
    }
}
