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

    /** Datum in der Sprache der Oberfläche (14.03.2026 bzw. 14 Mar 2026). */
    function datum_de(?string $iso): string
    {
        return \Wettkampf\Kern\Sprache::datum($iso);
    }

    /**
     * Übersetzen – deutscher Text als Schlüssel, Platzhalter {name}. Liefert reinen Text (für HTML: te()).
     * Schlüssel immer in einfachen Anführungszeichen (Katalogtest).
     *
     * @param array<string, scalar|null> $werte
     */
    function t(string $de, array $werte = []): string
    {
        return \Wettkampf\Kern\Sprache::t($de, $werte);
    }

    /**
     * Übersetzen und für HTML escapen.
     *
     * @param array<string, scalar|null> $werte
     */
    function te(string $de, array $werte = []): string
    {
        return e(\Wettkampf\Kern\Sprache::t($de, $werte));
    }

    /**
     * Übersetzen mit HTML im Text selbst (z. B. <strong>, Links aus dem Katalog – vertrauenswürdig); die Platzhalter werden escaped.
     *
     * @param array<string, scalar|null> $werte
     */
    function th(string $de, array $werte = []): string
    {
        return \Wettkampf\Kern\Sprache::t($de, array_map(static fn($w) => e($w), $werte));
    }

    /** Aktuelle Sprache (de/en), z. B. für <html lang>. */
    function sprache(): string
    {
        return \Wettkampf\Kern\Sprache::aktuell();
    }

    function grad(?int $grad): string
    {
        return $grad === null ? '' : Graduierung::text($grad);
    }

    function geschlecht_text(string $g): string
    {
        return match ($g) {
            'w' => t('weiblich'),
            'm' => t('männlich'),
            'd' => t('divers'),
            'mixed' => 'mixed',
            default => $g,
        };
    }

    /** Status einer Klasse bzw. Runde als Wort (statt des internen Werts). */
    function status_text(string $status): string
    {
        return match ($status) {
            'offen' => t('offen'),
            'ausgelost' => t('ausgelost'),
            'bereit' => t('bereit'),
            'wartet' => t('wartet'),
            'laeuft' => t('läuft'),
            'beendet' => t('beendet'),
            'bestaetigt' => t('bestätigt'),
            'zusammengelegt' => t('zusammengelegt'),
            'zurueckgezogen' => t('zurückgezogen'),
            'freilos' => t('Freilos'),
            // Status einer Teilnahme
            'gemeldet' => t('gemeldet'),
            'anwesend' => t('anwesend'),
            'gesperrt' => t('gesperrt||Teilnehmer'),
            'abgemeldet' => t('abgemeldet'),
            'nicht_erschienen' => t('nicht erschienen'),
            default => $status,
        };
    }

    function disziplin_text(string $d): string
    {
        if (str_starts_with($d, 'kumite:')) {
            return kumite_form_text(substr($d, 7));
        }
        return match ($d) {
            'kata' => t('Kata Einzel'),
            'kumite' => t('Kumite Einzel'),
            'kata_team' => t('Kata-Team'),
            'kumite_team' => t('Kumite-Team'),
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
            'flaggen' => t('Flaggen'),
            'punkte' => t('Punkte'),
            'kumite' => t('Kumite (Ippon/Wazaari)'),
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
