<?php

declare(strict_types=1);

namespace Wettkampf\Betrieb;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Instanz;

/**
 * Format eines Turnierpakets (JSON) – gemeinsam für Sicherung, Übergabe an die Halle und Sync (Schritt 9b).
 *
 * - Nur bekannte Tabellen und **erlaubte Spalten** (SPALTEN), Werte nur Zahl, Text, Wahrheitswert oder null:
 *   eine präparierte Datei kann keine fremden Felder setzen (Finding 3, Mass Assignment).
 * - **Prüfsumme** HMAC-SHA256 mit dem Schlüssel der exportierenden Installation (Finding 17). Der Import erkennt
 *   eigene Pakete (gültig), weist veränderte eigene Pakete ab und behandelt fremde bzw. alte Pakete ohne Prüfsumme
 *   als nicht vertrauenswürdig: Sie werden importiert, aber ohne PINs (PINS), die neu vergeben werden müssen.
 */
final class Turnierpaket
{
    public const FORMAT = 'wettkampf-turnier';
    /** 1 = bis 9a (ohne Prüfsumme), 2 = mit Prüfsumme, 3 = Anmeldungen mit Zahlungsstand (Migration 032). */
    public const VERSION = 3;

    public const EIGENE = 'eigene';
    public const FREMD = 'fremd';
    public const OHNE = 'ohne';
    /** Übergabe an die Halle: Echtheit über den Übergabe-Code geprüft (Sync), PINs werden übernommen. */
    public const UEBERGABE = 'uebergabe';

    private const KOPF = ['format', 'version', 'exportiert_am', 'pruefsumme'];

    /** Eine Kodierung für Datei und Prüfsumme: Kommazahlen bleiben Kommazahlen, kaputte Zeichen werden ersetzt. */
    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;

    /** Erlaubte Spalten je Tabelle (= Schema; ein Test hält beides gleich, neue Spalten erfordern eine bewusste Entscheidung). */
    public const SPALTEN = [
        'turnier' => ['id', 'uuid', 'name', 'datum', 'ort', 'halle', 'anzahl_tatamis', 'min_graduierung', 'meldeschluss', 'status', 'revision', 'leitung_pin_hash', 'oeffentlich', 'einstellungen', 'erstellt_am', 'fuehrung_instanz', 'fuehrung_name', 'fuehrung_seit', 'uebergabe_nr', 'fuehrung_stand', 'tisch_pin_erlaubt', 'anmelde_schluessel'],
        'tatami' => ['id', 'turnier_id', 'nummer', 'bezeichnung', 'pin_hash', 'stream_url', 'gesperrt_am', 'sperr_grund', 'uuid'],
        'verein' => ['id', 'uuid', 'name', 'kurzname', 'ort'],
        'athlet' => ['id', 'uuid', 'vorname', 'nachname', 'geburtsdatum', 'geschlecht', 'graduierung', 'verein_id', 'ausweis_nr'],
        'teilnahme' => ['id', 'turnier_id', 'athlet_id', 'startnummer', 'alter_manuell', 'status', 'ausweis_geprueft', 'nachmeldung', 'vermerk', 'disziplinen', 'uuid'],
        'team' => ['id', 'turnier_id', 'verein_id', 'name', 'disziplin', 'uuid'],
        'team_mitglied' => ['id', 'team_id', 'teilnahme_id', 'verein_id', 'rolle', 'uuid'],
        'anmeldung' => ['id', 'uuid', 'turnier_id', 'code', 'dojo', 'ansprechpartner', 'email', 'telefon', 'bemerkung', 'daten', 'preis_cent', 'status', 'grund', 'verein_id', 'uebernahme', 'eingereicht_am', 'geaendert_am', 'freigegeben_am', 'freigegeben_von',
            'aufforderung_version', 'aufforderung_cent', 'aufforderung_am', 'bezahlt_cent', 'bezahlt_am', 'zahlung_notiz', 'zahlung_von'],
        'turnier_logo' => ['id', 'turnier_id', 'art', 'mime', 'daten', 'breite', 'hoehe', 'geaendert_am', 'uuid'],
        'klasse' => ['id', 'uuid', 'turnier_id', 'code', 'bezeichnung', 'disziplin', 'kumite_form', 'geschlecht', 'alter_min', 'alter_max', 'grad_min', 'grad_max', 'erlaubte_kata', 'exklusiv_gruppe', 'modus', 'anzahl_pools', 'aufsteiger_pro_pool', 'finale_art', 'finalisten', 'wertung', 'wertung_finale', 'kampfrichter', 'dojo_trennung', 'gleiche_flaeche', 'optionen', 'status', 'zusammengelegt_in', 'sortierung', 'min_starter', 'tisch_abschluss_am', 'tisch_abschluss_von', 'ausruf_am', 'ausruf_freigabe_am'],
        'klasse_regel' => ['id', 'klasse_id', 'ab_runde', 'kumite_form', 'erlaubte_kata', 'wertung', 'uuid'],
        'start' => ['id', 'klasse_id', 'teilnahme_id', 'team_id', 'herkunft_klasse_id', 'status', 'manuell', 'stichentscheid', 'uuid'],
        'zusammenlegung' => ['id', 'ziel_klasse_id', 'quell_klasse_id', 'grund', 'zustimmung', 'erstellt_am', 'erstellt_von', 'aufgehoben_am', 'uuid'],
        'auslosung' => ['id', 'klasse_id', 'seed', 'algorithmus', 'optionen', 'protokoll', 'aktiv', 'erstellt_am', 'erstellt_von', 'uuid'],
        'phase' => ['id', 'klasse_id', 'typ', 'nummer', 'bezeichnung', 'wertung', 'aufsteiger', 'tatami_id', 'reihenfolge', 'status', 'baum_groesse', 'starter', 'ausruf_am', 'ausruf_freigabe_am', 'uuid'],
        'begegnung' => ['id', 'phase_id', 'runde', 'position', 'art', 'aka_start_id', 'shiro_start_id', 'aka_quelle', 'shiro_quelle', 'sieger_start_id', 'entscheidung', 'kata_aka', 'kata_shiro', 'wertung', 'status', 'version', 'beendet_am', 'erfasst_von', 'wertungssystem', 'kumite_form', 'kata_liste', 'aufruf_aka', 'aufruf_shiro', 'aufruf_am', 'unterbrochen_am', 'uuid'],
        'auftritt' => ['id', 'phase_id', 'start_id', 'reihenfolge', 'kata', 'wertung', 'gesamt', 'nebenwertung', 'stichentscheid', 'status', 'version', 'beendet_am', 'erfasst_von', 'kata_liste', 'aufruf_starter', 'aufruf_am', 'uuid'],
        'platzierung' => ['id', 'klasse_id', 'platz', 'start_id', 'bestaetigt_am', 'bestaetigt_von', 'uuid'],
        'audit_log' => ['id', 'zeit', 'benutzer', 'turnier_id', 'entitaet', 'entitaet_id', 'aktion', 'daten', 'uuid'],
        'tatami_nachricht' => ['id', 'turnier_id', 'tatami_id', 'text', 'von', 'erstellt_am', 'bestaetigt_am', 'bestaetigt_von', 'uuid'],
        'korrekturantrag' => ['id', 'turnier_id', 'verein_id', 'teilnahme_id', 'art', 'neu', 'begruendung', 'status', 'antwort', 'erstellt_am', 'erstellt_von', 'bearbeitet_am', 'bearbeitet_von', 'uuid'],
    ];

