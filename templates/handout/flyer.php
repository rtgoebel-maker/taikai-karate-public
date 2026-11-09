<?php
/**
 * Flyer „Überblick“ (5 Seiten A4) zum Weitergeben an Vereine, Veranstalter und Interessierte.
 * Reihenfolge wie ein Turnier: 1 Vorbereitung mit Baukasten · 2 Anmeldung, Klassen, Auslosung ·
 * 3 Turniertag: Wettkampfleitung, Tischbesatzung, Ausrufer · 4 Coaches, Zuschauer, nach dem
 * Turnier · 5 Sicherheit, Notfall-Konzept, Betrieb, Hilfe.
 */
$bild = static fn (string $datei, string $text, string $klasse = '') => '<figure class="ho-bild' . ($klasse !== '' ? ' ' . $klasse : '') . '"><img src="' . e(asset('handout/' . $datei)) . '" alt="' . e($text) . '"><figcaption>' . e($text) . '</figcaption></figure>';
$kopf = static fn (string $titel, string $seite) => '<div class="ho-kopf"><div><div class="ho-art">Taikai – Karate</div><h1 class="fl-seitentitel">' . e($titel) . '</h1></div><div class="ho-stand">' . e($seite) . '</div></div>';
$rolle = static fn (string $wer, string $text) => '<h2 class="fl-rolle"><span>' . e($wer) . '</span>' . e($text) . '</h2>';
?>
<div class="blatt fl">
    <header class="fl-held">
        <div class="fl-marke"><img src="<?= e(asset('logo/taikai-logo.svg')) ?>" alt="Taikai – Karate" width="188" height="60"><span>taikai-karate.de</span></div>
        <h1>Turniere organisieren –<br>von der Anmeldung bis zur Urkunde</h1>
        <p class="fl-unterzeile">Für Kata, Kumite, Kata-Team und Kumite-Team nach der DJKB-Wettkampfordnung 2024. Im Browser auf PC, Tablet und Handy – keine App, keine Installation auf den Geräten.</p>
    </header>

    <?= $bild('flyer-status.jpg', 'Am Turniertag: Statusseite live – wer ist auf welcher Tatami dran, wer macht sich bereit, wer wird aufgerufen', 'fl-gross') ?>

    <ol class="fl-ablauf">
        <li><strong>Vorbereiten</strong><span>Turnier, Klassen, Tatamis, Funktionen wählen</span></li>
        <li><strong>Anmelden</strong><span>Dojos melden selbst im eigenen Bereich</span></li>
        <li><strong>Einteilen &amp; Auslosen</strong><span>automatisch, fair, nachvollziehbar</span></li>
        <li><strong>Turniertag</strong><span>Leitung, Tisch, Ausruf, Coaches, Zuschauer</span></li>
        <li><strong>Ehren</strong><span>Platzierungen, Siegerliste, Urkunden</span></li>
    </ol>

    <h2><span class="ho-nr">1</span>Vorbereitung</h2>
    <div class="fl-zwei">
        <ul>
            <li><strong>Turnier anlegen</strong>: Datum, Ort, Anzahl Tatamis, Mindestgraduierung.</li>
            <li><strong>Klassen aus Vorlage</strong> (DJKB-Standard oder eigenes Schema) mit Regeln je Runde, z. B. Kata im Finale frei.</li>
            <li><strong>Tatamis &amp; Zugänge</strong>: eine PIN je Tatami für die Tablets, eine Leitungs-PIN für Korrekturen; weitere Turnierleiter ernennen.</li>
        </ul>
        <ul>
            <li><strong>Turnierphasen</strong> führen durch den Ablauf: Vorbereitung → Meldephase → Turnier läuft → Abgeschlossen. Ergebnisse gibt es nur, solange das Turnier läuft.</li>
            <li><strong>Demo-Modus</strong> zum Ausprobieren und für Schulungen der Tischbesatzung.</li>
        </ul>
    </div>

    <div class="fl-baukasten">
        <h3>Baukasten: Funktionen nach Wunsch ein- und ausschalten</h3>
        <p>Jedes Turnier nutzt nur, was es braucht – vom kleinen Vereinsturnier bis zur großen Meisterschaft. Alles per Haken in den Einstellungen, jederzeit änderbar.</p>
        <table>
            <tr><td><strong>Online-Anmeldung</strong></td><td>Meldephase öffnen und schließen, Meldeschluss, Startgeld je Start und Team</td></tr>
            <tr><td><strong>Coaching-Portal</strong></td><td>an/aus je Turnier; einzeln schaltbar: Starter live, Ergebnisse, Korrekturanträge, Startgeld</td></tr>
            <tr><td><strong>Statusseite</strong></td><td>öffentlich ja/nein; Starterlisten &amp; Bäume, Ergebnisse, Logos des Ausrichters</td></tr>
            <tr><td><strong>Kampfuhr am Tablet</strong></td><td>an/aus, Kampfzeit und Finalzeit, Signalton</td></tr>
            <tr><td><strong>Tatami-Verteilung</strong></td><td>dynamisch (freie Tatami holt die nächste Gruppe) oder fester Plan; Kata und Kumite getrennt</td></tr>
            <tr><td><strong>Hallen-Laptop</strong></td><td>nur bei Bedarf: Turnier für den Tag an einen Laptop in der Halle übergeben</td></tr>
            <tr><td><strong>Weiteres</strong></td><td>Seite von AKA am Tisch, eigene Farbe, Feedback-Knopf, Demo-Modus</td></tr>
        </table>
    </div>
