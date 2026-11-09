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
            'Anmeldung eingegangen',
            'anmeldung_eingegangen',
            "vielen Dank – Ihre Anmeldung ist eingegangen.\n\n" . $this->zusammenfassung($a) . $this->zahlung($turnier, $a)
            . "Über Ihren persönlichen Bearbeitungslink sehen Sie jederzeit den Bearbeitungsstand und können die Anmeldung bis zur Freigabe"
            . (!empty($turnier['meldeschluss']) ? ' bzw. bis zum Meldeschluss am ' . date('d.m.Y', (int) strtotime($turnier['meldeschluss'])) : '')
            . " ändern. Bitte nicht weitergeben:\n" . $this->post->link("anmeldung/{$turnier['id']}/{$a['code']}") . "\n",
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
            'Neue Zahlungsaufforderung (Version ' . (int) $a['aufforderung_version'] . ')',
            'anmeldung_aufforderung',
            "Ihre Anmeldung wurde geändert, dadurch hat sich das Startgeld geändert. Maßgeblich ist ab jetzt diese Zahlungsaufforderung "
            . "(Version " . (int) $a['aufforderung_version'] . "); bereits Gezahltes ist verrechnet.\n\n" . $this->zusammenfassung($a) . $this->zahlung($turnier, $a)
            . "Bearbeitungsstand und Übersicht: " . $this->post->link("anmeldung/{$turnier['id']}/{$a['code']}/uebersicht") . "\n",
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
            ? 'Ihre Zahlung von ' . AnmeldungService::euro($stand['bezahlt']) . " ist eingegangen – vielen Dank.\n"
                . ($stand['bezahlt'] > $stand['betrag'] ? 'Das Guthaben von ' . AnmeldungService::euro($stand['bezahlt'] - $stand['betrag']) . " wird erstattet bzw. verrechnet.\n" : '')
                . "Die Übersicht gilt als Anmelde- und Zahlungsbestätigung.\n"
            : 'Wir haben ' . AnmeldungService::euro($stand['bezahlt']) . ' erhalten. Noch offen: ' . AnmeldungService::euro($stand['offen']) . ".\n" . $this->zahlung($turnier, $a);
        $this->senden(
            $turnier,
            $a,
            $stand['bezahlt_voll'] ? 'Zahlung eingegangen' : 'Teilzahlung eingegangen',
            'anmeldung_zahlung',
            $text . "\nBearbeitungsstand und Übersicht: " . $this->post->link("anmeldung/{$turnier['id']}/{$a['code']}/uebersicht") . "\n",
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
            'Anmeldung freigegeben',
            'anmeldung_freigegeben',
            "Ihre Anmeldung wurde von der Wettkampfleitung freigegeben: Die Teilnehmer und Teams sind für das Turnier gemeldet und den Klassen zugeordnet.\n\n"
            . "Übersicht: " . $this->post->link("anmeldung/{$turnier['id']}/{$a['code']}/uebersicht") . "\n"
            . "Änderungen ab jetzt bitte direkt bei der Wettkampfleitung.\n",
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
            'Anmeldung abgelehnt',
            'anmeldung_abgelehnt',
            "Ihre Anmeldung wurde von der Wettkampfleitung abgelehnt."
            . ($a['grund'] ? "\nGrund: " . $a['grund'] : '') . "\n\nBei Fragen wenden Sie sich bitte an die Wettkampfleitung.\n",
        );
    }

    /**
     * @param array<string, mixed> $turnier
     * @param array<string, mixed> $a
     */
    private function senden(array $turnier, array $a, string $anlass, string $art, string $inhalt): void
    {
        $datum = date('d.m.Y', (int) strtotime((string) $turnier['datum']));
        $text = 'Guten Tag ' . $a['ansprechpartner'] . ",\n\n" . $inhalt
            . "\nTurnier: {$turnier['name']} am $datum" . ($turnier['ort'] ? " in {$turnier['ort']}" : '') . "\n"
            . "Dojo: {$a['dojo']} · Anmelde-Nr. {$a['id']}\n"
            . "\n-- \nDiese Nachricht wurde automatisch von Taikai – Karate (Turniersoftware) verschickt. "
            . "Sie erhalten sie, weil diese Adresse bei der Online-Anmeldung angegeben wurde.";
        $this->post->einreihen((string) $a['email'], "$anlass – {$turnier['name']}", $text, $art, (int) $turnier['id']);
    }

    /** @param array<string, mixed> $a */
    private function zusammenfassung(array $a): string
    {
        $daten = json_decode((string) $a['daten'], true) ?: ['teilnehmer' => [], 'teams' => []];
        return 'Gemeldet: ' . count($daten['teilnehmer']) . ' Teilnehmer, ' . count($daten['teams']) . ' ' . (count($daten['teams']) === 1 ? 'Team' : 'Teams')
            . ' · Startgeld ' . AnmeldungService::euro((int) $a['aufforderung_cent']) . "\n\n";
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
            'ZAHLUNGSAUFFORDERUNG' . ((int) $a['aufforderung_version'] > 1 ? ' (Version ' . (int) $a['aufforderung_version'] . ')' : ''),
            'Zu überweisen:     ' . AnmeldungService::euro($stand['offen']) . ($stand['bezahlt'] > 0 ? ' (bereits erhalten: ' . AnmeldungService::euro($stand['bezahlt']) . ')' : ''),
            'Zahlbar bis:       ' . date('d.m.Y', (int) strtotime($frist)),
            'Verwendungszweck:  ' . $rechnung->nummerAnmeldung($turnier, (int) $a['id']) . ' ' . $a['dojo'],
        ];
        if ($z['absender'] !== '') {
            $zeilen[] = 'Empfänger:         ' . implode(', ', array_filter(array_map('trim', explode("\n", $z['absender']))));
        }
        if ($z['bank'] !== '') {
            $zeilen[] = 'Bankverbindung:    ' . implode(' · ', array_filter(array_map('trim', explode("\n", $z['bank']))));
        }
        if (Girocode::nutzdaten($z['absender'], $z['bank'], $stand['offen'], '') !== null) {
            $zeilen[] = '';
            $zeilen[] = 'Bequem per Banking-App: Die Übersicht zur Anmeldung (Bearbeitungslink) zeigt einen QR-Code zum Scannen (GiroCode).';
        }
        $hinweis = (new AnmeldungService($this->db))->einstellungen($turnier)['hinweis'];
        return implode("\n", $zeilen) . "\n" . ($hinweis !== '' ? "\n$hinweis\n" : '') . "\n";
    }
}
