<?php

declare(strict_types=1);

use Wettkampf\Domain\Graduierung;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Url;

/*
 * Kleine Hilfsfunktionen für die Templates.
 */

if (!function_exists('e')) {
    function e(mixed $wert): string
    {
        return htmlspecialchars((string) $wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string, scalar|null> $query */
    function url(string $pfad, array $query = []): string
    {
        return Url::zu($pfad, $query);
    }

    function asset(string $pfad): string
    {
        return Url::zu('assets/' . $pfad, ['v' => asset_version($pfad)]);
    }

    /**
     * Version einer Datei unter public/assets aus ihrem Inhalt (nicht dem Änderungsdatum: das bleibt
     * beim Deployen je nach Weg erhalten). Je Anfrage nur einmal berechnet.
     */
    function asset_version(string $pfad): string
    {
        static $versionen = [];
        if (!isset($versionen[$pfad])) {
            $datei = dirname(__DIR__, 3) . '/public/assets/' . $pfad;
            $versionen[$pfad] = is_file($datei) ? substr((string) hash_file('crc32b', $datei), 0, 8) : '0';
        }
        return $versionen[$pfad];
    }

    /**
     * Programmstand der Tablets (Tisch und Ausruf): ändert sich, sobald eine ihrer Dateien neu
     * ist. Die Tablets laden sich dann selbst neu – sie sind Single-Page-Apps und holen sonst
     * nur Daten, sodass nach einem Update weiter das alte Skript liefe.
     */
    function programm_version(): string
    {
        return substr(hash('crc32b', implode('|', array_map('asset_version', ['app.js', 'tisch.js', 'ausruf.js', 'app.css', 'tisch.css', 'ausruf.css']))), 0, 8);
    }

    /** ⓘ-Info-Knopf mit Kurzerklärung und Link zur Hilfeseite (Texte: Hilfe::TEXTE). */
    function info(string $schluessel): string
    {
        return \Wettkampf\Kern\Http\Hilfe::info($schluessel);
    }

    function csrf_feld(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(Session::csrfToken()) . '">';
    }

    function datum_de(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '';
        }
        $teile = explode('-', substr($iso, 0, 10));
        return count($teile) === 3 ? "$teile[2].$teile[1].$teile[0]" : $iso;
    }

    function grad(?int $grad): string
    {
        return $grad === null ? '' : Graduierung::text($grad);
    }

    function geschlecht_text(string $g): string
    {
        return match ($g) {
            'w' => 'weiblich',
            'm' => 'männlich',
            'd' => 'divers',
            'mixed' => 'mixed',
            default => $g,
        };
    }

    /** Status einer Klasse bzw. Runde als Wort (statt des internen Werts). */
    function status_text(string $status): string
    {
        return match ($status) {
            'offen' => 'offen',
            'ausgelost' => 'ausgelost',
            'bereit' => 'bereit',
            'wartet' => 'wartet',
            'laeuft' => 'läuft',
            'beendet' => 'beendet',
            'bestaetigt' => 'bestätigt',
            'zusammengelegt' => 'zusammengelegt',
            'zurueckgezogen' => 'zurückgezogen',
            'freilos' => 'Freilos',
            default => $status,
        };
    }

    function disziplin_text(string $d): string
    {
        if (str_starts_with($d, 'kumite:')) {
            return kumite_form_text(substr($d, 7));
        }
        return match ($d) {
            'kata' => 'Kata Einzel',
            'kumite' => 'Kumite Einzel',
            'kata_team' => 'Kata-Team',
            'kumite_team' => 'Kumite-Team',
            default => $d,
        };
    }

    function kumite_form_text(?string $f): string
    {
        return match ($f) {
            'kihon_ippon' => 'Kihon-Ippon-Kumite',
            'jiyu_ippon' => 'Jiyu-Ippon-Kumite',
            'jiyu' => 'Jiyu-Kumite',
            null, '' => '',
            default => $f,
        };
    }

    function wertung_text(?string $w): string
    {
        return match ($w) {
            'flaggen' => 'Flaggen',
            'punkte' => 'Punkte',
            'kumite' => 'Kumite (Ippon/Wazaari)',
            'sanbon' => 'Kumite Shobu Sanbon',
            null, '' => '',
            default => $w,
        };
    }

    /** Markiert eine Auswahloption. */
    function sel(mixed $a, mixed $b): string
    {
        return (string) $a === (string) $b ? ' selected' : '';
    }

    function chk(mixed $wert): string
    {
        return $wert ? ' checked' : '';
    }
}
