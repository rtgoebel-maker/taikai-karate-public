<?php
/**
 * Handout Tischbesatzung (Tablet): Anmelden, Ausruf starten, Kata und Kumite eintragen,
 * Gruppe abschließen – und was bei einer Falscheingabe zu tun ist.
 */
$bild = static fn (string $datei, string $text) => '<figure class="ho-bild"><img src="' . e(asset('handout/' . $datei)) . '" alt="' . e($text) . '"><figcaption>' . e($text) . '</figcaption></figure>';
$kopf = static fn (string $seite) => '<div class="ho-kopf"><div><div class="ho-art">Handout · Tischbesatzung</div><h1>Ergebnisse am Tablet eintragen</h1></div><div class="ho-stand">' . e($seite) . '</div></div>';
?>
<div class="blatt">
    <?= $kopf('Seite 1 von 4') ?>
    <p>Je Tatami ein Tablet. Die <strong>Tatami-PIN</strong> bekommt ihr von der Wettkampfleitung. Alles, was ihr eintragt, erscheint sofort auf der Statusseite und in den Listen der Leitung.</p>

    <h2><span class="ho-nr">1</span>Anmelden</h2>
    <div class="ho-schritt hoch">
        <?= $bild('tablet-anmeldung.jpg', 'Tatami wählen, Ansicht „Eingabe“, PIN eingeben') ?>
        <div>
            <ol>
                <li>Im Browser die Adresse der Wettkampfsoftware mit <strong>/tisch</strong> öffnen.</li>
                <li><strong>Tatami</strong> wählen, Ansicht <strong>„Eingabe“</strong>.</li>
                <li><strong>PIN</strong> eingeben, <span class="ho-knopf">Anmelden</span>.</li>
            </ol>
            <p>Das Tablet bleibt angemeldet. Nach einem Update der Software lädt es sich selbst neu.</p>
        </div>
    </div>

    <h2><span class="ho-nr">2</span>Neue Gruppe: Ausruf starten</h2>
    <div class="ho-schritt">
        <?= $bild('tablet-uebergang.jpg', 'Neue Gruppe – Ausruf starten') ?>
        <div>
            <p>Kommt eine neue Gruppe (auch die erste des Tages), zeigt das Tablet <strong>„Nächste Gruppe …“</strong>.</p>
            <ol>
                <li><span class="ho-knopf">📣 Ausruf Gruppe … starten</span> tippen – erst dann sieht der Ausrufer die Namen.</li>
                <li>Hat der Ausrufer alle abgehakt, erscheint <strong>„Ausruf abgeschlossen“</strong> – dann <span class="ho-knopf">Weiter</span> bzw. <span class="ho-knopf">▶ Kampf beginnen</span>.</li>
            </ol>
            <p>Den Stand des Ausrufs seht ihr rechts in der Leiste.</p>
        </div>
    </div>
    <h2><span class="ho-nr">3</span>Kata mit Punkten</h2>
    <div class="ho-schritt">
        <?= $bild('tablet-kata.jpg', 'Kata-Vortrag: Wertungen der Kampfrichter') ?>
        <div>
            <ol>
                <li>Vorgetragene <strong>Kata</strong> eintragen bzw. wählen.</li>
                <li>Je Kampfrichter eine <strong>Wertung</strong> (0,0 bis 9,9). Schnell ohne Komma: <strong>77</strong> = 7,7 – danach springt das Feld weiter.</li>
                <li>Die Summe (höchste und niedrigste gestrichen) erscheint daneben. <span class="ho-knopf">Ergebnis senden</span>.</li>
            </ol>
            <p>Ein ungültiger Wert wird <strong>rot</strong>, Senden ist dann gesperrt. Tritt jemand nicht an: „Kampflos / Disqualifikation“ → <span class="ho-knopf">Kiken</span>.</p>
        </div>
    </div>
</div>

<div class="blatt">
    <?= $kopf('Seite 2 von 4') ?>
    <h2><span class="ho-nr">4</span>Kumite</h2>
    <div class="ho-schritt">
        <?= $bild('tablet-kumite.jpg', 'Kumite: Kampfuhr, Ippon/Wazaari, Strafen') ?>
        <div>
            <ul>
                <li><strong>Kampfuhr</strong> (falls eingeschaltet): <span class="ho-knopf">▶ Hajime</span> startet, <span class="ho-knopf">⏸ Yame</span> hält an. 30 Sekunden vor Ende gelb (Ato shibaraku), am Ende rot.</li>
                <li><strong>Ippon / Wazaari</strong> mit <span class="ho-knopf">+</span> und <span class="ho-knopf">−</span> (2 Wazaari = Ippon).</li>
                <li><strong>Strafen</strong>: Der Knopf zeigt den nächsten Ruf des Hauptkampfrichters – <em>Keikoku → Hansoku Chui → Hansoku</em>, je für Regelverstoß, Jogai, Mubobi. Strafen geben dem Gegner keine Punkte. Hansoku = Gegner gewinnt.</li>
            </ul>
        </div>
    </div>
    <div class="ho-schritt">
        <?= $bild('tablet-ippon.jpg', 'Ein Ippon beendet den Kampf') ?>
        <div>
            <p>Sobald ein Kampf entschieden ist (Ippon, Hansoku), fragt das Tablet: <span class="ho-knopf">Kampf beenden – Ergebnis senden</span> oder <span class="ho-knopf">Weiter eintragen</span> (falls vertippt).</p>
        </div>
    </div>
</div>

