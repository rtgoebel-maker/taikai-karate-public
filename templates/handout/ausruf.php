<?php
/**
 * Handout Ausruf (Handy): anmelden, Starter abhaken, Ausruf abschließen, Fehler beheben.
 */
$bild = static fn (string $datei, string $text) => '<figure class="ho-bild"><img src="' . e(asset('handout/' . $datei)) . '" alt="' . e($text) . '"><figcaption>' . e($text) . '</figcaption></figure>';
$kopf = static fn (string $seite) => '<div class="ho-kopf"><div><div class="ho-art">Handout · Ausruf</div><h1>Starter aufrufen und abhaken</h1></div><div class="ho-stand">' . e($seite) . '</div></div>';
?>
<div class="blatt">
    <?= $kopf('Seite 1 von 2') ?>
    <p>Der Ausrufer ruft die Starter einer Gruppe auf und meldet, wer da ist. Er nutzt ein Handy mit der Ansicht <strong>„Ausruf“</strong> derselben Tatami (gleiche PIN wie das Tablet).</p>

    <h2><span class="ho-nr">1</span>Anmelden</h2>
    <p>Adresse der Wettkampfsoftware mit <strong>/tisch</strong> öffnen, Tatami wählen, Ansicht <strong>„Ausruf – Starter aufrufen (Handy)“</strong>, PIN, <span class="ho-knopf">Anmelden</span>. Tipp: die Seite zum Startbildschirm hinzufügen.</p>

    <h2><span class="ho-nr">2</span>Starter abhaken</h2>
    <div class="ho-schritt hoch">
        <?= $bild('ausruf-liste.jpg', 'Abhakliste der Gruppe') ?>
        <div>
            <p>Die Namen erscheinen, sobald die Tischbesatzung den <strong>Ausruf gestartet</strong> hat (vorher steht dort „Nächste Gruppe … – die Namen erscheinen, sobald …“).</p>
            <ol>
                <li>Gruppe laut aufrufen. Mit <span class="ho-knopf">📣 Gruppe … aufrufen</span> stehen die Namen zusätzlich auf der Statusseite unter „Bitte zur Tatami“. Hat die Tischbesatzung den Ausruf gestartet, ist das schon geschehen.</li>
                <li>Je Starter <span class="ho-knopf">✓ da</span> oder <span class="ho-knopf">✗ fehlt</span> tippen.</li>
            </ol>
            <p>Wer in dieser Klasse schon einmal „da“ war oder gekämpft hat, muss in späteren Runden nicht erneut ausgerufen werden.</p>
        </div>
    </div>
</div>

<div class="blatt">
    <?= $kopf('Seite 2 von 2') ?>
    <h2><span class="ho-nr">3</span>Ausruf abschließen</h2>
    <div class="ho-schritt hoch">
        <?= $bild('ausruf-abhaken.jpg', 'Alle abgehakt – abschließen') ?>
        <div>
            <ol>
                <li>Sind alle abgehakt, wird <span class="ho-knopf">Ausruf Gruppe … abschließen</span> grün – tippen.</li>
                <li>Danach zeigt das Handy <strong>„Jetzt auf der Tatami“</strong> und <strong>„Bereit machen“</strong> – die nächsten Starter an den Mattenrand schicken.</li>
                <li>Wer <strong>fehlt</strong>, geht als Meldung an die Wettkampfleitung; sie entscheidet (der Starter kann gerade auf einer anderen Tatami sein).</li>
            </ol>
        </div>
    </div>
    <div class="ho-schritt hoch">
        <?= $bild('ausruf-fertig.jpg', 'Nach dem Abschluss: jetzt dran und bereit machen') ?>
        <div>
            <div class="ho-fehler">
                <h3>Falsch abgehakt?</h3>
                <ul>
                    <li><strong>Vor dem Abschluss</strong>: denselben Knopf noch einmal tippen – der Haken ist weg – und richtig setzen.</li>
                    <li><strong>Nach dem Abschluss</strong>: unter „Aufgerufen – bereit“ <span class="ho-knopf">wieder öffnen</span>, Haken ändern, neu abschließen.</li>
                    <li><strong>„fehlt“ war falsch</strong>, der Starter ist doch da: auf „da“ ändern – oder der Tischbesatzung sagen, dort gibt es <span class="ho-knopf">… ist doch da</span>.</li>
                </ul>
            </div>
            <div class="ho-hinweis">
                <h3>Gut zu wissen</h3>
                <ul>
                    <li>„Kann noch nicht aufgerufen werden“: Ein Starter kämpft noch auf einer anderen Tatami – warten, die Liste aktualisiert sich selbst.</li>
                    <li>Laufen die Pools einer Klasse auf mehreren Tatamis, ruft jede Tatami nur ihren Pool aus.</li>
                    <li>Rechts oben „offline“: WLAN prüfen, die Seite holt den Stand selbst nach.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