</div>

<div class="blatt fl">
    <?= $kopf('Anmeldung, Klassen, Auslosung', 'Seite 2 von 5') ?>

    <h2 class="fl-erste"><span class="ho-nr">2</span>Anmeldebereich für Dojos</h2>
    <div class="fl-zwei fl-anmeldung">
        <div>
            <?= $bild('flyer-meldungen.jpg', 'Anmeldebereich „Meine Meldungen“ eines Dojos') ?>
        </div>
        <div>
            <p>Jedes Dojo meldet seine Teilnehmer selbst – niemand muss Listen abtippen.</p>
            <ul>
                <li><strong>Mit Dojo-Konto</strong> unter <em>Meine Meldungen</em>: alle Turniere mit offener Anmeldung, die eigenen Meldungen mit Status und Startgeld, Übersicht zum Drucken. Mehrere Coaches je Dojo möglich.</li>
                <li><strong>Ohne Konto</strong> über die Turnieradresse; ein persönlicher Link erlaubt Änderungen bis zum Meldeschluss.</li>
            </ul>
        </div>
    </div>
    <div class="fl-zwei fl-anmeldung">
        <div>
            <?= $bild('flyer-anmeldeformular.jpg', 'Anmeldeformular mit Startgeld je Start und Team', 'fl-formular') ?>
        </div>
        <div>
            <ul>
                <li>Teilnehmer, Disziplinen und Teams in <strong>einem Formular</strong>; das Startgeld wird bei jeder Eingabe sofort berechnet.</li>
                <li>Die <strong>Klasse ergibt sich automatisch</strong> aus Geschlecht, Alter am Turniertag und Graduierung.</li>
                <li>Die Leitung prüft jede Meldung und gibt sie frei – erst dann werden die Teilnehmer übernommen.</li>
                <li>Alternativ <strong>Excel/CSV-Import</strong> mit Vorschau und Fehlerprüfung oder Erfassung von Hand.</li>
            </ul>
        </div>
    </div>

    <h2><span class="ho-nr">3</span>Klassen einteilen und auslosen</h2>
    <?= $bild('flyer-klassen.jpg', 'Klassenübersicht mit Starterzahl und Status je Klasse', 'fl-breit') ?>
    <div class="fl-zwei fl-mitte">
        <div>
            <?= $bild('flyer-baum.jpg', 'K.-o.-Baum mit Kampf um Platz 3') ?>
        </div>
        <div>
            <ul>
                <li><strong>Zu kleine Klassen</strong> werden markiert, mit Vorschlag zum Zusammenlegen.</li>
                <li><strong>Modi</strong>: K.-o. mit Kampf um Platz 3, Pools mit Finalrunde, Kata-Punkte mit Finale, jeder gegen jeden.</li>
                <li><strong>Auslosung</strong> per Zufall, aber protokolliert und reproduzierbar; Starter desselben Dojos treffen möglichst spät aufeinander.</li>
            </ul>
        </div>
    </div>
    <div class="ho-hinweis fl-start">
        <strong>„Turnier starten“ prüft vorher:</strong> alle Klassen ausgelost, Tatamis verteilt, PINs für die Tablets vergeben, Meldungen freigegeben – und warnt vor Klassen, die mangels Startern ausfallen würden.
    </div>
