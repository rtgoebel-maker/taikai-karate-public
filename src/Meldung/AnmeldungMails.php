<?php

declare(strict_types=1);

namespace Wettkampf\Meldung;

use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Girocode;
use Wettkampf\Kern\Mail\Postausgang;

/**
 * E-Mails zum Bearbeitungsstand einer Online-Anmeldung an die angegebene Adresse: eingegangen (mit Bearbeitungslink
 * und Zahlungsaufforderung), neue Zahlungsaufforderung (geänderter Betrag), Zahlung eingegangen, freigegeben, abgelehnt.
 * Reiner Text; eingereiht in den Postausgang, verschickt nach der Antwort.
 */
final class AnmeldungMails
{
    public function __construct(private readonly Database $db, private readonly Postausgang $post) {}

    /**
     * @param array<string, mixed> $turnier
     * @param array<string, mixed> $a
     */
    public function eingegangen(array $turnier, array $a): void
    {
        $this->senden(
            $turnier,
            $a,
            t('Anmeldung eingegangen'),
            'anmeldung_eingegangen',
            t('vielen Dank – Ihre Anmeldung ist eingegangen.') . "\n\n" . $this->zusammenfassung($a) . $this->zahlung($turnier, $a)
            . (!empty($turnier['meldeschluss'])
                ? t('Über Ihren persönlichen Bearbeitungslink sehen Sie jederzeit den Bearbeitungsstand und können die Anmeldung bis zur Freigabe bzw. bis zum Meldeschluss am {datum} ändern. Bitte nicht weitergeben:', ['datum' => self::datum((string) $turnier['meldeschluss'])])
                : t('Über Ihren persönlichen Bearbeitungslink sehen Sie jederzeit den Bearbeitungsstand und können die Anmeldung bis zur Freigabe ändern. Bitte nicht weitergeben:'))
            . "\n" . $this->post->link("anmeldung/{$turnier['id']}/{$a['code']}") . "\n",
        );
    }

    /**
     * @param array<string, mixed> $turnier
     * @param array<string, mixed> $a
     */
    public function neueAufforderung(array $turnier, array $a): void
    {
        $this->senden(
            $turnier,
            $a,
            t('Neue Zahlungsaufforderung (Version {version})', ['version' => (int) $a['aufforderung_version']]),
            'anmeldung_aufforderung',
            t('Ihre Anmeldung wurde geändert, dadurch hat sich das Startgeld geändert. Maßgeblich ist ab jetzt diese Zahlungsaufforderung (Version {version}); bereits Gezahltes ist verrechnet.', ['version' => (int) $a['aufforderung_version']])
            . "\n\n" . $this->zusammenfassung($a) . $this->zahlung($turnier, $a)
            . t('Bearbeitungsstand und Übersicht:') . ' ' . $this->post->link("anmeldung/{$turnier['id']}/{$a['code']}/uebersicht") . "\n",
        );
    }

    /**
     * @param array<string, mixed> $turnier
     * @param array<string, mixed> $a
     */
    public function zahlungEingegangen(array $turnier, array $a): void
    {
        $stand = (new AnmeldungService($this->db))->stand($a, $turnier);
        $text = $stand['bezahlt_voll']
            ? t('Ihre Zahlung von {betrag} ist eingegangen – vielen Dank.', ['betrag' => AnmeldungService::euro($stand['bezahlt'])]) . "\n"
                . ($stand['bezahlt'] > $stand['betrag'] ? t('Das Guthaben von {betrag} wird erstattet bzw. verrechnet.', ['betrag' => AnmeldungService::euro($stand['bezahlt'] - $stand['betrag'])]) . "\n" : '')
                . t('Die Übersicht gilt als Anmelde- und Zahlungsbestätigung.') . "\n"
            : t('Wir haben {bezahlt} erhalten. Noch offen: {offen}.', ['bezahlt' => AnmeldungService::euro($stand['bezahlt']), 'offen' => AnmeldungService::euro($stand['offen'])]) . "\n" . $this->zahlung($turnier, $a);
        $this->senden(
            $turnier,
            $a,
            $stand['bezahlt_voll'] ? t('Zahlung eingegangen') : t('Teilzahlung eingegangen'),
            'anmeldung_zahlung',
            $text . "\n" . t('Bearbeitungsstand und Übersicht:') . ' ' . $this->post->link("anmeldung/{$turnier['id']}/{$a['code']}/uebersicht") . "\n",
        );
    }

    /**
     * @param array<string, mixed> $turnier
     * @param array<string, mixed> $a
     */
    public function freigegeben(array $turnier, array $a): void
    {
        $this->senden(
            $turnier,
            $a,
            t('Anmeldung freigegeben'),
            'anmeldung_freigegeben',
            t('Ihre Anmeldung wurde von der Wettkampfleitung freigegeben: Die Teilnehmer und Teams sind für das Turnier gemeldet und den Klassen zugeordnet.') . "\n\n"
            . t('Übersicht:') . ' ' . $this->post->link("anmeldung/{$turnier['id']}/{$a['code']}/uebersicht") . "\n"
            . t('Änderungen ab jetzt bitte direkt bei der Wettkampfleitung.') . "\n",
        );
    }

