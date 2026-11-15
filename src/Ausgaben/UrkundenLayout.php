<?php

declare(strict_types=1);

namespace Wettkampf\Ausgaben;

/**
 * Lage und Größe der Logos auf der Urkunde (turnier.einstellungen['urkunde']['logos']): je Logo ein Platz auf einem festen
 * Raster und eine Höhe in mm. Die Breite folgt dem Seitenverhältnis (nie verzerrt, höchstens BREITE_MAX mm breit).
 *
 * Raster: oben (Kopfzeile über dem Turniernamen), seitlich auf halber Höhe links/rechts (über dem Text, nicht unter
 * ihm) und unten (Zeile über Ort und Unterschriften) – oben und unten je links, Mitte, rechts.
 */
final class UrkundenLayout
{
    public const POSITIONEN = [
        'oben-links' => 'oben links', 'oben-mitte' => 'oben Mitte', 'oben-rechts' => 'oben rechts',
        'mitte-links' => 'seitlich links (halbe Höhe)', 'mitte-rechts' => 'seitlich rechts (halbe Höhe)',
        'unten-links' => 'unten links', 'unten-mitte' => 'unten Mitte', 'unten-rechts' => 'unten rechts',
    ];
    /** Wie bisher: Verein links, Verband mittig, Dojo rechts – alle in der Kopfzeile */
    public const STANDARD = [
        'verband' => ['pos' => 'oben-mitte', 'groesse' => 26],
        'verein' => ['pos' => 'oben-links', 'groesse' => 26],
        'dojo' => ['pos' => 'oben-rechts', 'groesse' => 26],
    ];
    public const GROESSE_MIN = 10;
    public const GROESSE_MAX = 60;
    public const BREITE_MAX = 80;

    /**
     * Gespeicherte bzw. übergebene Werte prüfen und mit dem Standard ergänzen.
     *
     * @param mixed $werte z. B. ['verband' => ['pos' => 'unten-mitte', 'groesse' => 30]]
     * @return array<string, array{pos: string, groesse: int}>
     */
    public static function lesen(mixed $werte): array
    {
        $werte = is_array($werte) ? $werte : [];
        $layout = [];
        foreach (self::STANDARD as $art => $standard) {
            $w = is_array($werte[$art] ?? null) ? $werte[$art] : [];
            $pos = is_string($w['pos'] ?? null) && isset(self::POSITIONEN[$w['pos']]) ? $w['pos'] : $standard['pos'];
            $groesse = is_numeric($w['groesse'] ?? null) ? (int) $w['groesse'] : $standard['groesse'];
            $layout[$art] = ['pos' => $pos, 'groesse' => max(self::GROESSE_MIN, min(self::GROESSE_MAX, $groesse))];
        }
        return $layout;
    }

    /**
     * Logos einer Zeile (oben bzw. unten) nach Spalte: links/mitte/rechts → Arten in fester Reihenfolge.
     *
     * @param array<string, array{pos: string, groesse: int}> $layout
     * @param array<string, mixed> $vorhanden Art → Bild (nur vorhandene Logos zählen)
     * @return array<string, list<string>> Spalte → Arten (leer, wenn in der Zeile nichts liegt)
     */
    public static function zeile(array $layout, array $vorhanden, string $zeile): array
    {
        $spalten = ['links' => [], 'mitte' => [], 'rechts' => []];
        $belegt = false;
        foreach ($layout as $art => $l) {
            [$z, $spalte] = explode('-', $l['pos'], 2);
            if ($z === $zeile && isset($vorhanden[$art])) {
                $spalten[$spalte][] = $art;
                $belegt = true;
            }
        }
        return $belegt ? $spalten : [];
    }

    /**
     * Stil eines Logos: Höhe in mm, Breite proportional (begrenzt) – keine Verzerrung (object-fit: contain).
     *
     * @param array{pos: string, groesse: int} $l
     */
    public static function stil(array $l): string
    {
        return 'height:' . $l['groesse'] . 'mm;max-width:' . self::BREITE_MAX . 'mm';
    }
}
