<?php

declare(strict_types=1);

namespace Wettkampf\Datenschutz;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Http\View;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Meldung\RechnungService;

/**
 * Buchhaltungsexport eines Turniers als ZIP – zum Archivieren außerhalb der Software (die Plattform ist kein Archiv):
 *
 * - zahlungen.csv: je Online-Anmeldung Nummer (= Verwendungszweck), Dojo, Ansprechpartner, Betrag, Zahlungseingang
 * - belege/*.pdf: Rechnung bzw. Zahlungsbeleg je Anmeldung; Rechnungen je Dojo, wenn die Rechnungsseite eingerichtet ist –
 *   jeweils nur mit den Posten (Anzahl × Startgeld), ohne Starterliste (Datensparsamkeit)
 * - mails/: die versendeten E-Mails des Turniers (Bestätigungen, Zahlungsaufforderungen) mit Anhängen
 * - verlauf-zahlungen.csv: Änderungsverlauf der Anmeldungen (eingereicht, geändert, Zahlung erfasst …)
 * - LIESMICH.txt und pruefsummen.sha256 (SHA-256 je Datei, Nachweis der Unverändertheit)
 *
 * Belege entstehen aus dem aktuellen Stand – deshalb vor der Pseudonymisierung exportieren (wird vermerkt).
 * Dateinamen im ZIP bleiben immer gleich; Inhalte (Spaltenköpfe, LIESMICH, Vermerk) in der Sprache der auslösenden Person.
 */
final class Buchhaltungsexport
{
    /** CSV: Semikolon, UTF-8 mit BOM (Excel), Dezimalkomma. */
    private const BOM = "\xEF\xBB\xBF";

    public function __construct(private readonly Database $db, private readonly View $view) {}