</div>

<div class="blatt fl">
    <?= $kopf('Am Turniertag – wer sieht was?', 'Seite 3 von 5') ?>

    <?= $rolle('Wettkampfleitung', 'Steuern und entscheiden') ?>
    <div class="fl-zwei fl-leitung">
        <?= $bild('leitung-uebersicht.jpg', 'Übersicht: Rückmeldungen „nicht erschienen“, Turnierphase') ?>
        <?= $bild('leitung-plan.jpg', 'Tatami-Plan: Warteschlange und Tatamis') ?>
    </div>
    <ul class="fl-liste2">
        <li><strong>Tatami-Plan</strong>: Freie Tatamis bekommen automatisch die nächste Gruppe; Reihenfolge per Maus ändern, Tatamis für eine Pause sperren.</li>
        <li><strong>Nachrichten</strong> an einzelne oder alle Tische, bis dort „gelesen“ getippt wird.</li>
        <li><strong>Nicht erschienen</strong>: doch da, Kiken für diesen Kampf oder aus allen Wettkämpfen.</li>
        <li><strong>Platzierungen bestätigen</strong>, Ergebnisse am PC korrigieren – jede Änderung steht im Verlauf.</li>
    </ul>

    <?= $rolle('Tischbesatzung', 'Ergebnisse am Tablet') ?>
    <div class="fl-zwei fl-tablets">
        <?= $bild('tablet-kumite.jpg', 'Kumite: Kampfuhr, Ippon/Wazaari, Strafen') ?>
        <?= $bild('tablet-kata.jpg', 'Kata: Wertungen der Kampfrichter') ?>
    </div>
    <ul class="fl-liste2">
        <li><strong>Kumite</strong> nach WKO 2024: Shobu Ippon oder Sanbon, Strafen (Regel, Jogai, Mubobi) mit den Rufen <em>Keikoku → Hansoku Chui → Hansoku</em>, Hantei, Saishiai, Team-Kumite.</li>
        <li><strong>Kampfuhr</strong> mit Hajime/Yame und Ato shibaraku (wenn eingeschaltet).</li>
        <li><strong>Kata</strong> mit Wertungstafeln 0,0–9,9 (Streichwertung) oder Flaggen; <em>77</em> = 7,7.</li>
        <li><strong>Falsch eingegeben?</strong> Vor dem Senden ändern, danach nur mit Leitungs-PIN korrigieren oder neu austragen.</li>
    </ul>

    <?= $rolle('Ausrufer', 'Starter aufrufen per Handy') ?>
    <div class="fl-ausruf">
        <?= $bild('ausruf-liste.jpg', 'Ausruf-Handy je Tatami', 'fl-hoch') ?>
        <div>
            <p>Startet die Tischbesatzung eine neue Gruppe, sieht der Ausrufer deren Starter mit Verein und hakt jeden ab: <strong>da</strong> oder <strong>fehlt</strong>. Ist die Gruppe abgehakt, schließt er den Ausruf ab.</p>
            <ul>
                <li>Das Tablet zeigt den Stand und weiß, wann es losgehen kann.</li>
                <li>Fehlt jemand, erfährt es die Leitung sofort.</li>
                <li>Gleichzeitig steht auf der Statusseite „Bitte zur Tatami …“.</li>
            </ul>
        </div>
    </div>
