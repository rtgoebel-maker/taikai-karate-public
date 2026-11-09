<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http;

/**
 * Kurztexte der Info-Knöpfe (ⓘ) für die Wettkampfleitung. Jeder Schlüssel ist
 * zugleich der Anker des passenden Abschnitts auf der Hilfeseite (/hilfe#…).
 */
final class Hilfe
{
    /** @var array<string, array{0: string, 1: string}> Schlüssel → [Titel, Kurztext] */
    public const TEXTE = [
        'ablauf' => ['Ablauf eines Turniers', 'Turnier anlegen → Klassen aus Vorlage → Teilnehmer (Online-Anmeldung, Import, manuell) → kleine Klassen prüfen → auslosen → Tatami-Plan → PINs vergeben → Turnier starten → Ergebnisse → Klassen bestätigen → abschließen.'],
        'phasen' => ['Turnierphasen', 'Vorbereitung → Meldephase (Online-Anmeldung offen) → Turnier läuft (nur jetzt nehmen Tablets und PC Ergebnisse an) → Abgeschlossen. „Turnier starten“ kann offene Klassen gleich auslosen und verteilen.'],
        'hinweise' => ['Hinweise', 'Was noch zu tun ist: Klassen unter der Mindestanzahl, Meldungen ohne Klasse, Doppelmeldungen, Team-Regeln. Ein Klick führt zur Klasse bzw. zum Teilnehmer.'],
        'nicht-erschienen' => ['Nicht erschienen', 'Rückmeldung der Ausrufer. Ein Starter kann gerade auf einer anderen Tatami sein – entscheide: ist doch da, Kiken nur in diesem Kampf, aus allen Wettkämpfen nehmen (alle offenen Kämpfe Kiken) oder zusätzlich die Klasse neu auslosen.'],
        'anmeldung' => ['Online-Anmeldung', 'Dojos melden Teilnehmer, Disziplinen und Teams selbst und sehen den Gesamtpreis. Geöffnet ist die Seite nur in der Meldephase und bis zum Meldeschluss. Erst die Freigabe übernimmt die Daten.'],
        'zahlung' => ['Bearbeitungsstand und Zahlung', 'Das Dojo sieht: eingegangen → Zahlungsaufforderung (automatisch, bei Änderung neue Version) → Zahlung eingegangen → freigegeben. Den Zahlungseingang erfasst die Leitung in der Anmeldung; Rechnung nur auf Wunsch.'],
        'rechnungen' => ['Rechnungen', 'Je Dojo eine Rechnung über das Startgeld (gleiche Berechnung wie im Coaching-Portal) mit fester Rechnungsnummer. Absender, Bankverbindung, Zahlungsziel und Steuerhinweis einmal je Turnier eintragen; Drucken bzw. als PDF speichern.'],
        'startgeld' => ['Startgeld', 'Preis je Einzelstart (Kata, Kumite) und je Team. Wer Kata und Kumite läuft, zahlt zweimal. Der Hinweistext (z. B. Bankverbindung) erscheint auf der Anmeldeseite und der Druckübersicht.'],
        'freigabe' => ['Freigabe', 'Legt Verein, Teilnehmer und Teams an und ordnet sie automatisch den Klassen zu. Vor der Auslosung freigeben – in ausgeloste Klassen wird nicht automatisch eingetragen. Danach kann das Dojo nichts mehr ändern.'],
        'teilnehmer' => ['Teilnehmer', 'Status „gesperrt“ (z. B. Wettkampfarzt), „abgemeldet“ oder „nicht erschienen“ (Knopf in der Liste): alle offenen Kämpfe gehen kampflos (Kiken) an die Gegner. Die Klasse ergibt sich aus Geschlecht, Alter am Turniertag und Graduierung.'],
        'import' => ['Import', 'XLSX oder CSV nach Vorlage hochladen, Vorschau prüfen, übernehmen. Teams entstehen über die Spalten „Kata Team“/„Kumite Team“.'],
        'teams' => ['Teams', 'Ein Team startet in der Altersklasse des ältesten Mitglieds. 2:1-Regel: ein Mitglied darf für einen anderen Verein starten. Das Suchfeld findet Team, Verein, Mitglied oder Klasse.'],
        'klassen' => ['Klassen', 'Vorlage laden und anpassen. Filter „Besetzung“ zeigt, welche Klassen stattfinden, ausfallen oder leer sind; die Tabelle zeigt Starter, Mindestanzahl und Tatami.'],
        'zulassung' => ['Zulassung', 'Wer automatisch in die Klasse kommt: Geschlecht, Alter am Turniertag, Graduierung. Exklusiv-Gruppe: in Klassen derselben Gruppe darf man nur einmal starten (z. B. Jiyu-Ippon oder Jiyu-Kumite).'],
        'modus' => ['Modus und Wertung', 'K.-o. (2: ein Kampf, 3: jeder gegen jeden, ab 4: Baum mit Kampf um Platz 3), Pools (gleich groß, möglichst gerade), Punkte-Vorrunde (ab 9 Startern Pools zu höchstens 8, je Pool die besten zwei ins Finale, Pools getrennt auf mehreren Tatamis; Gleichstand: + niedrigste, dann + höchste Wertung, dann Stechen). Mindestanzahl Starter: darunter findet die Klasse nicht statt (mind. 2).'],
        'abweichungen' => ['Abweichungen ab einer Runde', 'Z. B. „Finale: Kata frei“ oder „ab letzte 8: Jiyu-Kumite“. Wird beim Auslosen je Kampf festgeschrieben.'],
        'auslosung' => ['Auslosung', 'Zufällig, aber reproduzierbar (Seed im Protokoll). Freilose in Runde 1 gleichmäßig verteilt, Starter desselben Dojos möglichst spät gegeneinander. Neu auslosen geht, solange kein Ergebnis eingetragen ist.'],
        'zusammenlegung' => ['Zusammenlegen', 'Kleine Klassen mit einer passenden anderen Klasse zusammenführen – Grund und Zustimmung (Trainer/Eltern) werden vermerkt. „Aufheben“ macht es rückgängig, solange nicht ausgelost ist.'],
        'tatami-plan' => ['Tatami-Plan', 'Klassen per Ziehen oder Pfeilen verteilen, „automatisch verteilen“ (Kata/Kumite getrennt). Startzeiten sind Schätzungen. Je Tatami: Tisch-Ansicht und Ausruf-Ansicht öffnen.'],
        'zugaenge' => ['Tatamis und Zugänge', 'Je Tatami eine PIN für Tablet und Ausruf-Handy, dazu die Leitungs-PIN: Korrekturen am Tablet nur mit ihr.'],
        'ergebnisse' => ['Ergebnisse und Bestätigung', 'Stand aller Klassen. Ist eine Klasse fertig: bestätigen – erst dann erscheinen die Platzierungen in Siegerliste und Statusseite. Bei Gleichstand fragt die Klasse nach einem Stichentscheid.'],
        'korrektur' => ['Eingabe und Korrektur am PC', 'Ergebnisse nur, während das Turnier läuft. Korrekturen sind möglich, solange der Folgekampf nicht entschieden und die Klasse nicht bestätigt ist; alles wird protokolliert.'],
        'druck' => ['Druck', 'Kampflisten je Tatami für die Tischbesatzung, Turnierbäume, Ergebnis- und Siegerliste, Starter je Verein. Im Druckdialog „Kopf- und Fußzeilen“ ausschalten.'],
        'system' => ['Systemeinstellungen', 'Gelten für alle Turniere: Farbschema der Oberfläche, Vereins-/Dojo-Logo und sichtbare Bereiche der Statusseite (Starterlisten, Ergebnisse), Feedback-Knopf. Turnierbezogenes steht im Turnier unter „Einstellungen“.'],
        'demo' => ['Demo-Modus', 'Eingeschaltet erscheint unter System „Demo-Turnier anlegen“: ein neues Turnier mit erfundenen Teilnehmern, ausgelost und verteilt, aber noch nicht gestartet – zum Ausprobieren und für Schulungen. Wahlweise mit Kata- und Kumite-Teams.'],
        'feedback' => ['Feedback', 'Ist der Knopf eingeschaltet (System), melden Leitung und Tablets Fehler, Vorschläge oder Fragen – mit Seite und Screenshot; jede Meldung geht per Mail an feedback@taikai-karate.de (setzt E-Mail-Versand voraus). Hier Status und Notiz pflegen (speichert automatisch) und alles als ZIP mit Screenshots oder als JSON exportieren.'],
        'verteilung' => ['Verteilung', 'Dynamisch: Jede Tatami hat nur ihre aktuelle Gruppe; sobald deren letzter Kampf entschieden ist, bekommt sie die nächste aus der Warteschlange. Fester Plan: alles vorab verteilt. Gesperrte Tatamis bekommen nichts Neues.'],
        'nachricht' => ['Nachricht an die Tische', 'Erscheint auf Tablet und Ausruf-Handy der Tatami über der Kopfzeile, ohne die Eingabe zu stören, und bleibt stehen, bis die Tischbesatzung „Gelesen“ tippt. Hier siehst du, wann bestätigt wurde.'],
        'notfall' => ['Notfall-Backup und Aushang', 'Sobald ausgelost ist: je Klasse ein Satz aus Baum/Pool-Liste und Kampfliste aller Kämpfe – mit Feld „Tatami“ für die Handschrift. Der Aushang (A4 oder A3, quer, große Schrift) ersetzt die Statusseite, wenn die Technik ausfällt.'],
        'urkunden' => ['Urkunden', 'Nach dem Bestätigen der Platzierungen: eine Urkunde je Platz (Teams: je Mitglied), in der Klasse „🏅 Urkunden drucken“ oder hier für alle bestätigten Klassen. Im Druckcenter optional Logos hochladen (Verband oben mittig, Verein links, Dojo rechts) sowie beide Unterschriften als Bild. „Nur Text“ druckt passend auf vorgedrucktes Urkundenpapier.'],
        'vereine' => ['Vereine / Dojos', 'Gemeinsame Vereinsliste aller Turniere. Vereine entstehen automatisch – bei der Freigabe einer Online-Anmeldung, beim Import oder beim Erfassen eines Teilnehmers. Hier Schreibweisen korrigieren, einen Kurznamen für Turnierbaum, Tablet und Statusseite hinterlegen und doppelte Vereine zusammenführen. Ein Klick auf den Namen zeigt Teilnehmer, ihre Turniere, Coach-Konten und Anmeldungen.'],
        'sicherung' => ['Sicherung', 'Turnier als JSON exportieren (auch für den Umzug auf den Webserver) bzw. die ganze Datenbank herunterladen. Lokal (SQLite) sichert die Software zusätzlich automatisch alle paar Minuten; daraus lässt sich ein Turnier wiederherstellen.'],
    ];

    /** ⓘ-Knopf mit Kurztext und Link zur Hilfeseite. */
    public static function info(string $schluessel): string
    {
        if (!isset(self::TEXTE[$schluessel])) {
            return '';
        }
        [$titel, $text] = self::TEXTE[$schluessel];
        return '<details class="info"><summary aria-label="Hilfe: ' . e($titel) . '" title="Hilfe: ' . e($titel) . '">i</summary>'
            . '<div class="info-text"><strong>' . e($titel) . '</strong><p>' . e($text) . '</p>'
            . '<a href="' . e(url('hilfe') . '#' . $schluessel) . '">Mehr in der Hilfe →</a></div></details>';
    }
}
