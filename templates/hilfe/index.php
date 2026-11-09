<?php
/**
 * Hilfe für die Wettkampfleitung: wichtigste Funktionen, je Thema ein Abschnitt
 * (Anker = Schlüssel der Info-Knöpfe, siehe Hilfe::TEXTE).
 * @var array<string, mixed>|null $turnier
 */
$this->layout('layout', ['titel' => 'Hilfe', 'turnier' => $turnier, 'aktiv' => 'hilfe']);
$tid = $turnier['id'] ?? null;
/** Link auf eine Turnierseite – nur, wenn die Hilfe aus einem Turnier geöffnet wurde. */
$seite = static fn (string $pfad, string $text) => $tid !== null ? '<a href="' . e(url("turnier/$tid/$pfad")) . '">' . e($text) . '</a>' : '<strong>' . e($text) . '</strong>';
$abschnitte = [
    'ablauf' => 'Ablauf eines Turniers',
    'phasen' => 'Turnierphasen',
    'anmeldung' => 'Online-Anmeldung der Dojos',
    'startgeld' => 'Startgeld',
    'zahlung' => 'Bearbeitungsstand und Zahlung',
    'rechnungen' => 'Rechnungen an die Dojos',
    'freigabe' => 'Anmeldungen freigeben',
    'teilnehmer' => 'Teilnehmer',
    'import' => 'Import (XLSX/CSV)',
    'teams' => 'Teams',
    'klassen' => 'Klassen',
    'zulassung' => 'Zulassung einer Klasse',
    'modus' => 'Modus, Wertung, Mindestanzahl',
    'abweichungen' => 'Abweichungen ab einer Runde',
    'zusammenlegung' => 'Kleine Klassen zusammenlegen',
    'auslosung' => 'Auslosung',
    'tatami-plan' => 'Tatami-Plan',
    'zugaenge' => 'PINs und Zugänge',
    'tablet' => 'Tablet der Tischbesatzung',
    'ausruf' => 'Ausruf (Handy)',
    'nicht-erschienen' => 'Nicht erschienen',
    'korrektur' => 'Eingabe und Korrektur',
    'ergebnisse' => 'Ergebnisse und Bestätigung',
    'hinweise' => 'Hinweise in der Übersicht',
    'druck' => 'Druck',
    'statusseite' => 'Statusseite',
    'sicherung' => 'Sicherung und Umzug',
    'feedback' => 'System und Feedback',
    'fragen' => 'Häufige Fragen',
];
?>
<div class="titelzeile">
    <h1>Hilfe</h1>
    <input type="search" id="hilfe-suche" placeholder="Hilfe durchsuchen …" aria-label="Hilfe durchsuchen" style="max-width:320px">
</div>
<p class="leise">Die wichtigsten Funktionen für die Wettkampfleitung. Die <strong>ⓘ</strong>-Knöpfe auf den Seiten führen direkt zum passenden Abschnitt.
    Ausführlich: Handbuch (<code>docs/HANDBUCH.md</code>) und Seitenübersicht (<code>docs/ZUGRIFF.md</code>).</p>
<div class="karte">
    <h2>Handouts zum Ausdrucken</h2>
    <p class="hilfe">Kurzanleitungen mit Bildern für den Turniertag – auch: was bei einer Falscheingabe zu tun ist. Drucken oder als PDF speichern.</p>
    <p class="knopfleiste">
        <?php foreach (\Wettkampf\Betrieb\Http\HilfeController::HANDOUTS as $name => $titel): ?>
            <a class="knopf" href="<?= e(url("handout/$name", $tid !== null ? ['turnier' => $tid] : [])) ?>" target="_blank">🖨 <?= e($titel) ?></a>
        <?php endforeach; ?>
    </p>
</div>