</div>

<div class="blatt fl">
    <?= $kopf('Coaches, Zuschauer, Auswertung', 'Seite 4 von 5') ?>

    <?= $rolle('Coaches', 'Coaching-Portal – nur das eigene Dojo') ?>
    <div class="fl-zwei">
        <div>
            <?= $bild('flyer-coach.jpg', 'Meine Starter live: Tatami, Runde, wie viele Kämpfe noch davor') ?>
            <?= $bild('flyer-antraege.jpg', 'Korrekturanträge mit Antwort der Turnierleitung', 'fl-schmal') ?>
        </div>
        <div>
            <?= $bild('flyer-medaillen.jpg', 'Ergebnisse des Dojos mit Medaillenspiegel', 'fl-medaillen') ?>
            <ul>
                <li><strong>Starter live</strong>: wo und wann jeder antritt, wie viele Kämpfe noch davor sind, Stand im Ausruf.</li>
                <li><strong>Ergebnisse</strong> und Medaillenspiegel des Dojos, <strong>Startgeld</strong> zum Drucken.</li>
                <li><strong>Korrekturanträge</strong> (Name, Geburtsdatum, Grad, Klasse) direkt an die Leitung – mit Antwort im Portal.</li>
            </ul>
        </div>
    </div>

    <?= $rolle('Zuschauer', 'Statusseite in der Halle und auf dem Handy') ?>
    <div class="fl-zwei">
        <?= $bild('flyer-vollbild.jpg', 'Vollbild je Tatami für Beamer oder Fernseher') ?>
        <div>
            <p>Wer <strong>jetzt antritt</strong> und wer sich <strong>bereit machen</strong> soll – je Tatami, aktualisiert sich selbst. Dazu Starterlisten, Turnierbäume, bestätigte Ergebnisse, Vereinswertung und Starter-Suche.</p>
            <p>Öffentlich sichtbar sind nur <strong>Name und Verein</strong> – keine Geburtsdaten.</p>
        </div>
    </div>

    <h2><span class="ho-nr">5</span>Nach dem Turnier</h2>
    <div class="fl-drei fl-ehrung">
        <?= $bild('flyer-urkunde.jpg', 'Urkunde je Platz') ?>
        <?= $bild('flyer-siegerliste.jpg', 'Siegerliste (A4)') ?>
        <div>
            <?= $bild('flyer-vereinswertung.jpg', 'Vereinswertung') ?>
            <p><strong>Urkunden</strong> mit Logos und Unterschriften oder als Text für Vordrucke, Siegerliste, Ergebnislisten, Starter je Verein.</p>
        </div>
    </div>
</div>

