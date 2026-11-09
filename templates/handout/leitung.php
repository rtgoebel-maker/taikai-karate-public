<?php
/**
 * Handout Wettkampfleitung: Turniertag steuern – Übersicht, Tatami-Plan, Korrekturen, Bestätigen,
 * Druck und Notfall, Statusseite.
 */
$bild = static fn (string $datei, string $text) => '<figure class="ho-bild"><img src="' . e(asset('handout/' . $datei)) . '" alt="' . e($text) . '"><figcaption>' . e($text) . '</figcaption></figure>';
$kopf = static fn (string $seite) => '<div class="ho-kopf"><div><div class="ho-art">Handout · Wettkampfleitung</div><h1>Den Turniertag steuern</h1></div><div class="ho-stand">' . e($seite) . '</div></div>';
?>
<div class="blatt">
    <?= $kopf('Seite 1 von 4') ?>
    <h2><span class="ho-nr">1</span>Vor dem Start</h2>
    <ul>
        <li><strong>Einstellungen → Tatamis &amp; Zugänge</strong>: PIN je Tatami, <strong>Leitungs-PIN</strong> (für Korrekturen am Tablet), Aufstellung AKA links/rechts, Kampfuhr am Tablet.</li>
        <li><strong>Druck</strong>: Kampflisten je Tatami und den <strong>Notfall-Satz</strong> ausdrucken und bereitlegen.</li>
        <li><strong>Übersicht → Turnierphase → „Turnier starten“</strong>: lost offene Klassen aus und verteilt die ersten Gruppen auf die Tatamis.</li>
    </ul>

    <h2><span class="ho-nr">2</span>Übersicht am Turniertag</h2>
    <div class="ho-schritt breit">
        <?= $bild('leitung-uebersicht.jpg', 'Übersicht: Meldungen „Nicht erschienen“ und Turnierphase') ?>
    </div>
    <ul>
        <li><strong>Nicht erschienen</strong>: Rückmeldung des Ausrufs. Entscheiden: <span class="ho-knopf">Ist doch da</span>, <span class="ho-knopf">Kiken nur hier</span> (verliert diesen Kampf) oder <span class="ho-knopf">Aus allen Wettkämpfen</span>.</li>
        <li>In der Kopfzeile stehen Warnungen (z. B. „1 nicht erschienen“, „☕ Kata beendet“ – Gelegenheit für eine Pause).</li>
    </ul>
</div>

<div class="blatt">
    <?= $kopf('Seite 2 von 4') ?>
    <h2><span class="ho-nr">3</span>Tatami-Plan</h2>
    <div class="ho-schritt breit">
        <?= $bild('leitung-plan.jpg', 'Warteschlange und Tatamis: Karten per Maus ziehen') ?>
    </div>
    <ul>
        <li><strong>Dynamische Verteilung</strong>: Ist eine Gruppe fertig, bekommt die Tatami sofort die nächste aus der <strong>Warteschlange</strong> (erst Kata, dann Kumite, jüngere Klassen zuerst). Reihenfolge per Ziehen ändern; „Warteschlange neu sortieren“ stellt die Klassenreihenfolge wieder her.</li>
        <li><strong>🔒 Sperren</strong> je Tatami (z. B. Pause, Matte tauschen): Die laufende Gruppe läuft zu Ende, danach kommt nichts Neues.</li>
    </ul>
    <div class="ho-schritt">
        <?= $bild('leitung-nachricht.jpg', 'Nachricht an eine oder alle Tatamis') ?>
        <div>
            <p><strong>Nachricht an die Tische</strong>: Text an eine oder alle Tatamis senden. Er bleibt auf dem Tablet und dem Ausruf-Handy stehen, bis dort „Gelesen“ getippt wird.</p>
        </div>
    </div>
</div>