<div class="blatt">
    <?= $kopf('Seite 3 von 4') ?>
    <h2><span class="ho-nr">5</span>Kampfzeit abgelaufen, Saishiai, Hantei</h2>
    <div class="ho-schritt">
        <?= $bild('tablet-hantei.jpg', 'Gleichstand nach Ablauf der Zeit: Hantei') ?>
        <div>
            <ol>
                <li>Endet die Zeit ohne Ippon: <span class="ho-knopf">⏱ Kampfzeit abgelaufen</span> (mit Kampfuhr automatisch). Die höhere Wertung gewinnt.</li>
                <li>Bei <strong>Gleichstand</strong>: entweder <span class="ho-knopf">Saishiai (Verlängerung)</span> – ein neuer Kampf über 2 Minuten – oder gleich <strong>Hantei</strong>.</li>
                <li>Hantei: Flaggen für AKA und SHIRO eintragen, nicht gezeigte zählen als unentschieden. Dann <span class="ho-knopf">Ergebnis senden</span>.</li>
            </ol>
        </div>
    </div>

    <h2><span class="ho-nr">6</span>Gruppe fertig – abschließen</h2>
    <div class="ho-schritt">
        <?= $bild('tablet-gruppe-fertig.jpg', 'Gruppe fertig: Platzierungen prüfen und abschließen') ?>
        <div>
            <ol>
                <li>Nach dem letzten Kampf zeigt das Tablet die <strong>Platzierungen</strong>.</li>
                <li>Prüfen. Stimmt etwas nicht: erst korrigieren (Seite 4).</li>
                <li><span class="ho-knopf">Gruppe … abschließen</span> – danach kommt die nächste Gruppe (Schritt 2).</li>
            </ol>
        </div>
    </div>
    <div class="ho-hinweis">
        <h3>Weitere Situationen</h3>
        <ul>
            <li><strong>„Laut Ausrufer fehlt …“</strong>: Starter nicht da → <span class="ho-knopf">Bestätigen: Kiken</span>; doch da → <span class="ho-knopf">… ist doch da</span>.</li>
            <li><strong>Verletzung / Unterbrechung</strong>: <span class="ho-knopf">⏸ Kampf unterbrechen</span>, danach <span class="ho-knopf">▶ Kampf fortsetzen</span>.</li>
            <li><strong>Nachricht der Leitung</strong> erscheint oben – lesen und <span class="ho-knopf">✓ Gelesen</span> tippen.</li>
        </ul>
    </div>
</div>

<div class="blatt">
    <?= $kopf('Seite 4 von 4') ?>
    <h2><span class="ho-nr">!</span>Falsch eingegeben – was tun?</h2>
    <table class="ho-tabelle">
        <thead><tr><th style="width:32%">Situation</th><th>So geht's</th></tr></thead>
        <tbody>
            <tr><td><strong>Noch nicht gesendet</strong></td><td>Zähler mit <span class="ho-knopf">−</span> zurücknehmen, Strafe über die Plakette <span class="ho-knopf">… ✕</span> zurücknehmen, Wertung im Feld überschreiben – oder alles mit <span class="ho-knopf">Verwerfen</span> zurücksetzen.</td></tr>
            <tr><td><strong>Im Fenster „Kampf beenden?“ vertippt</strong></td><td><span class="ho-knopf">Weiter eintragen</span> – nichts wird gesendet.</td></tr>
            <tr><td><strong>Schon gesendet</strong></td><td>Rechts unter <strong>„Zuletzt eingetragen“</strong> <span class="ho-knopf">Korrigieren</span> → <strong>Leitungs-PIN</strong> (von der Wettkampfleitung) → Werte ändern → <span class="ho-knopf">Ergebnis senden</span>. Gilt für Kämpfe und Kata-Vorträge.</td></tr>
            <tr><td><strong>Kampf muss neu ausgetragen werden</strong></td><td>In der Korrektur <span class="ho-knopf">Ergebnis löschen – Kampf neu austragen</span>. Der Kampf startet sofort wieder auf dieser Tatami.</td></tr>
            <tr><td><strong>„Folgekampf ist bereits entschieden“</strong></td><td>Erst den späteren Kampf korrigieren – oder die <strong>Wettkampfleitung</strong> holen (sie korrigiert am PC).</td></tr>
            <tr><td><strong>Gruppe schon abgeschlossen</strong></td><td>Korrigieren geht weiter über „Zuletzt eingetragen“; die Gruppe öffnet sich dann wieder. Ist die Klasse bestätigt: Wettkampfleitung.</td></tr>
        </tbody>
    </table>
    <div class="ho-schritt">
        <?= $bild('tablet-korrektur-pin.jpg', '„Korrigieren“ fragt nach der Leitungs-PIN') ?>
        <?= $bild('tablet-korrektur.jpg', 'Korrekturmodus: ändern und neu senden – oder löschen und neu austragen') ?>
    </div>
    <div class="ho-fehler">
        <h3>Technik-Probleme</h3>
        <ul>
            <li>Oben steht <strong>„offline“</strong>: WLAN prüfen. Eingetragenes bleibt stehen – erneut <span class="ho-knopf">Ergebnis senden</span>, sobald „online“ erscheint.</li>
            <li>Tablet reagiert nicht oder zeigt Altes: Seite <strong>neu laden</strong>. Die Anmeldung bleibt erhalten.</li>
            <li>Geht gar nichts: auf dem <strong>Notfall-Satz</strong> (Papier, von der Leitung) weiterschreiben – ◯ = Ippon, △ = Wazaari – und die Leitung informieren.</li>
        </ul>
    </div>
</div>
