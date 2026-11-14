<?php
/**
 * @var bool $zweiterFaktorPflicht
 * @var bool $zweiterFaktorAktiv
 * @var bool $feedbackAktiv
 * @var string $feedbackAdresse Empfänger der Feedback-Mails
 * @var array{schema: string, primaer: string, hell: string, eigene: string} $farben
 * @var list<string> $statusLogos
 * @var array{starterlisten: bool, ergebnisse: bool} $statusBereiche
 * @var bool $demoModus
 * @var int $kontoMonate
 * @var bool $dojoMehrereTurniere
 * @var list<array{id: int|string, name: string, datum: string}> $turniereMitLogos
 * @var array<string, string|int|bool> $mail
 * @var bool $mailFestgelegt
 * @var bool $adresseFestgelegt
 * @var array{oeffentliche_url: string|null, transport: string|null, host: string, aktiv: bool} $mailWirksam
 * @var array<string, mixed> $warteschlange
 * @var list<array<string, mixed>> $mailListe
 */
use Wettkampf\Betrieb\DemoTurnier;
use Wettkampf\Turnier\SystemEinstellungen;

$this->layout('layout', ['titel' => 'Systemeinstellungen', 'aktiv' => 'system']);
?>
<h1>Systemeinstellungen <?= info('system') ?></h1>
<p class="leise">Diese Einstellungen gelten für alle Turniere. Turnierbezogenes (Startgeld, PINs, Urkunden …) steht im jeweiligen Turnier unter „Einstellungen“.</p>
<form method="post" action="<?= e(url('system')) ?>" class="system-formular">
    <?= csrf_feld() ?>
    <div class="karte formular">
        <h2>Oberfläche</h2>
        <p class="hilfe">Hauptfarbe für Kopfzeile, Menü und Knöpfe – auf allen Seiten der Leitung, den Tablets (Tisch und Ausruf), der Statusseite und der Online-Anmeldung. Signalfarben (Aka rot, Erfolg grün, Warnung gelb) bleiben unverändert. Ausdrucke bleiben schwarz-weiß.</p>
        <div class="farbwahl">
            <?php foreach (SystemEinstellungen::FARBSCHEMATA as $schluessel => [$name, $farbe]): ?>
                <label class="farbfeld">
                    <input type="radio" name="farbschema" value="<?= e($schluessel) ?>" data-farbe="<?= e($farbe) ?>"<?= $farben['schema'] === $schluessel ? ' checked' : '' ?>>
                    <span class="farbmuster" style="background:<?= e($farbe) ?>"></span><?= e($name) ?>
                </label>
            <?php endforeach; ?>
            <label class="farbfeld">
                <input type="radio" name="farbschema" value="eigene"<?= $farben['schema'] === 'eigene' ? ' checked' : '' ?>>
                <input type="color" name="farbe_eigene" value="<?= e($farben['eigene']) ?>" aria-label="Eigene Farbe"> eigene Farbe
            </label>
        </div>
        <p class="klein leise">Die Vorschau ändert diese Seite sofort; gespeichert wird erst mit „Speichern“. Eine eigene Farbe muss dunkel genug für weiße Schrift sein.</p>
    </div>

    <div class="karte formular">
        <h2>Statusseite</h2>
        <p class="hilfe">Logos des Ausrichters links in der Kopfzeile der Statusseite (auch Tatami-Vollbild und Offlinekopie). Hochgeladen werden sie je Turnier unter Druck → Urkunden; fehlt ein Logo, bleibt der Platz leer.</p>
        <label class="check"><input type="checkbox" name="status_logos[]" value="verein"<?= in_array('verein', $statusLogos, true) ? ' checked' : '' ?>> Vereinslogo des Ausrichters anzeigen</label>
        <label class="check"><input type="checkbox" name="status_logos[]" value="dojo"<?= in_array('dojo', $statusLogos, true) ? ' checked' : '' ?>> Dojo-Logo des Ausrichters anzeigen</label>
        <p class="hilfe">Bereiche neben „Tatamis live“ – ausgeblendet verschwinden Menüpunkt und Seite. Sind beide aus, zeigt die Statusseite nur die Tatamis (ohne Menüzeile, mit mehr Einträgen je Tatami).</p>
        <label class="check"><input type="checkbox" name="status_bereiche[]" value="starterlisten"<?= $statusBereiche['starterlisten'] ? ' checked' : '' ?>> „Starterlisten &amp; Bäume“ anzeigen</label>
        <label class="check"><input type="checkbox" name="status_bereiche[]" value="ergebnisse"<?= $statusBereiche['ergebnisse'] ? ' checked' : '' ?>> „Ergebnisse“ anzeigen</label>
    </div>

    <div class="karte formular">
        <h2>Feedback <?= info('feedback') ?></h2>
        <label class="check">
            <input type="checkbox" name="feedback_aktiv" value="1"<?= $feedbackAktiv && $mailWirksam['aktiv'] ? ' checked' : '' ?><?= $mailWirksam['aktiv'] ? '' : ' disabled' ?>>
            Feedback-Knopf anzeigen – auf allen Seiten der Wettkampfleitung und auf den Tablets (Tisch und Ausruf)
        </label>
        <?php if (!$mailWirksam['aktiv']): ?>
            <div class="meldung meldung-hinweis klein"><strong>Setzt den E-Mail-Versand voraus</strong> (unten einrichten): Meldungen gehen per Mail an das Taikai-Team.</div>
        <?php endif; ?>
        <p class="hilfe">Nutzer melden damit Fehler, Verbesserungsvorschläge oder Fragen. Jede Meldung geht <strong>per Mail an <?= e($feedbackAdresse) ?></strong> (Team von Taikai – Karate) –
            mit Seite, Absender und, falls nicht abgewählt, Screenshot – und bleibt zusätzlich unter <a href="<?= e(url('feedback')) ?>">Feedback</a> gespeichert.
            Screenshots können personenbezogene Daten zeigen (z. B. Teilnehmerlisten); der Dialog weist darauf hin. Ausschalten blendet nur den Knopf aus; vorhandene Meldungen bleiben erhalten.</p>
    </div>

    <div class="karte formular">
        <h2>Anmeldung</h2>
        <label class="check"><input type="checkbox" name="zweiter_faktor_pflicht" value="1"<?= $zweiterFaktorPflicht ? ' checked' : '' ?>> Zweiter Faktor für alle Konten Pflicht (empfohlen)
            <span class="hilfe">Nach dem Passwort ist ein Code nötig: aus der Authenticator-App, sonst per E-Mail. Ohne E-Mail-Versand bzw. ohne E-Mail-Adresse
                richtet jedes Konto bei der nächsten Anmeldung eine App ein (z. B. Google/Microsoft Authenticator, Aegis, 2FAS – funktioniert auch offline).
                Aus: Nur Konten mit eingerichteter App werden nach einem Code gefragt. Jeder kann seine App unter „Konto &amp; Sicherheit“ einrichten.</span></label>
        <?php if (!$zweiterFaktorAktiv): ?>
            <div class="meldung meldung-warnung klein"><strong>Abgeschaltet über <code>'mfa' => false</code> in config.php</strong> – der zweite Faktor wird derzeit nicht abgefragt.</div>
        <?php endif; ?>
        <p class="klein"><a href="<?= e(url('system/sperren')) ?>">Gesperrte Anmeldungen ansehen</a> – IP-Adressen nach zu vielen falschen Passwörtern oder PINs, Sperren aufheben</p>
    </div>

    <div class="karte formular">
        <h2>Dojo-Zugänge aus der Online-Anmeldung</h2>
        <label>Gültig bis … Monate nach dem Turnier
            <input type="number" name="konto_monate" min="1" max="24" value="<?= (int) $kontoMonate ?>" style="max-width:8rem"></label>
        <p class="hilfe">Dojos können bei der Online-Anmeldung einen Zugang anlegen (nur mit E-Mail-Versand). Er gilt bis so viele Monate nach dem Turnier
            und wird danach automatisch gelöscht (Datensparsamkeit); je Turnier ist eine neue Anmeldung nötig, angemeldet verlängert sie den Zugang.
            Von der Administration angelegte Konten laufen nicht ab.</p>
        <label class="check"><input type="checkbox" name="dojo_mehrere_turniere" value="1"<?= $dojoMehrereTurniere ? ' checked' : '' ?>> Ein Zugang für mehrere Turniere
            <span class="hilfe">Aus (Standard): Ein Zugang gilt nur für das Turnier, für das er angelegt wurde – „Meine Meldungen“ und Coaching-Portal zeigen nur dieses Turnier.
                Ein: Mit demselben Zugang lassen sich auch weitere Turniere melden. Von der Administration angelegte Coach-Konten gelten immer für alle Turniere ihres Dojos.</span></label>
    </div>
    <div class="karte">
        <h2>Datenschutz</h2>
        <p class="hilfe">Gilt für alle Turniere. Datenschutzhinweise, Löschfristen und Buchhaltungsexport stehen je Turnier unter „Einstellungen → Datenschutz“.</p>
        <p><a class="knopf" href="<?= e(url('datenschutz/auskunft')) ?>">Auskunft und Löschung</a></p>
        <p class="klein">Anfragen von Startern, Erziehungsberechtigten oder meldenden Personen (Art. 15/17 DSGVO): alle gespeicherten Angaben zur Person über alle Turniere, Löschung auf Antrag.</p>
        <p><a class="knopf" href="<?= e(url('einladungen')) ?>">Einladungen zu künftigen Turnieren</a></p>
        <p class="klein">Freiwillig eingewilligte Adressen aus der Online-Anmeldung: ansehen, löschen, exportieren, Einladung versenden.</p>
    </div>
    <div class="system-speichern"><button class="knopf primaer" type="submit">Einstellungen speichern</button></div>