<div class="blatt">
    <?= $kopf('Seite 3 von 4') ?>
    <h2><span class="ho-nr">4</span>Ergebnis korrigieren</h2>
    <div class="ho-schritt eng">
        <?= $bild('leitung-ergebnis.jpg', 'Korrektur eines Kampfs am PC') ?>
        <div>
            <ol>
                <li>Klasse öffnen → beim Kampf bzw. Vortrag <span class="ho-knopf">Korr.</span> – oder Ergebnisse → Kampf.</li>
                <li>Werte ändern, speichern. Jede Korrektur wird protokolliert (Verlauf).</li>
            </ol>
            <p>Am <strong>Tablet</strong> korrigiert die Tischbesatzung selbst mit der Leitungs-PIN („Zuletzt eingetragen → Korrigieren“).</p>
        </div>
    </div>
    <div class="ho-fehler">
        <h3>Falscheingabe – typische Fälle</h3>
        <table class="ho-tabelle">
            <tr><th style="width:36%">Meldung / Situation</th><th>Lösung</th></tr>
            <tr><td>„Der Folgekampf ist bereits entschieden“</td><td>Zuerst den späteren Kampf korrigieren bzw. dessen Ergebnis löschen, dann den früheren.</td></tr>
            <tr><td>Klasse ist schon <strong>bestätigt</strong></td><td>In der Klasse die Bestätigung aufheben, korrigieren, neu bestätigen.</td></tr>
            <tr><td>Kampf soll neu ausgetragen werden</td><td>Am Tablet „Korrigieren“ → „Ergebnis löschen – Kampf neu austragen“ (Leitungs-PIN).</td></tr>
            <tr><td>Falscher Starter in einer Klasse / Stammdaten falsch</td><td>Teilnehmer bearbeiten; vor der Auslosung neu zuordnen, danach Klasse prüfen (ggf. neu auslosen, solange keine Ergebnisse da sind).</td></tr>
            <tr><td>Gleichstand bei Kata / „jeder gegen jeden“</td><td>Die Klasse zeigt „Stichentscheid“ – Reihenfolge nach Stechen bzw. Entscheidungskampf eintragen.</td></tr>
        </table>
    </div>
    <h2><span class="ho-nr">5</span>Platzierungen bestätigen</h2>
    <div class="ho-schritt eng">
        <?= $bild('leitung-klasse.jpg', 'Klasse fertig: Platzierungen prüfen und bestätigen') ?>
        <div>
            <p>Hat der Tisch die Gruppe abgeschlossen, stehen die Platzierungen als <strong>vorläufig</strong> in der Klasse. Prüfen und <span class="ho-knopf">Platzierungen bestätigen</span> – erst dann erscheinen sie in Siegerliste, Ergebnissen und auf den Urkunden.</p>
        </div>
    </div>
</div>

<div class="blatt">
    <?= $kopf('Seite 4 von 4') ?>
    <h2><span class="ho-nr">6</span>Druck und Notfall</h2>
    <div class="ho-schritt">
        <?= $bild('leitung-druck.jpg', 'Druckcenter: Kampflisten, Notfall-Satz, Aushang, Urkunden') ?>
        <div>
            <ul>
                <li><strong>Notfall-Satz je Klasse</strong>: Baum bzw. Pool-Liste und Kampfliste zum Weiterschreiben von Hand (◯ = Ippon, △ = Wazaari).</li>
                <li><strong>Aushang A4/A3</strong>: ersetzt die Statusseite an der Wand.</li>
                <li><strong>Urkunden</strong> je bestätigter Klasse.</li>
            </ul>
            <div class="ho-hinweis"><strong>Fällt die Technik aus:</strong> Notfall-Satz an die Tische, Ergebnisse von Hand schreiben, später am PC nachtragen.</div>
        </div>
    </div>
    <h2><span class="ho-nr">7</span>Statusseite</h2>
    <div class="ho-schritt">
        <?= $bild('leitung-status.jpg', 'Statusseite für Zuschauer und Coaches') ?>
        <div>
            <p>Die öffentliche Statusseite (Menü „Statusseite“) zeigt je Tatami, wer jetzt antritt, wer sich bereit machen soll und wer gerade aufgerufen wird. Sie aktualisiert sich selbst – gut für einen Bildschirm in der Halle („Vollbild“ je Tatami).</p>
        </div>
    </div>
    <div class="ho-hinweis">
        <h3>Zum Nachlesen</h3>
        <p>Menü <strong>Hilfe</strong> in der Software (alle Themen), dort auch diese Handouts für Tischbesatzung und Ausruf zum Ausdrucken.</p>
    </div>
</div>
