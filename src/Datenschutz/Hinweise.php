<?php

declare(strict_types=1);

namespace Wettkampf\Datenschutz;

use Wettkampf\Turnier\SystemEinstellungen;
use Wettkampf\Kern\Db\Database;

/**
 * Datenschutzhinweise nach Art. 13/14 DSGVO je Turnier, erzeugt aus den Datenschutz-Angaben des Turniers und dem, was
 * die Software tatsächlich verarbeitet. Dazu ein kurzer Baustein für die Ausschreibung.
 *
 * Reiner Text (keine HTML-Auszeichnung): Das Template escaped alles. Die Texte sind eine Vorlage – der Veranstalter
 * bleibt Verantwortlicher und prüft sie (Hinweis auf der Einstellungsseite).
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

        $verantwortlich = [$e['verantwortlicher'] !== '' ? $e['verantwortlicher'] : '(Verantwortlicher noch nicht eingetragen)'];
        if ($e['vertreten'] !== '') {
            $verantwortlich[] = 'Vertreten durch: ' . $e['vertreten'];
        }
        $verantwortlich[] = 'Kontakt: ' . ($e['kontakt'] !== '' ? $e['kontakt'] : '(noch nicht eingetragen)');
        if ($e['dsb'] !== '') {
            $verantwortlich[] = 'Datenschutzbeauftragte/r: ' . $e['dsb'];
        }
        $a[] = self::abschnitt('1. Verantwortlicher', [
            "Diese Hinweise informieren Teilnehmende, Erziehungsberechtigte, Coaches und Vereinsvertreter über die Verarbeitung personenbezogener Daten beim Turnier „{$name}“. Verantwortlich im Sinne der Datenschutz-Grundverordnung (DSGVO) ist:",
            implode("\n", $verantwortlich),
        ]);

        $a[] = self::abschnitt('2. Welche Daten wir verarbeiten und woher sie stammen', [
            'Die Anmeldung erfolgt in der Regel gesammelt durch das Dojo bzw. den Verein über die Online-Anmeldung. Die Daten der Starter erhalten wir daher meist nicht von ihnen selbst, sondern vom meldenden Dojo (Art. 14 DSGVO).',
        ], [
            'Starter: Vor- und Nachname, Geburtsdatum, Geschlecht, Graduierung (Kyu/Dan), Verein/Dojo, gemeldete Disziplinen (Kata, Kumite, Team), am Turniertag Anwesenheit, gewählte Kata, Wertungen, Ergebnisse und Platzierungen.',
            'Meldende Personen (Ansprechpartner): Name, E-Mail-Adresse, Telefonnummer (freiwillig), Bemerkungen zur Anmeldung, Startgeld und Zahlungseingang.',
            "Dojo-Zugang (freiwillig): Name, E-Mail-Adresse, Telefon, Passwort (nur als Hash), ggf. zweiter Faktor. Der Zugang wird {$kontoMonate} Monate nach dem Turnier automatisch gelöscht.",
            'Wettkampfleitung und Tischbesatzung: Name bzw. Gerätename und welche Eingaben und Änderungen sie vorgenommen haben (Änderungsverlauf zur Nachvollziehbarkeit der Ergebnisse).',
            'Technisch: Bei fehlgeschlagenen Anmeldeversuchen die IP-Adresse für höchstens 24 Stunden (Schutz vor dem Durchprobieren von Passwörtern); der Hoster speichert Zugriffsprotokolle nach seinen Vorgaben.',
        ]);

        $live = $e['live_tage'] > 0 ? "Die Statusseite wird {$e['live_tage']} Tage nach dem Turnier abgeschaltet." : '';
        $punkte = [
            'Anmeldung, Klasseneinteilung, Auslosung, Durchführung, Ergebnisse und Urkunden (Art. 6 Abs. 1 lit. b DSGVO): Name, Geburtsdatum, Geschlecht und Graduierung sind nötig, um die Starter nach der Wettkampfordnung in Alters- und Leistungsklassen einzuteilen und das Turnier durchzuführen.',
            trim('Start- und Ergebnislisten, Aushänge in der Halle und die Live-Statusseite (Art. 6 Abs. 1 lit. f DSGVO): Unser berechtigtes Interesse und das der teilnehmenden Vereine und Zuschauer ist ein geordneter Ablauf und die zeitnahe Information über den Wettkampf. Angezeigt werden nur Name, Verein, Klasse, Kämpfe und Ergebnisse – keine Geburtsdaten. ' . $live),
            'Coaching-Portal: Coaches sehen nach Freigabe durch die Turnierleitung nur die Starter ihres eigenen Dojos.',
            'Startgeld, Zahlungsaufforderungen und Rechnungen (Art. 6 Abs. 1 lit. b und c DSGVO): Berechnung, Zahlungsabgleich und Aufbewahrung nach den steuer- und handelsrechtlichen Pflichten.',
        ];
        if ($e['veroeffentlichung'] !== '') {
            $punkte[] = 'Veröffentlichung der Siegerlisten (Platz, Name, Verein, Klasse) ' . $e['veroeffentlichung'] . ' (Art. 6 Abs. 1 lit. f DSGVO, Berichterstattung über das Turnier).';
        }
        $a[] = self::abschnitt('3. Zwecke und Rechtsgrundlagen', [], $punkte);

        $empfaenger = [
            'Technischer Betrieb: Die Software „Taikai – Karate“ (freie Software) läuft auf einem von uns betriebenen Server bzw. am Turniertag auf einem Laptop in der Halle. Die Entwickler der Software erhalten keine Daten; es gibt keine Werbung, kein Tracking und keine Analysedienste.',
        ];
        if ($e['hoster'] !== '') {
            $empfaenger[] = 'Hosting: ' . $e['hoster'] . ' – mit einem Vertrag zur Auftragsverarbeitung nach Art. 28 DSGVO.';
        }
        if ($e['cloudflare']) {
            $empfaenger[] = 'Schutz und Auslieferung: Alle Seiten werden über Cloudflare (Cloudflare, Inc., USA; in der EU Cloudflare Germany GmbH) ausgeliefert, um Angriffe abzuwehren. Dabei werden IP-Adresse und technische Verbindungsdaten verarbeitet; eine Übermittlung in die USA ist möglich (Grundlage: EU-US Data Privacy Framework bzw. Standardvertragsklauseln, Auftragsverarbeitung nach Art. 28 DSGVO).';
        }
        $empfaenger[] = 'E-Mails (Bestätigungen, Zahlungsaufforderungen) werden über den Mailserver des Veranstalters bzw. Hosters versendet.';
        $empfaenger[] = 'Innerhalb des Turniers sehen Wettkampfleitung und Tischbesatzung die Starterdaten, die sie für ihre Aufgabe brauchen; Zuschauer nur die öffentlichen Angaben aus Abschnitt 3.';
        $a[] = self::abschnitt('4. Empfänger', [], $empfaenger);

        $speicher = [
            "Turnierdaten: Spätestens {$wochen} " . ($wochen === 1 ? 'Woche' : 'Wochen') . ' nach dem Turnier werden die Starter pseudonymisiert: Namen werden durch „Starter 1, 2, …“ ersetzt, Geburtsdaten, Kontaktdaten der Anmeldungen, Bemerkungen, E-Mails und die Personenangaben im Änderungsverlauf gelöscht. Erhalten bleiben nur Klassen, Anzahl der Starter, Begegnungen, Ergebnisse und Vereine (Statistik).',
        ];
        if ($e['platzierte_behalten']) {
            $speicher[] = 'Siegerlisten: Die Plätze 1 bis 3 bleiben mit Name, Verein und Klasse dauerhaft erhalten (Chronik des Vereins, Art. 6 Abs. 1 lit. f DSGVO).';
        }
        $speicher[] = 'Abrechnungsunterlagen (Zahlungsaufforderungen, Rechnungen, Zahlungseingänge – mit Dojo, meldender Person und Anzahl der Starts, ohne Starterliste) werden vor der Pseudonymisierung exportiert und außerhalb der Software nach den gesetzlichen Fristen aufbewahrt (§ 147 AO, § 257 HGB: Buchungsbelege derzeit 8 Jahre, Geschäftsbriefe 6 Jahre).';
        $speicher[] = "Dojo-Zugänge werden {$kontoMonate} Monate nach dem Turnier gelöscht, unbestätigte nach 7 Tagen.";
        $a[] = self::abschnitt('5. Speicherdauer und Löschung', [], $speicher);

        $a[] = self::abschnitt('6. Ihre Rechte', [
            'Sie haben – für sich bzw. für Ihr Kind – das Recht auf Auskunft (Art. 15 DSGVO), Berichtigung (Art. 16), Löschung (Art. 17), soweit keine Aufbewahrungspflicht entgegensteht, Einschränkung der Verarbeitung (Art. 18) und Widerspruch gegen Verarbeitungen auf Grundlage berechtigter Interessen (Art. 21). Eine Teilnahme am Wettkampf ohne Nennung in den Kampf- und Ergebnislisten ist aus organisatorischen Gründen nicht möglich.',
            'Eine formlose Nachricht an ' . ($e['kontakt'] !== '' ? $e['kontakt'] : 'den Veranstalter') . ' genügt.',
            'Sie können sich bei einer Datenschutz-Aufsichtsbehörde beschweren (Art. 77 DSGVO). Zuständig ist: ' . ($e['aufsicht'] !== '' ? $e['aufsicht'] : '(noch nicht eingetragen)'),
        ]);

        if ($e['fotos']) {
            $a[] = self::abschnitt('7. Foto- und Videoaufnahmen', [
                'Während des Turniers fertigen Beauftragte des Veranstalters Fotos und Videos für die Berichterstattung und Öffentlichkeitsarbeit des Vereins an (Vereinswebseite, Presse, ggf. soziale Medien). Rechtsgrundlage für Übersichtsaufnahmen des Wettkampfgeschehens ist Art. 6 Abs. 1 lit. f DSGVO. Für gezielte Einzelaufnahmen, insbesondere von Minderjährigen außerhalb der Siegerehrung, holen wir vorab eine Einwilligung ein. Wer im Einzelfall nicht abgebildet werden möchte, wendet sich bitte vor Ort an die Wettkampfleitung.',
            ]);
        }
        if ($e['zusatz'] !== '') {
            $a[] = self::abschnitt(($e['fotos'] ? '8' : '7') . '. Weitere Hinweise', [$e['zusatz']]);
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
            'Datenschutz',
            'Für Anmeldung und Durchführung des Turniers verwenden wir die freie Turniersoftware „Taikai – Karate“ auf einem eigenen Server – ohne Weitergabe an Werbe- oder Analysedienste. '
                . 'Verarbeitet werden Vor- und Nachname, Geburtsdatum, Geschlecht, Graduierung, Verein und die gemeldeten Disziplinen (Art. 6 Abs. 1 lit. b DSGVO).',
            '• Start- und Ergebnislisten, Aushänge und die Live-Statusseite zeigen Name, Verein, Klasse und Ergebnisse – keine Geburtsdaten.'
                . ($e['live_tage'] > 0 ? " Die Statusseite wird {$e['live_tage']} Tage nach dem Turnier abgeschaltet." : ''),
            '• Das meldende Dojo bestätigt bei der Anmeldung, dass die Starter bzw. bei Minderjährigen die Erziehungsberechtigten über diese Hinweise informiert wurden.',
            "• Spätestens {$wochen} " . ($wochen === 1 ? 'Woche' : 'Wochen') . ' nach dem Turnier werden die Starterdaten pseudonymisiert'
                . ($e['platzierte_behalten'] ? ' (die Plätze 1–3 bleiben mit Name und Verein in der Chronik)' : '') . '; Abrechnungsunterlagen bewahren wir nach den gesetzlichen Fristen auf.',
        ];
        if ($e['fotos']) {
            $zeilen[] = '• Beim Turnier werden Fotos und Videos für die Berichterstattung des Vereins angefertigt.';
        }
        $zeilen[] = 'Vollständige Datenschutzhinweise: ' . $link;
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