    /**
     * Hallenstand (Halle → Online, Schritt 9e): ohne Bereiche, die online führend sind oder nur groß wären
     * (Online-Anmeldungen, Logos – die kommen mit der Rückgabe), und ohne Geburtsdaten, Ausweisnummern und PINs
     * (Konzept Kap. 4.5). Der Empfang (9f) überschreibt fehlende Felder nicht.
     */
    public const HALLENSTAND_OHNE_BEREICHE = ['anmeldung', 'turnier_logo'];
    public const HALLENSTAND_OHNE_SPALTEN = [
        'turnier' => ['leitung_pin_hash'], 'tatami' => ['pin_hash'], 'athlet' => ['geburtsdatum', 'ausweis_nr'],
    ];

    /** Zugangsdaten, die aus nicht vertrauenswürdigen Paketen nicht übernommen werden. */
    public const PINS = ['turnier' => ['leitung_pin_hash'], 'tatami' => ['pin_hash']];

    /**
     * Prüft Kopf, Tabellen und Spalten gegen SPALTEN; wirft bei allem Unbekannten.
     *
     * @param array<mixed> $paket
     */
    public static function pruefen(array $paket): void
    {
        if (($paket['format'] ?? null) !== self::FORMAT || !is_array($paket['turnier'] ?? null)) {
            throw new FachFehler('Die Datei ist keine Turnier-Sicherung dieser Software.');
        }
        if (!is_int($paket['version'] ?? null) || $paket['version'] < 1) {
            throw new FachFehler('Die Sicherung hat keine gültige Versionsangabe.');
        }
        if ($paket['version'] > self::VERSION) {
            throw new FachFehler('Die Sicherung stammt aus einer neueren Programmversion. Bitte zuerst aktualisieren.');
        }
        foreach ($paket as $schluessel => $inhalt) {
            if (in_array($schluessel, self::KOPF, true)) {
                continue;
            }
            if (!isset(self::SPALTEN[$schluessel])) {
                throw new FachFehler('Die Sicherung enthält einen unbekannten Bereich: ' . self::kurz((string) $schluessel) . '.');
            }
            $zeilen = $schluessel === 'turnier' ? [$inhalt] : $inhalt;
            if (!is_array($zeilen) || !array_is_list($zeilen)) {
                throw new FachFehler("Die Sicherung ist im Bereich $schluessel beschädigt.");
            }
            foreach ($zeilen as $zeile) {
                if (!is_array($zeile) || !isset($zeile['id'])) {
                    throw new FachFehler("Die Sicherung ist im Bereich $schluessel beschädigt.");
                }
                foreach ($zeile as $spalte => $wert) {
                    if (!in_array($spalte, self::SPALTEN[$schluessel], true)) {
                        throw new FachFehler("Die Sicherung enthält eine unbekannte Spalte $schluessel." . self::kurz((string) $spalte) . '.');
                    }
                    if (!is_scalar($wert) && $wert !== null) {
                        throw new FachFehler("Die Sicherung enthält einen ungültigen Wert in $schluessel.$spalte.");
                    }
                }
            }
        }
    }