    /**
     * @param array<string, mixed> $turnier
     * @param array<string, mixed> $a
     */
    public function abgelehnt(array $turnier, array $a): void
    {
        $this->senden(
            $turnier,
            $a,
            t('Anmeldung abgelehnt'),
            'anmeldung_abgelehnt',
            t('Ihre Anmeldung wurde von der Wettkampfleitung abgelehnt.')
            . ($a['grund'] ? "\n" . t('Grund: {grund}', ['grund' => $a['grund']]) : '') . "\n\n" . t('Bei Fragen wenden Sie sich bitte an die Wettkampfleitung.') . "\n",
        );
    }

    /**
     * @param array<string, mixed> $turnier
     * @param array<string, mixed> $a
     */
    private function senden(array $turnier, array $a, string $anlass, string $art, string $inhalt): void
    {
        $datum = self::datum((string) $turnier['datum']);
        $text = t('Guten Tag {name},', ['name' => $a['ansprechpartner']]) . "\n\n" . $inhalt
            . "\n" . ($turnier['ort']
                ? t('Turnier: {name} am {datum} in {ort}', ['name' => $turnier['name'], 'datum' => $datum, 'ort' => $turnier['ort']])
                : t('Turnier: {name} am {datum}', ['name' => $turnier['name'], 'datum' => $datum])) . "\n"
            . t('Dojo: {dojo} · Anmelde-Nr. {nr}', ['dojo' => $a['dojo'], 'nr' => $a['id']]) . "\n"
            . "\n-- \n" . t('Diese Nachricht wurde automatisch von Taikai – Karate (Turniersoftware) verschickt. Sie erhalten sie, weil diese Adresse bei der Online-Anmeldung angegeben wurde.');
        $this->post->einreihen((string) $a['email'], "$anlass – {$turnier['name']}", $text, $art, (int) $turnier['id']);
    }

    /** Datum in der Sprache der Mail (14.03.2026 bzw. 14 Mar 2026). */
    private static function datum(string $zeit): string
    {
        return \Wettkampf\Kern\Sprache::datum(date('Y-m-d', (int) strtotime($zeit)));
    }

    /** @param array<string, mixed> $a */
    private function zusammenfassung(array $a): string
    {
        $daten = json_decode((string) $a['daten'], true) ?: ['teilnehmer' => [], 'teams' => []];
        $werte = ['n' => count($daten['teilnehmer']), 'teams' => count($daten['teams']), 'betrag' => AnmeldungService::euro((int) $a['aufforderung_cent'])];
        return (count($daten['teams']) === 1
            ? t('Gemeldet: {n} Teilnehmer, {teams} Team · Startgeld {betrag}', $werte)
            : t('Gemeldet: {n} Teilnehmer, {teams} Teams · Startgeld {betrag}', $werte)) . "\n\n";
    }

    /**
     * Zahlungsdaten (nur wenn noch etwas offen ist).
     *
     * @param array<string, mixed> $turnier
     * @param array<string, mixed> $a
     */
    private function zahlung(array $turnier, array $a): string
    {
        $stand = (new AnmeldungService($this->db))->stand($a, $turnier);
        if ($stand['offen'] <= 0) {
            return '';
        }
        $rechnung = new RechnungService($this->db);
        $z = $rechnung->einstellungen($turnier);
        $frist = date('Y-m-d', (int) strtotime(($a['aufforderung_am'] ?: date('Y-m-d')) . ' +' . $z['zahlungsziel'] . ' days'));
        if (!empty($turnier['meldeschluss']) && $turnier['meldeschluss'] < $frist && $turnier['meldeschluss'] >= date('Y-m-d')) {
            $frist = (string) $turnier['meldeschluss'];
        }
        $zeilen = [
            (int) $a['aufforderung_version'] > 1 ? t('ZAHLUNGSAUFFORDERUNG (Version {version})', ['version' => (int) $a['aufforderung_version']]) : t('ZAHLUNGSAUFFORDERUNG'),
            t('Zu überweisen:     {betrag}', ['betrag' => AnmeldungService::euro($stand['offen'])]) . ($stand['bezahlt'] > 0 ? t(' (bereits erhalten: {betrag})', ['betrag' => AnmeldungService::euro($stand['bezahlt'])]) : ''),
            t('Zahlbar bis:       {datum}', ['datum' => self::datum($frist)]),
            t('Verwendungszweck:  {zweck}', ['zweck' => $rechnung->nummerAnmeldung($turnier, (int) $a['id']) . ' ' . $a['dojo']]),
        ];
        if ($z['absender'] !== '') {
            $zeilen[] = t('Empfänger:         {empfaenger}', ['empfaenger' => implode(', ', array_filter(array_map('trim', explode("\n", $z['absender']))))]);
        }
        if ($z['bank'] !== '') {
            $zeilen[] = t('Bankverbindung:    {bank}', ['bank' => implode(' · ', array_filter(array_map('trim', explode("\n", $z['bank']))))]);
        }
        if (Girocode::nutzdaten($z['absender'], $z['bank'], $stand['offen'], '') !== null) {
            $zeilen[] = '';
            $zeilen[] = t('Bequem per Banking-App: Die Übersicht zur Anmeldung (Bearbeitungslink) zeigt einen QR-Code zum Scannen (GiroCode).');
        }
        $hinweis = (new AnmeldungService($this->db))->einstellungen($turnier)['hinweis'];
        return implode("\n", $zeilen) . "\n" . ($hinweis !== '' ? "\n$hinweis\n" : '') . "\n";
    }
}