</form>

<div class="system-raster">
<?= $this->teil('system/mail', compact('mail', 'mailFestgelegt', 'adresseFestgelegt', 'mailWirksam', 'warteschlange', 'mailListe')) ?>

<section class="karte formular demo-bereich" id="demo">
    <h2>Demo-Turniere <?= info('demo') ?></h2>
    <form method="post" action="<?= e(url('system/demo-modus')) ?>" class="knopfleiste demo-schalter">
        <?= csrf_feld() ?>
        <label class="check"><input type="checkbox" name="demo_modus" value="1"<?= $demoModus ? ' checked' : '' ?>> Demo-Modus – hier Demo-Turniere zum Ausprobieren und Schulen anlegen</label>
        <button class="knopf klein" type="submit">Übernehmen</button>
    </form>
    <p class="hilfe">Für Schulungs- und Testinstallationen. Ausschalten blendet nur das Anlegen aus; vorhandene Demo-Turniere bleiben erhalten (löschen wie jedes Turnier).</p>
    <?php if ($demoModus): ?>
    <form method="post" action="<?= e(url('system/demo-turnier')) ?>" class="formular demo-anlegen" data-bestaetigen="Demo-Turnier mit erfundenen Teilnehmern anlegen? (laufend/abgeschlossen dauert einige Sekunden)">
    <?= csrf_feld() ?>
        <h3>Demo-Turnier anlegen</h3>
        <p class="hilfe">Legt ein neues Turnier mit erfundenen Teilnehmern und Vereinen an – ausgelost und auf die 4 Tatamis verteilt, wahlweise noch nicht gestartet, laufend oder abgeschlossen (Ergebnisse zufällig). Die Klassen zeigen die Pool-Bildung nach Starterzahl: Kata (Punkte) bis 8 Starter in einem Block, darüber Pools zu höchstens 8 (bis Pool E), Kumite-Pools gleich groß und möglichst gerade. PINs: <?= e(DemoTurnier::PINS) ?>.</p>
        <div class="zeile">
            <label>Name <input type="text" name="name" maxlength="200" placeholder="automatisch, z. B. „Pool-Demo <?= e(date('Y')) ?>“"></label>
            <label>Datum <input type="date" name="datum" required value="<?= e((new DateTimeImmutable('+4 weeks'))->format('Y-m-d')) ?>"></label>
            <label>Logos und Urkunde aus
                <select name="logos_von">
                    <option value="0">– keine –</option>
                    <?php foreach ($turniereMitLogos as $t): ?>
                        <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?> (<?= e(datum_de($t['datum'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <fieldset class="optionen">
            <legend>Stand des Turniers</legend>
            <?php foreach (DemoTurnier::PHASEN as $wert => $text): ?>
                <label class="check"><input type="radio" name="phase" value="<?= e($wert) ?>"<?= $wert === array_key_first(DemoTurnier::PHASEN) ? ' checked' : '' ?>> <?= e($text) ?></label>
            <?php endforeach; ?>
        </fieldset>
        <label class="check"><input type="checkbox" name="teams" value="1" checked> mit Kata- und Kumite-Teams (ohne Pools)</label>
        <div><button class="knopf primaer" type="submit">Demo-Turnier anlegen</button></div>
</form>
    <?php endif; ?>
</section>
</div>
<script src="<?= e(asset('system.js')) ?>" defer></script>