<div class="hilfe-seite">
    <nav class="karte hilfe-inhalt" aria-label="Inhalt">
        <strong>Inhalt</strong>
        <ol><?php foreach ($abschnitte as $anker => $titel): ?><li data-anker="<?= $anker ?>"><a href="#<?= $anker ?>"><?= e($titel) ?></a></li><?php endforeach; ?></ol>
    </nav>
    <div>
        <section class="karte hilfe-abschnitt" id="ablauf">
            <h2>Ablauf eines Turniers</h2>
            <ol>
                <li><strong>Turnier anlegen</strong> (Startseite → Neues Turnier): Datum, Ort, Anzahl Tatamis, Mindestgraduierung, Meldeschluss. Der <strong>Einrichtungs-Assistent</strong> führt danach durch Klassen, Tatamis und PINs, Online-Anmeldung, Zahlungsangaben und Coaching-Portal (jeder Schritt überspringbar; später in der Übersicht erneut aufrufbar).</li>
                <li><strong>Klassen</strong> aus einer Vorlage laden und anpassen (<?= $seite('klassen', 'Klassen') ?>).</li>
                <li><strong>Teilnehmer</strong> sammeln: Online-Anmeldung der Dojos (<?= $seite('anmeldungen', 'Anmeldungen') ?>), Import oder manuell.</li>
                <li><strong>Hinweise</strong> in der Übersicht abarbeiten, kleine Klassen zusammenlegen.</li>
                <li><strong>Auslosen</strong> und den <?= $seite('plan', 'Tatami-Plan') ?> erstellen, Listen drucken.</li>
                <li><strong>PINs</strong> für Tatamis und Leitung vergeben (<?= $seite('einstellungen', 'Einstellungen') ?>).</li>
                <li>Am Turniertag <strong>„▶ Turnier starten“</strong> (Übersicht). Ausrufer und Tablets arbeiten je Tatami.</li>
                <li>Fertige Klassen <strong>bestätigen</strong>, am Ende <strong>„■ Turnier abschließen“</strong>, Siegerliste drucken, sichern.</li>
            </ol>
        </section>

        <section class="karte hilfe-abschnitt" id="phasen">
            <h2>Turnierphasen</h2>
            <p class="wo">Wo: Turnier-Übersicht, oben · aktuelle Phase auch in der Kopfzeile</p>
            <ul>
                <li><strong>Vorbereitung</strong> – alles änderbar, Online-Anmeldung zu, Ergebnisse gesperrt, Statusseite: „Turnier beginnt am …“.</li>
                <li><strong>Meldephase</strong> – die Online-Anmeldung ist offen (bis zum Meldeschluss).</li>
                <li><strong>Turnier läuft</strong> – nur jetzt nehmen Tablets und PC Ergebnisse an; Statusseite live.</li>
                <li><strong>Abgeschlossen</strong> – Ergebnisse gesperrt; „Wieder öffnen“ für Korrekturen.</li>
            </ul>
            <p>Vor dem Start prüft die Software: alle Klassen ausgelost und verteilt, PINs vergeben, keine offenen Anmeldungen. Auslosen und Verteilen erledigt „Turnier starten“ auf Wunsch selbst (Haken voreingestellt; leere Klassen und Klassen unter der Mindestanzahl bleiben außen vor). Andere offene Punkte nur mit „trotzdem umschalten“.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="anmeldung">
            <h2>Online-Anmeldung der Dojos</h2>
            <p class="wo">Wo: <?= $seite('anmeldungen', 'Anmeldungen') ?> · Seite für die Dojos: Anmeldelink <code>/melden/&lt;Schlüssel&gt;</code> (nicht erratbar, auf der Seite „Anmeldungen“ erneuerbar)</p>
            <ol>
                <li>Startgeld und Zahlungshinweis eintragen, in der Übersicht <strong>„Meldephase starten“</strong>.</li>
                <li>Den Link an die Dojos schicken. Sie tragen Ansprechperson, Teilnehmer (mit Disziplinen) und Teams ein und sehen den Gesamtpreis.</li>
                <li>Nach dem Absenden erhält das Dojo eine <strong>druckbare Übersicht</strong> und einen persönlichen Bearbeitungslink (Änderungen bis zur Freigabe bzw. zum Meldeschluss).</li>
            </ol>
        </section>

        <section class="karte hilfe-abschnitt" id="startgeld">
            <h2>Startgeld</h2>
            <p>Preis je Einzelstart (Kata, Kumite) und je Team (Kata-Team, Kumite-Team). Wer Kata und Kumite läuft, zahlt zweimal; Teams zahlen zusätzlich zu den Einzelstarts ihrer Mitglieder. Der Hinweistext (Bankverbindung, Verwendungszweck, Frist) steht auf der Anmeldeseite und auf der Druckübersicht.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="zahlung">
            <h2>Bearbeitungsstand und Zahlung</h2>
            <p class="wo">Wo: Anmeldungen → Anmeldung öffnen → Zahlung</p>
            <p><strong>Zugang für das Dojo</strong> (nur mit E-Mail-Versand): Im Anmeldeformular kann das Dojo ein Konto anlegen; nach Bestätigung der E-Mail-Adresse sieht es unter „Meine Meldungen“ den Stand. Der Zugang gilt bis X Monate nach dem Turnier (System, Standard 3) und wird dann gelöscht – je Turnier ist eine neue Anmeldung nötig. Starter, Ergebnisse und Portal des Dojos erst nach Zuordnung bei der Freigabe („Zugang … dem Dojo zuordnen“). Mails zum Stand gehen an die Adresse der Anmeldung.</p>
            <p>Das Dojo sieht auf seiner Anmeldeübersicht die Schritte <strong>Anmeldung eingegangen → Zahlungsaufforderung → Zahlung eingegangen → freigegeben</strong>. Die <strong>Zahlungsaufforderung</strong> entsteht beim Absenden automatisch (Betrag, Frist, Verwendungszweck, Bankverbindung aus Rechnungen → Angaben); ändert das Dojo den Betrag, gibt es automatisch eine neue Version, Gezahltes wird verrechnet. Den <strong>Zahlungseingang</strong> erfasst die Leitung in der Anmeldung. Optional: <strong>Freigabe erst nach Zahlungseingang</strong>. Eine <strong>Rechnung</strong> ist nicht vorgeschrieben – nach der Zahlung gilt die Übersicht als Anmelde- und Zahlungsbestätigung; auf Wunsch „Rechnung erstellen“ bzw. Dojos per Einstellung selbst abrufen lassen.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="rechnungen">
            <h2>Rechnungen an die Dojos</h2>
            <p class="wo">Wo: Anmeldungen → Rechnungen</p>
            <p>Je Dojo eine Rechnung über das Startgeld – berechnet wie die Startgeld-Übersicht im Coaching-Portal (gemeldete Teilnehmer und Teams, ohne Abmeldungen und Nicht-Erschienene). Einmal je Turnier <strong>Absender</strong>, <strong>Bankverbindung</strong>, Zahlungsziel und ggf. Steuerhinweis eintragen. Die <strong>Rechnungsnummer</strong> (Präfix + Turnier + Verein) bleibt fest, auch wenn später weitere Dojos dazukommen. „Alle Rechnungen drucken“ gibt je Dojo ein A4-Blatt aus (Drucken bzw. als PDF speichern); einzeln über „Rechnung ↗“.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="freigabe">
            <h2>Anmeldungen freigeben</h2>
            <p class="wo">Wo: Anmeldungen → Anmeldung öffnen → Entscheidung</p>
            <ul>
                <li><strong>Freigeben</strong> legt Verein, Teilnehmer und Teams an und ordnet sie automatisch den Klassen zu. Gleiche Person (Name + Geburtsdatum) wird aktualisiert statt doppelt angelegt.</li>
                <li><strong>Vor der Auslosung</strong> freigeben – in bereits ausgeloste Klassen wird nicht automatisch eingetragen.</li>
                <li><strong>Ablehnen</strong> mit Grund (sieht das Dojo); „Wieder öffnen“ macht das rückgängig. Druckansicht: 🖨.</li>
            </ul>
        </section>

        <section class="karte hilfe-abschnitt" id="teilnehmer">
            <h2>Teilnehmer</h2>
            <p class="wo">Wo: <?= $seite('teilnehmer', 'Teilnehmer') ?></p>
            <ul>
                <li>Die Klasse ergibt sich aus Geschlecht, <strong>Alter am Turniertag</strong> und Graduierung; bei Kumite optional die Kampfform.</li>
                <li>Status <strong>gesperrt</strong> (z. B. Wettkampfarzt), <strong>abgemeldet</strong> oder <strong>nicht erschienen</strong>: alle offenen und künftigen Kämpfe gehen kampflos (Kiken) an die Gegner.</li>
                <li><strong>Nicht erschienen kennzeichnen</strong>: in der Liste per Knopf „nicht erschienen“ oder auf der Teilnehmerseite – dort optional Klassen ohne Ergebnis ohne den Starter neu auslosen. Filter „Status“ zeigt z. B. alle nicht Erschienenen.</li>
                <li>„Zuordnung aktualisieren“ (Klassen) ordnet alle Meldungen neu zu, z. B. nach Änderung einer Klasse.</li>
            </ul>
        </section>

        <section class="karte hilfe-abschnitt" id="import">
            <h2>Import (XLSX/CSV)</h2>
            <p>Teilnehmer → Import: Vorlage herunterladen, ausfüllen, hochladen, <strong>Vorschau prüfen</strong>, übernehmen. Spalten u. a. Name, Vorname, Geschlecht, Geburtsdatum oder Alter, Dojo, Graduierung, Kata/Kumite Einzel, Kata/Kumite Team.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="teams">
            <h2>Teams</h2>
            <p class="wo">Wo: <?= $seite('teams', 'Teams') ?></p>
            <ul>
                <li>Ein Team startet in der Altersklasse des <strong>ältesten Mitglieds</strong>; 3 Stammmitglieder, optional Ersatz.</li>
                <li><strong>2:1-Regel:</strong> ein Mitglied darf für einen anderen Verein starten (beim Hinzufügen den Verein wählen).</li>
                <li>Das <strong>Suchfeld</strong> findet Team, Verein, Mitglied oder Klasse; mehrere Wörter müssen alle passen.</li>
            </ul>
        </section>

        <section class="karte hilfe-abschnitt" id="klassen">
            <h2>Klassen</h2>
            <p class="wo">Wo: <?= $seite('klassen', 'Klassen') ?></p>
            <ul>
                <li><strong>Vorlage laden</strong> (Gruppenschema A–P, DJKB-Standard) und Klassen anpassen.</li>
                <li>Filter <strong>Besetzung</strong>: mit Teilnehmern · findet statt · fällt aus (zu wenige Starter) · ohne Teilnehmer.</li>
                <li>Umschalter <strong>Karten / Tabelle</strong>: Die Tabelle zeigt Starter, Mindestanzahl, „findet statt“, Status und Tatami.</li>
            </ul>
        </section>

        <section class="karte hilfe-abschnitt" id="zulassung">
            <h2>Zulassung einer Klasse</h2>
            <p>Geschlecht, Alter von/bis (am Turniertag), Graduierung von/bis bestimmen, wer automatisch zugeordnet wird. <strong>Erlaubte Kata</strong> erscheinen am Tablet zur Auswahl. <strong>Exklusiv-Gruppe</strong>: In Klassen derselben Gruppe darf man nur einmal starten (z. B. Jiyu-Ippon <em>oder</em> Jiyu-Kumite).</p>
        </section>

        <section class="karte hilfe-abschnitt" id="modus">
            <h2>Modus, Wertung, Mindestanzahl</h2>
            <ul>
                <li><strong>K.-o.</strong>: 2 Starter ein Kampf, 3 jeder gegen jeden, ab 4 Baum mit Kampf um Platz 3. Außerdem Pools + Finalrunde, Punkte-Vorrunde + Finale, jeder gegen jeden.</li>
                <li><strong>Wertung</strong>: Flaggen, Punkte (Kata 0,0–9,9, Streichwertung ab 5 Kampfrichtern), Kumite Shobu Ippon oder Shobu Sanbon (zwei volle Punkte, z. B. im Finale). Strafen nach der Wettkampfordnung: je Regelverstoß, Jogai und Mubobi Keikoku → Hansoku Chui → Hansoku, ohne Punkte für den Gegner. Bei Gleichstand Saishiai (Verlängerung) oder Hantei. Kumite-Team mit 3, 5 oder 7 Einzelkämpfen.</li>
                <li><strong>Mindestanzahl Starter</strong> (mind. 2): darunter findet die Klasse nicht statt – keine Auslosung, Hinweis in der Übersicht. Eine Klasse mit nur einem Starter nimmt nie teil.</li>
                <li><strong>Bleibt auf derselben Tatami</strong>: alle Runden auf einer Fläche.</li>
            </ul>
        </section>

        <section class="karte hilfe-abschnitt" id="abweichungen">
            <h2>Abweichungen ab einer Runde</h2>
            <p>Andere Kumite-Form, Kata-Liste oder Wertung ab „letzte 8“, „letzte 4“ oder im Finale – z. B. „Finale: Kata frei“. Wird beim Auslosen je Kampf festgeschrieben.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="zusammenlegung">
            <h2>Kleine Klassen zusammenlegen</h2>
            <p>Klassen unter der Mindestanzahl sind rot markiert, mit Vorschlag „💡 zusammenlegen mit …“. In der Klasse unter <strong>Zusammenlegung</strong> Zielklassen anhaken, Grund und Zustimmung vermerken. „Aufheben“, solange nicht ausgelost ist.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="auslosung">
            <h2>Auslosung</h2>
            <ul>
                <li>Je Klasse <strong>„Jetzt auslosen“</strong> oder in Klassen „Alle offenen auslosen“ – bzw. automatisch beim Turnierstart.</li>
                <li>Reproduzierbar: Seed und Protokoll in der Klasse. Freilose nur in Runde 1, gleichmäßig verteilt; Starter desselben Dojos möglichst spät gegeneinander.</li>
                <li><strong>Neu auslosen / Zurücknehmen</strong>, solange in der Klasse kein Ergebnis steht.</li>
            </ul>
        </section>

        <section class="karte hilfe-abschnitt" id="tatami-plan">
            <h2>Tatami-Plan</h2>
            <p class="wo">Wo: <?= $seite('plan', 'Tatami-Plan') ?></p>
            <ul>
                <li><strong>Verteilung dynamisch</strong> (Standard): jede Tatami hat nur ihre aktuelle Gruppe; ist deren letzter Kampf entschieden, bekommt sie sofort die nächste aus der <strong>Warteschlange</strong> – erst Kata, dann Kumite, jüngere bzw. niedrigere Klassen zuerst (Reihenfolge per Ziehen änderbar). Sind alle Kata gelaufen, meldet die Kopfzeile „☕ Kata beendet“ (Gelegenheit für eine Pause). <strong>Fester Plan</strong>: „Offene automatisch verteilen“ verteilt alles vorab.</li>
                <li>Je Tatami <strong>„🔒 Sperren …“</strong> (mit Grund): laufende Gruppe läuft zu Ende, danach keine neuen Gruppen; Unbegonnenes geht zurück in die Warteschlange. „🔓 Freigeben“ hebt die Sperre auf.</li>
                <li><strong>Nachricht an die Tische</strong> (eine oder alle Tatamis): erscheint auf Tablet und Ausruf-Handy über der Kopfzeile, stört die Eingabe nicht und bleibt bis „✓ Gelesen“; die Liste zeigt die Bestätigung.</li>
                <li>Karten per Maus ziehen oder mit den Pfeilen verschieben. Beendete Gruppen sind je Spalte eingeklappt („✓ … beendete Gruppen“); „Beendete Gruppen einblenden“ zeigt sie alle. „Plan leeren“ nimmt nur nicht begonnene Gruppen von den Tatamis.</li>
                <li>Beginn und Minuten je Kampf einstellen – daraus werden Startzeiten geschätzt.</li>
                <li>Je Tatami: <strong>Tisch-Ansicht ↗</strong> und <strong>Ausruf ↗</strong> als Leitung öffnen.</li>
            </ul>
        </section>

        <section class="karte hilfe-abschnitt" id="zugaenge">
            <h2>PINs und Zugänge</h2>
            <p class="wo">Wo: <?= $seite('einstellungen', 'Einstellungen') ?> → Tatamis &amp; Zugänge</p>
            <ul>
                <li>Je Tatami eine <strong>PIN</strong> (4–8 Ziffern) – gilt für Tablet und Ausruf-Handy. Nach 5 Fehlversuchen 60 Sekunden Sperre.</li>
                <li><strong>Leitungs-PIN</strong>: Korrekturen und „Ergebnis löschen“ am Tablet nur mit ihr.</li>
            </ul>
        </section>

        <section class="karte hilfe-abschnitt" id="tablet">
            <h2>Tablet der Tischbesatzung</h2>
            <p>Unter <code>/tisch</code> Tatami wählen, Ansicht „Eingabe“, PIN. Der nächste Kampf <strong>beginnt automatisch</strong> (Statusseite „Jetzt tritt an“); bei Bedarf <strong>„⏸ Kampf unterbrechen“</strong> und <strong>„▶ Kampf fortsetzen“</strong> – das Ergebnis lässt sich auch im unterbrochenen Zustand eintragen. Ergebnis eintragen (Flaggen, Punkte, Ippon/Wazaari mit Verwarnungen, Kampfzeit abgelaufen, Hantei, Kampflos) und <strong>„Ergebnis senden“</strong>. Bei Kumite beendet ein <strong>Ippon</strong> den Kampf: Es erscheint sofort „Ippon – … gewinnt“ mit „Kampf beenden – Ergebnis senden“. Korrekturen rechts unter „Zuletzt eingetragen“ – mit Leitungs-PIN, auch „Ergebnis löschen – Kampf neu austragen“. Ist eine Gruppe fertig, zeigt das Tablet die Platzierungen; nach <strong>„Gruppe abschließen“</strong> folgt der Hinweis, die nächste Gruppe auszurufen.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="ausruf">
            <h2>Ausruf (Handy)</h2>
            <p>Unter <code>/tisch</code> Ansicht <strong>„Ausruf“</strong> wählen (gleiche Tatami-PIN), als Web-App „Zum Startbildschirm hinzufügen“. Je Gruppe jeden Starter in der Abhakliste mit <strong>„✓ da“</strong> oder <strong>„✗ fehlt“</strong> abhaken, dann <strong>„Ausruf Gruppe … abschließen“</strong>. Das Tablet zeigt den Stand und fragt nach, wenn ein Kampf ohne abgeschlossenen Ausruf beginnt. Solange der Ausruf der aktuellen Gruppe läuft, steht die <strong>Abhakliste oben</strong>; <strong>„Jetzt auf der Tatami“</strong> und <strong>„Bereit machen“</strong> (die Begegnung direkt danach) erscheinen erst, wenn der Ausruf der Gruppe abgeschlossen ist – auch wenn ein Starter fehlt.</p>
            <p><strong>Einmal ausgerufen genügt:</strong> Halbfinale, Kampf um Platz 3, Finale und Finalrunde werden nicht erneut ausgerufen, wenn die Starter in der Klasse schon „da“ waren (abgeschlossener Ausruf) oder dort schon gekämpft haben. Bei <strong>Jiyu-Kumite</strong> weist das Tablet darauf hin, wenn jemand direkt nach seinem Kampf wieder antritt – bitte nach einer Pause fragen.</p>
            <p>Meldet der Ausrufer einen Starter als <strong>„fehlt“</strong>, startet der Kampf nicht automatisch: Am Tablet bestätigt die Tischbesatzung <strong>„Kiken – Gegner gewinnt kampflos“</strong> (weiter zum nächsten Kampf) oder <strong>„… ist doch da – Kampf beginnen“</strong>.</p>
            <p>Am Tablet zeigt <strong>„📣 Ausruf … · x von y da“</strong> oben rechts in der Seitenleiste (solange der Ausruf der Gruppe läuft), wer schon da ist und wer fehlt. Ohne Ausrufer kann die Tischbesatzung mit <strong>„Notfall: am Tablet abhaken“</strong> selbst „da“/„fehlt“ setzen und den Ausruf der Gruppe abschließen.</p>
            <p>Die Starter einer neuen Gruppe sieht der Ausrufer erst, wenn die Tischbesatzung am Tablet <strong>„📣 Ausruf starten“</strong> tippt (unter den Bedienknöpfen, auf der Seite „Gruppe fertig“ und nach dem Abschluss) – am besten schon während der letzten Kämpfe der laufenden Gruppe. Bis dahin zeigt die Ausruf-Ansicht nur „Nächste Gruppe … – die Namen erscheinen, sobald die Tischbesatzung den Ausruf startet“. Die laufende Gruppe ist immer sichtbar.</p>
            <p><strong>„📣 Gruppe aufrufen“</strong> setzt alle Starter der Gruppe auf die Statusseite („Bitte zur Tatami“), bis sie „da“ sind; gesperrt, solange ein Starter noch auf einer anderen Tatami im Wettkampf ist. Der Ausrufer sieht wie Tablet und Statusseite <strong>AKA</strong> (rot, immer oben) und <strong>SHIRO</strong> (weiß), dazu die Aufstellung <strong>links/rechts</strong> (Einstellungen → Tatamis &amp; Zugänge: „AKA rechts/links“). Die Statusseite zeigt je Tatami „Jetzt tritt an“ und „Bereit machen“ (AKA rot, SHIRO weiß), Vollbild unter <code>/live/&lt;Nr&gt;/tatami/&lt;Tatami&gt;</code>.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="nicht-erschienen">
            <h2>Nicht erschienen</h2>
            <p class="wo">Wo: Kopfzeile „⚠ … nicht erschienen“ → Turnier-Übersicht, oben</p>
            <p>Jedes „fehlt“ aus einem abgeschlossenen Ausruf meldet sich bei der Leitung. Weil ein Starter gerade auf einer anderen Tatami sein kann (Kata und Kumite parallel), entscheidet die Leitung:</p>
            <ul>
                <li><strong>Ist doch da</strong> – Meldung erledigt.</li>
                <li><strong>Kiken nur hier</strong> – nur dieser Kampf/Vortrag kampflos.</li>
                <li><strong>Aus allen Wettkämpfen</strong> – Teilnehmer „nicht erschienen“, alle offenen und künftigen Kämpfe Kiken (Teams: nur der Team-Start).</li>
                <li><strong>… und Klasse neu auslosen</strong> – zusätzlich neue Paarungen ohne den Starter (nur ohne Ergebnisse in der Klasse).</li>
            </ul>
        </section>

        <section class="karte hilfe-abschnitt" id="korrektur">
            <h2>Eingabe und Korrektur am PC</h2>
            <p>In der Klasse beim Kampf <strong>Ergebnis</strong> bzw. <strong>Korrigieren</strong>. Ergebnisse nur, während das Turnier läuft. Korrigierbar, solange der Folgekampf nicht entschieden und die Klasse nicht bestätigt ist; „Ergebnis zurücksetzen“ macht den Kampf wieder offen. Alles wird protokolliert.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="ergebnisse">
            <h2>Ergebnisse und Bestätigung</h2>
            <p class="wo">Wo: <?= $seite('ergebnisse', 'Ergebnisse') ?></p>
            <p>Ist eine Klasse fertig: <strong>Bestätigen</strong> – erst dann erscheinen die Platzierungen in Siegerliste und auf der Statusseite. Bei Gleichstand (jeder gegen jeden, Punkte) fragt die Klasse nach einem <strong>Stichentscheid</strong>. „Bestätigung aufheben“ für spätere Korrekturen.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="hinweise">
            <h2>Hinweise in der Übersicht</h2>
            <p>Klassen unter der Mindestanzahl, Meldungen ohne passende Klasse, Doppel- und Exklusivmeldungen (z. B. D2A und D2B), Team-Regeln. Ein Klick öffnet die Klasse bzw. den Teilnehmer.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="druck">
            <h2>Druck</h2>
            <p class="wo">Wo: <?= $seite('druck', 'Druck') ?></p>
            <p>Kampflisten je Tatami (mit Feldern für die Tischbesatzung), Turnierbäume und Pool-Listen, Ergebnis- und Siegerliste, Starter nach Verein. Druck über den Browser, auch als PDF; im Druckdialog „Kopf- und Fußzeilen“ ausschalten.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="statusseite">
            <h2>Statusseite</h2>
            <p><code>/live/&lt;Turnier-Nr&gt;</code> für Beamer und Zuschauer: was läuft je Tatami, Starterlisten und Bäume (auch als Offlinekopie), bestätigte Ergebnisse. Nur Name und Verein – keine Geburtsdaten.</p>
            <p><strong>Stream-Einblendung</strong> (Einstellungen → Tatamis &amp; Zugänge → „öffnen ↗“): <code>/live/&lt;Turnier-Nr&gt;/einblendung/&lt;Tatami&gt;</code> in OBS als <em>Browserquelle</em> (1920×1080) einbinden. Zeigt den laufenden Kampf (AKA/SHIRO) bzw. Kata-Vortrag als Bauchbinde und nach dem Ende 20 Sekunden lang Sieger bzw. Wertung; Hintergrund transparent. Zusätze: <code>?pos=oben</code>, <code>?hintergrund=gruen</code> (Chroma-Key), <code>?ergebnis=0</code>. Voraussetzung: Statusseite öffentlich.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="vereine">
            <h2>Vereine / Dojos</h2>
            <p>Menü <strong>Vereine</strong> (nur Administration): eine gemeinsame Liste für alle Turniere. Angelegt werden Vereine meist automatisch – bei der <strong>Freigabe einer Online-Anmeldung</strong>, beim <strong>Import</strong> und beim Erfassen eines Teilnehmers (gleiche Namen ohne Rücksicht auf Groß-/Kleinschreibung zählen als derselbe Verein). Von Hand nur nötig, um ein Coach-Konto für ein Dojo anzulegen, das noch nie gemeldet hat.</p>
            <p><strong>Name</strong> korrigieren wirkt überall, auch in laufenden Turnieren. Der <strong>Kurzname</strong> (optional, z. B. „KD Bergheim“) erscheint, wo der Platz knapp ist – Turnierbaum, Tablet, Ausruf, Statusseite; Urkunden, Sieger- und Ergebnislisten zeigen den vollen Namen.</p>
            <p><strong>Doppelte Vereine</strong> (z. B. „Karate Dojo Bergheim“ und „Karate-Dojo Bergheim“) schlägt die Seite selbst vor. „Zusammenführen …“ zeigt vorher, wie viele Athleten, Teams, Anmeldungen und Coach-Konten umziehen; danach ist der doppelte Eintrag gelöscht (im Änderungsverlauf festgehalten, nicht rückgängig zu machen).</p>
            <p>Ein Klick auf den <strong>Vereinsnamen</strong> öffnet die Vereinsseite: Teilnehmer mit Jahrgang, Graduierung und den Turnieren, in denen sie gemeldet sind bzw. waren (Link zum Teilnehmer im Turnier), Coach-Konten und Online-Anmeldungen. Personen verschwinden mit dem letzten Turnier, in dem sie gemeldet sind.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="sicherung">
            <h2>Sicherung und Umzug</h2>
            <p>Einstellungen → <strong>Turnier exportieren</strong> (JSON, auch für den Umzug auf den Webserver) bzw. Menü <strong>Sicherung</strong> → Datenbank herunterladen; dort auch „Turnier importieren“. Am Turniertag regelmäßig sichern.</p>
            <p><strong>Automatische Sicherung</strong> (lokal bzw. Hallen-Laptop mit SQLite): Während des Betriebs legt die Software höchstens alle 2 Minuten – und nur nach Änderungen – eine Kopie der Datenbank unter <code>data/sicherungen/</code> ab und behält die letzten 30. Unter <strong>Sicherung</strong> lässt sich daraus ein einzelnes Turnier <strong>als Kopie wiederherstellen</strong> (das aktuelle bleibt unberührt) oder ein Stand herunterladen. „Jetzt sichern“ legt sofort einen Stand an, z. B. vor einem riskanten Schritt.</p>
            <p><strong>WLAN-Aussetzer am Tablet:</strong> Jede Aktion trägt eine eindeutige Befehls-ID und wird bei fehlender Antwort automatisch wiederholt. Kommt sie doppelt an, zählt sie trotzdem nur einmal – „Erneut senden“ ist immer gefahrlos.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="feedback">
            <h2 id="system">System und Feedback</h2>
            <p>Menü <strong>System</strong>: Einstellungen für alle Turniere – <strong>Farbschema</strong> der Oberfläche (sieben Vorlagen oder eigene Farbe; gilt für Leitung, Tablets, Statusseite und Online-Anmeldung), <strong>Vereins- und Dojo-Logo</strong> in der Kopfzeile der Statusseite (Logos je Turnier unter Druck → Urkunden), <strong>Starterlisten und Ergebnisse</strong> der Statusseite ein-/ausblenden (beide aus: nur Tatamis, volle Breite) der <strong>E-Mail-Versand</strong> (Postfach für Bestätigungslinks, „Passwort vergessen“ und Mails zur Anmeldung – Passwort verschlüsselt gespeichert, Änderung nur mit dem eigenen Passwort, „Testmail an mich senden“), der <strong>Feedback-Knopf</strong> und der <strong>Demo-Modus</strong> (eingeschaltet: „Demo-Turnier anlegen“ – in Vorbereitung, laufend oder abgeschlossen, wahlweise mit Teams). Eingeschaltet erscheint er unten rechts auf allen Seiten der Wettkampfleitung und unten links (klein) auf den Tablets von Tisch und Ausruf.</p>
            <p>Ein Klick nimmt einen Screenshot des sichtbaren Bereichs auf und öffnet ein Formular: Art (Fehler, Verbesserungsvorschlag, Frage), Beschreibung; Seite und Screenshot gehen automatisch mit (der Screenshot lässt sich abwählen). Die Meldung geht <strong>per Mail an feedback@taikai-karate.de</strong> (Team von Taikai – Karate) und bleibt zusätzlich hier gespeichert – der Knopf setzt deshalb einen eingerichteten E-Mail-Versand voraus.</p>
            <p>Menü <strong>Feedback</strong> (Zähler = neue Meldungen): filtern nach Status und Art, Status und Notiz pflegen (speichert automatisch), löschen. <strong>Export</strong> der Meldungen im Status „neu“ als ZIP (feedback.json, Screenshots als Bilder, uebersicht.html) oder als JSON (Screenshots eingebettet) – nach der Bearbeitung den Status ändern, dann kommen sie beim nächsten Export nicht erneut.</p>
        </section>

        <section class="karte hilfe-abschnitt" id="fragen">
            <h2>Häufige Fragen</h2>
            <table class="tabelle">
                <tbody>
                <tr><td>Tablet: „Das Turnier ist noch nicht gestartet“</td><td>Übersicht → „▶ Turnier starten“.</td></tr>
                <tr><td>Eine Tatami bekommt keine Kämpfe</td><td>Klassen ausgelost und im Tatami-Plan verteilt? Bei laufendem Turnier: Übersicht → „Offene Klassen auslosen und verteilen“.</td></tr>
                <tr><td>Anmeldeseite „derzeit nicht geöffnet“</td><td>Übersicht → „Meldephase starten“; Meldeschluss und Turnierdatum prüfen.</td></tr>
                <tr><td>Freigabe meldet „bereits ausgelost“</td><td>Start in der Klasse manuell nachtragen oder die Klasse neu auslosen (solange ohne Ergebnis).</td></tr>
                <tr><td>Klasse lässt sich nicht auslosen</td><td>Unter der Mindestanzahl – zusammenlegen oder Mindestanzahl anpassen; oder es gibt schon Ergebnisse.</td></tr>
                <tr><td>Korrektur am Tablet abgelehnt</td><td>Leitungs-PIN nötig; Folgekampf schon entschieden → dort zuerst korrigieren.</td></tr>
                <tr><td>Ergebnis versehentlich gesendet</td><td>Tablet → Korrigieren → „Ergebnis löschen – Kampf neu austragen“.</td></tr>
                <tr><td>Tablet zeigt „offline“</td><td>WLAN prüfen; das Ergebnis wurde nicht übertragen – erneut senden.</td></tr>
                <tr><td>Dojo hat den Bearbeitungslink verloren</td><td>Anmeldungen → Anmeldung öffnen → Link unter „Kontakt“.</td></tr>
                </tbody>
            </table>
        </section>
        <div class="karte leer" id="hilfe-nichts" hidden>Nichts gefunden.</div>
    </div>
</div>
<script src="<?= e(asset('hilfe.js')) ?>" defer></script>
