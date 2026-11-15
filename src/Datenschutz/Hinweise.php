<?php

declare(strict_types=1);

namespace Wettkampf\Datenschutz;

use Wettkampf\Meldung\Einladungen;
use Wettkampf\Turnier\SystemEinstellungen;
use Wettkampf\Kern\Db\Database;

/**
 * Datenschutzhinweise nach Art. 13/14 DSGVO je Turnier, erzeugt aus den Datenschutz-Angaben des Turniers und dem, was
 * die Software tatsächlich verarbeitet. Dazu ein kurzer Baustein für die Ausschreibung.
 *
 * Reiner Text (keine HTML-Auszeichnung): Das Template escaped alles. Die Texte sind eine Vorlage – der Veranstalter
 * bleibt Verantwortlicher und prüft sie (Hinweis auf der Einstellungsseite). Sprache: die der anfragenden Person
 * (auf Englisch mit Vorrang-Hinweis im Template); die Angaben des Vereins bleiben, wie eingetragen.
 */
final class Hinweise
{
    public function __construct(private readonly Database $db) {}

    /**
     * @param array<string, mixed> $turnier
     * @return list<array{titel: string, absaetze: list<string>, punkte: list<string>}>
     */
    public function abschnitte(array $turnier): array
    {
        $e = DatenschutzEinstellungen::lesen($turnier);
        $name = (string) $turnier['name'];
        $wochen = $e['loeschfrist_wochen'];
        $kontoMonate = (new SystemEinstellungen($this->db))->kontoMonate();
        $a = [];

        $verantwortlich = [$e['verantwortlicher'] !== '' ? $e['verantwortlicher'] : t('(Verantwortlicher noch nicht eingetragen)')];
        if ($e['vertreten'] !== '') {
            $verantwortlich[] = t('Vertreten durch: {name}', ['name' => $e['vertreten']]);
        }
        $verantwortlich[] = t('Kontakt: {kontakt}', ['kontakt' => $e['kontakt'] !== '' ? $e['kontakt'] : t('(noch nicht eingetragen)')]);
        if ($e['dsb'] !== '') {
            $verantwortlich[] = t('Datenschutzbeauftragte/r: {name}', ['name' => $e['dsb']]);
        }
        $a[] = self::abschnitt(t('1. Verantwortlicher'), [
            t('Diese Hinweise informieren Teilnehmende, Erziehungsberechtigte, Coaches und Vereinsvertreter über die Verarbeitung personenbezogener Daten beim Turnier „{name}“. Verantwortlich im Sinne der Datenschutz-Grundverordnung (DSGVO) ist:', ['name' => $name]),
            implode("\n", $verantwortlich),
        ]);

        $a[] = self::abschnitt(t('2. Welche Daten wir verarbeiten und woher sie stammen'), [
            t('Die Anmeldung erfolgt in der Regel gesammelt durch das Dojo bzw. den Verein über die Online-Anmeldung. Die Daten der Starter erhalten wir daher meist nicht von ihnen selbst, sondern vom meldenden Dojo (Art. 14 DSGVO).'),
        ], [
            t('Starter: Vor- und Nachname, Geburtsdatum, Geschlecht, Graduierung (Kyu/Dan), Verein/Dojo, gemeldete Disziplinen (Kata, Kumite, Team), am Turniertag Anwesenheit, gewählte Kata, Wertungen, Ergebnisse und Platzierungen.'),
            t('Meldende Personen (Ansprechpartner): Name, E-Mail-Adresse, Telefonnummer (freiwillig), Bemerkungen zur Anmeldung, Startgeld und Zahlungseingang.'),
            ($kontoMonate === 1 ? t('Dojo-Zugang (freiwillig): Name, E-Mail-Adresse, Telefon, Passwort (nur als Hash), ggf. zweiter Faktor. Der Zugang wird 1 Monat nach dem Turnier automatisch gelöscht.') : t('Dojo-Zugang (freiwillig): Name, E-Mail-Adresse, Telefon, Passwort (nur als Hash), ggf. zweiter Faktor. Der Zugang wird {monate} Monate nach dem Turnier automatisch gelöscht.', ['monate' => $kontoMonate])),
            t('Wettkampfleitung und Tischbesatzung: Name bzw. Gerätename und welche Eingaben und Änderungen sie vorgenommen haben (Änderungsverlauf zur Nachvollziehbarkeit der Ergebnisse).'),
            t('Technisch: Bei fehlgeschlagenen Anmeldeversuchen die IP-Adresse für höchstens 24 Stunden (Schutz vor dem Durchprobieren von Passwörtern); der Hoster speichert Zugriffsprotokolle nach seinen Vorgaben.'),
        ]);

        $live = $e['live_tage'] > 0 ? ($e['live_tage'] === 1 ? t('Die Statusseite wird 1 Tag nach dem Turnier abgeschaltet.') : t('Die Statusseite wird {tage} Tage nach dem Turnier abgeschaltet.', ['tage' => $e['live_tage']])) : '';
        $punkte = [
            t('Anmeldung, Klasseneinteilung, Auslosung, Durchführung, Ergebnisse und Urkunden (Art. 6 Abs. 1 lit. b DSGVO): Name, Geburtsdatum, Geschlecht und Graduierung sind nötig, um die Starter nach der Wettkampfordnung in Alters- und Leistungsklassen einzuteilen und das Turnier durchzuführen.'),
            trim(t('Start- und Ergebnislisten, Aushänge in der Halle und die Live-Statusseite (Art. 6 Abs. 1 lit. f DSGVO): Unser berechtigtes Interesse und das der teilnehmenden Vereine und Zuschauer ist ein geordneter Ablauf und die zeitnahe Information über den Wettkampf. Angezeigt werden nur Name, Verein, Klasse, Kämpfe und Ergebnisse – keine Geburtsdaten.') . ' ' . $live),
            t('Coaching-Portal: Coaches sehen nach Freigabe durch die Turnierleitung nur die Starter ihres eigenen Dojos.'),
            t('Startgeld, Zahlungsaufforderungen und Rechnungen (Art. 6 Abs. 1 lit. b und c DSGVO): Berechnung, Zahlungsabgleich und Aufbewahrung nach den steuer- und handelsrechtlichen Pflichten.'),
        ];
        if ($e['veroeffentlichung'] !== '') {
            $punkte[] = t('Veröffentlichung der Siegerlisten (Platz, Name, Verein, Klasse) {wo} (Art. 6 Abs. 1 lit. f DSGVO, Berichterstattung über das Turnier).', ['wo' => $e['veroeffentlichung']]);
        }
        $a[] = self::abschnitt(t('3. Zwecke und Rechtsgrundlagen'), [], $punkte);

        $empfaenger = [
            t('Technischer Betrieb: Die Software „Taikai – Karate“ (freie Software) läuft auf einem von uns betriebenen Server bzw. am Turniertag auf einem Laptop in der Halle. Die Entwickler der Software erhalten keine Daten; es gibt keine Werbung, kein Tracking und keine Analysedienste.'),
        ];
        if ($e['hoster'] !== '') {
            $empfaenger[] = t('Hosting: {hoster} – mit einem Vertrag zur Auftragsverarbeitung nach Art. 28 DSGVO.', ['hoster' => $e['hoster']]);
        }
        if ($e['cloudflare']) {
            $empfaenger[] = t('Schutz und Auslieferung: Alle Seiten werden über Cloudflare (Cloudflare, Inc., USA; in der EU Cloudflare Germany GmbH) ausgeliefert, um Angriffe abzuwehren. Dabei werden IP-Adresse und technische Verbindungsdaten verarbeitet; eine Übermittlung in die USA ist möglich (Grundlage: EU-US Data Privacy Framework bzw. Standardvertragsklauseln, Auftragsverarbeitung nach Art. 28 DSGVO).');
        }
        $empfaenger[] = t('E-Mails (Bestätigungen, Zahlungsaufforderungen) werden über den Mailserver des Veranstalters bzw. Hosters versendet.');
        $empfaenger[] = t('Innerhalb des Turniers sehen Wettkampfleitung und Tischbesatzung die Starterdaten, die sie für ihre Aufgabe brauchen; Zuschauer nur die öffentlichen Angaben aus Abschnitt 3.');
        $a[] = self::abschnitt(t('4. Empfänger'), [], $empfaenger);

        $speicher = [
            $wochen === 1
                ? t('Turnierdaten: Spätestens {wochen} Woche nach dem Turnier werden die Starter pseudonymisiert: Namen werden durch „Starter 1, 2, …“ ersetzt, Geburtsdaten, Kontaktdaten der Anmeldungen, Bemerkungen, E-Mails und die Personenangaben im Änderungsverlauf gelöscht. Erhalten bleiben nur Klassen, Anzahl der Starter, Begegnungen, Ergebnisse und Vereine (Statistik).', ['wochen' => $wochen])
                : t('Turnierdaten: Spätestens {wochen} Wochen nach dem Turnier werden die Starter pseudonymisiert: Namen werden durch „Starter 1, 2, …“ ersetzt, Geburtsdaten, Kontaktdaten der Anmeldungen, Bemerkungen, E-Mails und die Personenangaben im Änderungsverlauf gelöscht. Erhalten bleiben nur Klassen, Anzahl der Starter, Begegnungen, Ergebnisse und Vereine (Statistik).', ['wochen' => $wochen]),
        ];
        if ($e['platzierte_behalten']) {
            $speicher[] = t('Siegerlisten: Die Plätze 1 bis 3 bleiben mit Name, Verein und Klasse dauerhaft erhalten (Chronik des Vereins, Art. 6 Abs. 1 lit. f DSGVO).');
        }
        $speicher[] = t('Abrechnungsunterlagen (Zahlungsaufforderungen, Rechnungen, Zahlungseingänge – mit Dojo, meldender Person und Anzahl der Starts, ohne Starterliste) werden vor der Pseudonymisierung exportiert und außerhalb der Software nach den gesetzlichen Fristen aufbewahrt (§ 147 AO, § 257 HGB: Buchungsbelege derzeit 8 Jahre, Geschäftsbriefe 6 Jahre).');
        $speicher[] = ($kontoMonate === 1 ? t('Dojo-Zugänge werden 1 Monat nach dem Turnier gelöscht, unbestätigte nach 7 Tagen.') : t('Dojo-Zugänge werden {monate} Monate nach dem Turnier gelöscht, unbestätigte nach 7 Tagen.', ['monate' => $kontoMonate]));
        $a[] = self::abschnitt(t('5. Speicherdauer und Löschung'), [], $speicher);

        $a[] = self::abschnitt(t('6. Ihre Rechte'), [
            t('Sie haben – für sich bzw. für Ihr Kind – das Recht auf Auskunft (Art. 15 DSGVO), Berichtigung (Art. 16), Löschung (Art. 17), soweit keine Aufbewahrungspflicht entgegensteht, Einschränkung der Verarbeitung (Art. 18) und Widerspruch gegen Verarbeitungen auf Grundlage berechtigter Interessen (Art. 21). Eine Teilnahme am Wettkampf ohne Nennung in den Kampf- und Ergebnislisten ist aus organisatorischen Gründen nicht möglich.'),
            $e['kontakt'] !== ''
                ? t('Eine formlose Nachricht an {kontakt} genügt.', ['kontakt' => $e['kontakt']])
                : t('Eine formlose Nachricht an den Veranstalter genügt.'),
            t('Sie können sich bei einer Datenschutz-Aufsichtsbehörde beschweren (Art. 77 DSGVO). Zuständig ist: {aufsicht}', ['aufsicht' => $e['aufsicht'] !== '' ? $e['aufsicht'] : t('(noch nicht eingetragen)')]),
        ]);

        $nr = 7;
        if ($e['einladungen']) {
            $a[] = self::abschnitt(t('{nr}. Einladungen zu künftigen Turnieren (freiwillig)', ['nr' => $nr++]), [
                t('Wer bei der Anmeldung ausdrücklich einwilligt, wird per E-Mail zu künftigen Turnieren des Veranstalters eingeladen (Art. 6 Abs. 1 lit. a DSGVO). Die Einwilligung ist freiwillig und keine Bedingung für die Teilnahme.'),
                t('Gespeichert werden E-Mail-Adresse, Name, Dojo sowie Zeitpunkt und Wortlaut der Einwilligung – getrennt von den Turnierdaten, daher auch nach deren Pseudonymisierung. Widerruf jederzeit mit Wirkung für die Zukunft über den Link in jeder Einladung oder formlos an den Kontakt in Abschnitt 1; die Adresse wird dann gelöscht. Ohne erneute Einwilligung löschen wir sie spätestens {monate} Monate nach der letzten Einwilligung.', ['monate' => Einladungen::GUELTIG_MONATE]),
            ]);
        }
        if ($e['fotos']) {
            $a[] = self::abschnitt(t('{nr}. Foto- und Videoaufnahmen', ['nr' => $nr++]), [
                t('Während des Turniers fertigen Beauftragte des Veranstalters Fotos und Videos für die Berichterstattung und Öffentlichkeitsarbeit des Vereins an (Vereinswebseite, Presse, ggf. soziale Medien). Rechtsgrundlage für Übersichtsaufnahmen des Wettkampfgeschehens ist Art. 6 Abs. 1 lit. f DSGVO. Für gezielte Einzelaufnahmen, insbesondere von Minderjährigen außerhalb der Siegerehrung, holen wir vorab eine Einwilligung ein. Wer im Einzelfall nicht abgebildet werden möchte, wendet sich bitte vor Ort an die Wettkampfleitung.'),
            ]);
        }
        if ($e['zusatz'] !== '') {
            $a[] = self::abschnitt(t('{nr}. Weitere Hinweise', ['nr' => $nr]), [$e['zusatz']]);
        }
        return $a;
    }