    /**
     * Was der Export enthalten wird (Anzeige vor dem Download): Belege zu Online-Anmeldungen, Rechnungen je Dojo
     * (null = fehlen, weil unter Rechnungen kein Absender eingetragen ist), versendete E-Mails.
     *
     * @return array{anmeldungen: int, belege: int, dojos: int, dojoRechnungen: int|null, mails: int, pdf: bool}
     */
    public function inhalt(int $turnierId): array
    {
        $turnier = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$turnierId]) ?? [];
        $rechnungen = new RechnungService($this->db);
        $dojos = count($rechnungen->rechnungen($turnierId));
        return [
            'anmeldungen' => (int) $this->db->wert('SELECT COUNT(*) FROM anmeldung WHERE turnier_id = ?', [$turnierId]),
            'belege' => (int) $this->db->wert("SELECT COUNT(*) FROM anmeldung WHERE turnier_id = ? AND status <> 'abgelehnt' AND aufforderung_cent > 0", [$turnierId]),
            'dojos' => $dojos,
            'dojoRechnungen' => $rechnungen->einstellungen($turnier)['absender'] !== '' ? $dojos : null,
            'mails' => (int) $this->db->wert('SELECT COUNT(*) FROM mail_ausgang WHERE turnier_id = ? AND gesendet_am IS NOT NULL', [$turnierId]),
            'pdf' => PdfBeleg::verfuegbar(),
        ];
    }

    /**
     * ZIP erzeugen; liefert den Pfad einer temporären Datei (der Aufrufer löscht sie nach dem Senden).
     */
    public function erstellen(int $turnierId): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new FachFehler(t('ZIP-Export ist auf diesem Server nicht verfügbar (PHP-Erweiterung zip).'));
        }
        $turnier = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$turnierId]) ?? throw new FachFehler(t('Turnier nicht gefunden.'));
        if (DatenschutzEinstellungen::lesen($turnier)['pseudonymisiert_am'] !== null) {
            throw new FachFehler(t('Das Turnier ist bereits pseudonymisiert – der Buchhaltungsexport muss vorher erstellt werden. Bitte die damals exportierte Datei verwenden.'));
        }
        $jetzt = date('Y-m-d H:i:s');
        $vermerk = t('Archivkopie aus Taikai – Karate, erstellt am {zeit} · {turnier}', ['zeit' => self::zeit($jetzt), 'turnier' => $turnier['name']]);
        $dateien = [];

        $rechnungen = new RechnungService($this->db);
        $anmeldungen = $this->db->alle('SELECT * FROM anmeldung WHERE turnier_id = ? ORDER BY id', [$turnierId]);
        $zeilen = [[t('Nummer'), t('Dojo'), t('Ansprechpartner'), t('E-Mail'), t('Status'), t('Eingereicht am'), t('Zahlungsaufforderung Version'), t('Zahlungsaufforderung vom'),
            t('Betrag (EUR)'), t('Bezahlt (EUR)'), t('Bezahlt am'), t('Offen (EUR)'), t('Notiz zur Zahlung'), t('Beleg')]];
        foreach ($anmeldungen as $a) {
            $nummer = $rechnungen->nummerAnmeldung($turnier, (int) $a['id']);
            $beleg = '';
            if ($a['status'] !== 'abgelehnt' && (int) $a['aufforderung_cent'] > 0 && PdfBeleg::verfuegbar()) {
                $beleg = 'belege/anmeldung-' . self::dateiname($nummer) . '.pdf';
                // ohne Starterliste: Für die Buchhaltung genügen die Posten (Anzahl × Startgeld) – Namen von Kindern gehören nicht ins Archiv
                $dateien[$beleg] = PdfBeleg::erzeugen($this->view->render('datenschutz/beleg_pdf', [
                    'blatt' => ['teilnehmer' => [], 'teams' => []] + $rechnungen->anmeldungRechnung($turnier, $a), 'vermerk' => $vermerk,
                ]));
            }
            $zeilen[] = [$nummer, $a['dojo'], $a['ansprechpartner'], $a['email'], $a['status'], $a['eingereicht_am'], $a['aufforderung_version'],
                $a['aufforderung_am'], self::betrag((int) $a['aufforderung_cent']), self::betrag((int) $a['bezahlt_cent']), $a['bezahlt_am'],
                self::betrag(max(0, (int) $a['aufforderung_cent'] - (int) $a['bezahlt_cent'])), $a['zahlung_notiz'], $beleg];
        }
        $dateien['zahlungen.csv'] = self::csv($zeilen);

        // Rechnungen je Dojo nur, wenn die Leitung die Rechnungsseite eingerichtet hat (Absender eingetragen)
        if ($rechnungen->einstellungen($turnier)['absender'] !== '') {
            $e = $rechnungen->einstellungen($turnier);
            $q = $e['quittung']; // gemeinnütziger Ausrichter: Quittungen (Kopie ohne Unterschrift)
            $zeilen = [[$q ? t('Quittungs-Nr.') : t('Rechnungs-Nr.'), t('Dojo'), $q ? t('Datum') : t('Rechnungsdatum'), $q ? t('Bezahlt am') : t('Fällig'), t('Betrag (EUR)'), t('Beleg')]];
            foreach ($rechnungen->rechnungen($turnierId) as $r) {
                $beleg = '';
                if (PdfBeleg::verfuegbar()) {
                    $beleg = 'belege/dojo-' . ($q ? 'quittung' : 'rechnung') . '-' . self::dateiname((string) $r['nummer']) . '.pdf';
                    $dateien[$beleg] = PdfBeleg::erzeugen($this->view->render('datenschutz/beleg_pdf', ['vermerk' => $vermerk . ($q ? ' · ' . t('Kopie ohne Unterschrift') : ''), 'blatt' => [
                        'turnier' => $turnier, 'einstellungen' => $e,
                        'empfaenger' => ['verein' => $r['verein'], 'ansprechpartner' => $r['ansprechpartner'], 'email' => $r['email']],
                        'nummer' => $r['nummer'], 'datum' => $r['datum'], 'faellig' => $r['bezahlt'] !== null ? null : $r['faellig'], 'bezahlt' => $r['bezahlt'],
                        'preis' => $r['startgeld'], 'teilnehmer' => [], 'teams' => [],
                    ]]));
                }
                $zeilen[] = [$r['nummer'], $r['verein'], $r['datum'], $q ? (string) ($r['bezahlt']['am'] ?? '') : $r['faellig'], self::betrag((int) $r['startgeld']['summe']), $beleg];
            }
            $dateien[$q ? 'quittungen-je-dojo.csv' : 'rechnungen-je-dojo.csv'] = self::csv($zeilen);
        }

        // Versendete E-Mails (Geschäftsbriefe) mit Anhängen
        foreach ($this->db->alle('SELECT * FROM mail_ausgang WHERE turnier_id = ? AND gesendet_am IS NOT NULL ORDER BY id', [$turnierId]) as $m) {
            $basis = 'mails/' . substr(str_replace([' ', ':', '-'], '', (string) $m['gesendet_am']), 0, 14) . '-' . (int) $m['id'] . '-' . self::dateiname((string) $m['anlass']);
            $dateien[$basis . '.txt'] = t('An') . ": {$m['empfaenger']}\n" . t('Betreff') . ": {$m['betreff']}\n" . t('Erstellt') . ": {$m['erstellt_am']}\n"
                . t('Gesendet') . ": {$m['gesendet_am']}\n\n{$m['text']}\n";
            foreach (json_decode((string) ($m['anhaenge'] ?? ''), true) ?: [] as $i => $anhang) {
                if (is_array($anhang) && isset($anhang['name'], $anhang['daten'])) {
                    $dateien[$basis . '-anhang' . ($i + 1) . '-' . self::dateiname((string) $anhang['name'])] = (string) base64_decode((string) $anhang['daten'], true);
                }
            }
        }

        $zeilen = [[t('Zeit'), t('Anmeldung'), t('Aktion'), t('Angaben'), t('Durch')]];
        foreach ($this->db->alle("SELECT * FROM audit_log WHERE turnier_id = ? AND entitaet = 'anmeldung' ORDER BY id", [$turnierId]) as $p) {
            $zeilen[] = [$p['zeit'], $p['entitaet_id'], $p['aktion'], $p['daten'], $p['benutzer']];
        }
        $dateien['verlauf-zahlungen.csv'] = self::csv($zeilen);

        $dateien['LIESMICH.txt'] = $this->liesmich($turnier, $jetzt, count($anmeldungen), $this->inhalt($turnierId));
        $summen = '';
        foreach ($dateien as $name => $inhalt) {
            $summen .= hash('sha256', $inhalt) . '  ' . $name . "\n";
        }
        $dateien['pruefsummen.sha256'] = $summen;

        $datei = (string) tempnam(sys_get_temp_dir(), 'bh');
        $zip = new \ZipArchive();
        if ($zip->open($datei, \ZipArchive::OVERWRITE) !== true) {
            throw new FachFehler(t('Die ZIP-Datei konnte nicht angelegt werden.'));
        }
        foreach ($dateien as $name => $inhalt) {
            $zip->addFromString($name, $inhalt);
        }
        $zip->close();

        (new DatenschutzEinstellungen($this->db))->vermerken($turnierId, 'exportiert_am', $jetzt);
        (new Protokoll($this->db))->eintragen($turnierId, 'turnier', $turnierId, 'buchhaltung_exportiert', ['anmeldungen' => count($anmeldungen), 'dateien' => count($dateien)]);
        return $datei;
    }

    /**
     * Dateiname des Downloads.
     *
     * @param array<string, mixed> $turnier
     */
    public static function downloadName(array $turnier): string
    {
        return 'buchhaltung-' . self::dateiname((string) $turnier['name']) . '-' . $turnier['datum'] . '-' . date('Ymd-His') . '.zip';
    }

    /**
     * @param array<string, mixed> $turnier
     * @param array{anmeldungen: int, belege: int, dojos: int, dojoRechnungen: int|null, mails: int, pdf: bool} $inhalt
     */
    private function liesmich(array $turnier, string $jetzt, int $anmeldungen, array $inhalt): string
    {
        $fehlt = [];
        if (!$inhalt['pdf']) {
            $fehlt[] = t('Hinweis: Auf dem Server fehlt die PDF-Erzeugung – es sind keine Belege als PDF enthalten.');
        }
        if ($inhalt['dojoRechnungen'] === null && $inhalt['dojos'] > 0) {
            $fehlt[] = t('Hinweis: Rechnungen je Dojo ({n}) fehlen, weil unter „Rechnungen“ kein Absender eingetragen war.', ['n' => $inhalt['dojos']]);
        }
        return implode("\n", [
            ...$fehlt,
            ...($fehlt === [] ? [] : ['']),
            t('Buchhaltungsexport – {turnier} ({datum})', ['turnier' => $turnier['name'], 'datum' => $turnier['datum']]),
            t('Erstellt am {zeit} mit Taikai – Karate.', ['zeit' => self::zeit($jetzt)]),
            '',
            t('Inhalt'),
            t('- zahlungen.csv: {anzahl} Online-Anmeldungen mit Zahlungsaufforderung (Nummer = Verwendungszweck), Zahlungseingang und offenem Betrag', ['anzahl' => $anmeldungen]),
            t('- belege/: Rechnung bzw. Zahlungsbeleg je Anmeldung als PDF (Posten, ohne Starterliste); ggf. Rechnungen je Dojo (rechnungen-je-dojo.csv)'),
            t('- mails/: versendete E-Mails des Turniers (Anmeldebestätigungen, Zahlungsaufforderungen) mit Anhängen'),
            t('- verlauf-zahlungen.csv: Änderungsverlauf der Anmeldungen (eingereicht, geändert, Zahlung erfasst, freigegeben …)'),
            t('- pruefsummen.sha256: SHA-256 je Datei (prüfen z. B. mit: sha256sum -c pruefsummen.sha256)'),
            '',
            t('Aufbewahrung'),
            t('Diese Datei unverändert außerhalb von Taikai – Karate archivieren (z. B. beim Kassenwart bzw. in der Vereinsbuchhaltung).'),
            t('Die Software ist kein Archiv: Nach Ablauf der Löschfrist werden die Personenangaben dort pseudonymisiert.'),
            t('Fristen nach § 147 AO / § 257 HGB (Stand 2025): Buchungsbelege wie Rechnungen 8 Jahre, Geschäftsbriefe 6 Jahre,'),
            t('jeweils ab Ende des Kalenderjahres. Für gemeinnützige Vereine bitte mit Kassenprüfung bzw. Steuerberatung abstimmen.'),
            t('Die Datei enthält personenbezogene Daten (meldende Personen und E-Mails an sie): nur für Berechtigte zugänglich ablegen'),
            t('und nach Ablauf der Frist löschen.'),
            '',
        ]);
    }

    /** Zeitpunkt (JJJJ-MM-TT HH:MM:SS) als Datum in der aktuellen Sprache mit Uhrzeit, z. B. 14.03.2026 10:15. */
    private static function zeit(string $iso): string
    {
        $ts = (int) strtotime($iso);
        return datum_de(date('Y-m-d', $ts)) . date(' H:i', $ts);
    }

    /** @param list<list<mixed>> $zeilen */
    private static function csv(array $zeilen): string
    {
        $aus = self::BOM;
        foreach ($zeilen as $zeile) {
            $aus .= implode(';', array_map(static function (mixed $wert): string {
                $text = (string) ($wert ?? '');
                // CSV-Injection (OWASP): Zellen, die Excel als Formel lesen würde, mit ' entschärfen
                if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) && !preg_match('/^-?\d+(,\d+)?$/', $text)) {
                    $text = "'" . $text;
                }
                return '"' . str_replace('"', '""', $text) . '"';
            }, $zeile)) . "\r\n";
        }
        return $aus;
    }

    private static function betrag(int $cent): string
    {
        return number_format($cent / 100, 2, ',', '');
    }

    private static function dateiname(string $text): string
    {
        $text = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']));
        return trim(substr($text, 0, 60), '-.') ?: 'datei';
    }
}