    /**
     * Prüfsumme anhängen.
     *
     * @param array<string, mixed> $paket
     * @return array<string, mixed>
     */
    public static function signieren(array $paket, Instanz $instanz): array
    {
        unset($paket['pruefsumme']);
        $paket['version'] = self::VERSION;
        $paket['pruefsumme'] = [
            'verfahren' => 'HMAC-SHA256',
            'instanz' => $instanz->id(),
            'wert' => self::pruefsummeBerechnen($paket, $instanz->schluessel()),
        ];
        return $paket;
    }

    /**
     * Herkunft eines Pakets: EIGENE (Prüfsumme dieser Installation gültig), FREMD (andere Installation, nicht prüfbar)
     * oder OHNE (keine Prüfsumme, z. B. ältere Sicherung). Eine ungültige Prüfsumme dieser Installation bedeutet:
     * Datei wurde verändert – Abbruch.
     *
     * @param array<string, mixed> $paket
     */
    public static function herkunft(array $paket, ?Instanz $instanz): string
    {
        $summe = $paket['pruefsumme'] ?? null;
        if ($summe === null) {
            return self::OHNE;
        }
        if (!is_array($summe) || ($summe['verfahren'] ?? null) !== 'HMAC-SHA256' || !is_string($summe['wert'] ?? null) || !is_string($summe['instanz'] ?? null)) {
            throw new FachFehler('Die Prüfsumme der Sicherung ist beschädigt.');
        }
        if ($instanz === null || !hash_equals($instanz->id(), $summe['instanz'])) {
            return self::FREMD;
        }
        if (!hash_equals(self::pruefsummeBerechnen($paket, $instanz->schluessel()), $summe['wert'])) {
            throw new FachFehler('Die Sicherung wurde nach dem Export verändert (Prüfsumme stimmt nicht) und wird nicht importiert.');
        }
        return self::EIGENE;
    }

    /**
     * Hallenstand aus einer Sicherung (ohne Kopf und Prüfsumme).
     *
     * @param array<string, mixed> $sicherung
     * @return array<string, mixed>
     */
    public static function hallenstand(array $sicherung): array
    {
        $stand = array_diff_key($sicherung, array_flip([...self::KOPF, ...self::HALLENSTAND_OHNE_BEREICHE]));
        foreach (self::HALLENSTAND_OHNE_SPALTEN as $tabelle => $spalten) {
            $weg = array_flip($spalten);
            $stand[$tabelle] = $tabelle === 'turnier'
                ? array_diff_key($stand[$tabelle], $weg)
                : array_map(static fn(array $z) => array_diff_key($z, $weg), $stand[$tabelle] ?? []);
        }
        return $stand;
    }

    /**
     * HMAC-SHA256 über den kanonischen Inhalt (ohne die Prüfsumme selbst).
     *
     * @param array<string, mixed> $paket
     */
    public static function pruefsummeBerechnen(array $paket, string $schluessel): string
    {
        return hash_hmac('sha256', self::inhalt($paket), $schluessel);
    }

    /**
     * PINs entfernen (nicht vertrauenswürdiges Paket).
     *
     * @param array<string, mixed> $paket
     * @return array<string, mixed>
     */
    public static function ohnePins(array $paket): array
    {
        foreach (self::PINS as $tabelle => $spalten) {
            foreach ($spalten as $spalte) {
                if ($tabelle === 'turnier') {
                    $paket['turnier'][$spalte] = null;
                    continue;
                }
                foreach ($paket[$tabelle] ?? [] as $i => $zeile) {
                    $paket[$tabelle][$i][$spalte] = null;
                }
            }
        }
        return $paket;
    }

    /**
     * Paket als Datei (lesbar eingerückt).
     *
     * @param array<string, mixed> $paket
     */
    public static function kodieren(array $paket): string
    {
        return json_encode($paket, self::JSON | JSON_PRETTY_PRINT);
    }

    /**
     * Kanonischer Inhalt für die Prüfsumme: alles außer der Prüfsumme selbst. Kodiert wie die Datei, dann wieder
     * gelesen – so rechnen Export und Import über exakt dieselben Werte (z. B. ersetzte Zeichen).
     *
     * @param array<string, mixed> $paket
     */
    private static function inhalt(array $paket): string
    {
        unset($paket['pruefsumme']);
        return json_encode(json_decode(json_encode($paket, self::JSON), true, 512, JSON_THROW_ON_ERROR), self::JSON);
    }

    private static function kurz(string $text): string
    {
        return preg_replace('/[^a-z0-9_]/i', '?', mb_substr($text, 0, 40)) ?? '';
    }
}