    /**
     * Kurzer Baustein für Ausschreibung bzw. Einladung (reiner Text zum Kopieren).
     *
     * @param array<string, mixed> $turnier
     */
    public function ausschreibung(array $turnier, string $link): string
    {
        $e = DatenschutzEinstellungen::lesen($turnier);
        $wochen = $e['loeschfrist_wochen'];
        $zeilen = [
            t('Datenschutz'),
            t('Für Anmeldung und Durchführung des Turniers verwenden wir die freie Turniersoftware „Taikai – Karate“ auf einem eigenen Server – ohne Weitergabe an Werbe- oder Analysedienste. Verarbeitet werden Vor- und Nachname, Geburtsdatum, Geschlecht, Graduierung, Verein und die gemeldeten Disziplinen (Art. 6 Abs. 1 lit. b DSGVO).'),
            '• ' . t('Start- und Ergebnislisten, Aushänge und die Live-Statusseite zeigen Name, Verein, Klasse und Ergebnisse – keine Geburtsdaten.')
                . ($e['live_tage'] > 0 ? ' ' . ($e['live_tage'] === 1 ? t('Die Statusseite wird 1 Tag nach dem Turnier abgeschaltet.') : t('Die Statusseite wird {tage} Tage nach dem Turnier abgeschaltet.', ['tage' => $e['live_tage']])) : ''),
            '• ' . t('Das meldende Dojo bestätigt bei der Anmeldung, dass die Starter bzw. bei Minderjährigen die Erziehungsberechtigten über diese Hinweise informiert wurden.'),
            '• ' . ($wochen === 1
                ? t('Spätestens {wochen} Woche nach dem Turnier werden die Starterdaten pseudonymisiert', ['wochen' => $wochen])
                : t('Spätestens {wochen} Wochen nach dem Turnier werden die Starterdaten pseudonymisiert', ['wochen' => $wochen]))
                . ($e['platzierte_behalten'] ? ' (' . t('die Plätze 1–3 bleiben mit Name und Verein in der Chronik') . ')' : '')
                . '; ' . t('Abrechnungsunterlagen bewahren wir nach den gesetzlichen Fristen auf.'),
        ];
        if ($e['fotos']) {
            $zeilen[] = '• ' . t('Beim Turnier werden Fotos und Videos für die Berichterstattung des Vereins angefertigt.');
        }
        $zeilen[] = t('Vollständige Datenschutzhinweise: {link}', ['link' => $link]);
        return implode("\n", $zeilen);
    }

    /**
     * @param list<string> $absaetze
     * @param list<string> $punkte
     * @return array{titel: string, absaetze: list<string>, punkte: list<string>}
     */
    private static function abschnitt(string $titel, array $absaetze, array $punkte = []): array
    {
        return ['titel' => $titel, 'absaetze' => $absaetze, 'punkte' => $punkte];
    }
}