<div class="blatt fl">
    <?= $kopf('Sicherheit, Notfall-Konzept, Betrieb', 'Seite 5 von 5') ?>

    <div class="fl-kasten fl-sicher">
        <h2 class="fl-erste">🔒 Sicherheit und Datenschutz</h2>
        <p>Die Software verwaltet Daten von Kindern und Jugendlichen – Sicherheit ist von Anfang an eingebaut:</p>
        <div class="fl-zwei">
            <ul>
                <li>Geprüft nach den <strong>OWASP Top 10</strong> (2025), dem Standard für Web-Sicherheit; über 300 automatische Tests</li>
                <li><strong>Rechte je Rolle und Turnier</strong>: Turnierleiter nur für ihre Turniere, Coaches nur für ihr Dojo, Tischbesatzung nur für ihre Tatami</li>
                <li>Passwörter und PINs nur <strong>verschlüsselt</strong> (gehasht) gespeichert</li>
                <li><strong>Sperre nach Fehlversuchen</strong> bei Login und PIN, Drosselung gegen Massenanfragen</li>
            </ul>
            <ul>
                <li>Schutz vor <strong>Formular-Fälschung</strong> (CSRF) und <strong>eingeschleusten Skripten</strong> (Content-Security-Policy)</li>
                <li>Korrekturen am Tablet nur mit <strong>Leitungs-PIN</strong>; jede Änderung im <strong>Verlauf</strong>: wer, wann, was</li>
                <li>Öffentlich nur <strong>Name und Verein</strong>, keine Geburtsdaten</li>
                <li><strong>Signierte Übertragung</strong> zwischen Halle und Online, Sicherungen mit Prüfsumme</li>
            </ul>
        </div>
    </div>

    <div class="fl-kasten fl-notfall">
        <h2 class="fl-erste">🛟 Notfall-Konzept – falls die Technik versagt</h2>
        <p>Bei aller Liebe zur Technik: Ein Turnier darf nicht an einem leeren Akku oder schwachen WLAN scheitern.</p>
        <table>
            <tr><td><strong>WLAN kurz weg</strong></td><td>Das Tablet zeigt „offline“ und behält die Eingabe; sobald es wieder „online“ ist, wird erneut gesendet.</td></tr>
            <tr><td><strong>Kein Internet in der Halle</strong></td><td>Der Hallen-Laptop führt das Turnier im eigenen Hallen-WLAN; online bleibt alles sichtbar und wird nachgezogen.</td></tr>
            <tr><td><strong>Laptop fällt aus</strong></td><td>Online übernimmt die Führung wieder; der letzte übertragene Stand ist dort vorhanden.</td></tr>
            <tr><td><strong>Ein Tablet streikt</strong></td><td>Jedes andere Gerät mit Browser meldet sich mit derselben Tatami-PIN an, oder die Leitung trägt am PC ein.</td></tr>
            <tr><td><strong>Technik fällt ganz aus</strong></td><td>Der vorab gedruckte <strong>Notfall-Satz</strong> (Bäume und Kampflisten zum Weiterschreiben, ◯ Ippon / △ Wazaari) liegt an jedem Tisch, dazu Aushang A4/A3 statt Statusseite; die Ergebnisse werden später am PC nachgetragen.</td></tr>
            <tr><td><strong>Daten sichern</strong></td><td>Export des ganzen Turniers mit Prüfsumme, Datenbank-Download – auch zum Umzug zwischen Rechner und Online.</td></tr>
        </table>
    </div>

    <div class="fl-unten3">
        <div>
            <h2>Betrieb nach Wahl</h2>
            <ul>
                <li><strong>Online</strong> auf normalem Webspace (PHP, MySQL): Anmeldung, Portal und Statusseite für alle.</li>
                <li><strong>Online + Hallen-Laptop</strong> (Windows oder Linux) mit eigenem WLAN in der Halle.</li>
                <li><strong>Nur lokal</strong>: ein Rechner, komplett offline – ideal für Vereinsturniere.</li>
            </ul>
            <p class="klein">Voraussetzung: PHP 8.3 und ein aktueller Browser – keine App nötig.</p>
        </div>
        <div>
            <h2>Hilfe und Schulung</h2>
            <ul>
                <li><strong>Handbuch</strong> und Hilfe in der Software</li>
                <li><strong>Handouts</strong> für Tisch, Ausruf und Leitung – mit „Falsch eingegeben – was tun?“</li>
                <li><strong>Demo-Modus</strong> zum Üben</li>
                <li><strong>Feedback-Knopf</strong> mit Screenshot</li>
            </ul>
        </div>
        <div class="fl-spenden">
            <h3>♥ Projekt unterstützen</h3>
            <img src="<?= e(asset('spenden-qr.png')) ?>" alt="QR-Code zur PayPal-Spendenseite">
            <p>Frei und kostenlos, entwickelt in der Freizeit. Eine Spende hilft bei Pflege, Sicherheit und Weiterentwicklung.</p>
        </div>
    </div>
    <p class="fl-urheber">Urheber: Robert Göbel und Wolfgang Maier · freie Software (AGPL-3.0)</p>
    <div class="fl-kontakt">Ansprechpartner: <span></span></div>
</div>
