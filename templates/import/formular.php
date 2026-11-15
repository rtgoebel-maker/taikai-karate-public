<?php
/** @var array<string, mixed> $turnier @var list<string> $kopf */
$this->layout('layout', ['titel' => t('Import'), 'turnier' => $turnier, 'aktiv' => 'teilnehmer']);
$tid = $turnier['id'];
?>
<h1><?= te('Teilnehmer importieren') ?> <?= info('import') ?></h1>
<div class="zwei-spalten">
    <form method="post" action="<?= e(url("turnier/$tid/import")) ?>" enctype="multipart/form-data" class="karte formular">
        <?= csrf_feld() ?>
        <h2><?= te('Datei hochladen') ?></h2>
        <label><?= te('Meldeliste (XLSX oder CSV)') ?> <input type="file" name="datei" accept=".xlsx,.csv,.txt" required></label>
        <p class="hilfe"><?= te('Im nächsten Schritt siehst du eine Vorschau mit allen erkannten Fehlern. Erst danach wird importiert.') ?></p>
        <div><button class="knopf primaer" type="submit"><?= te('Hochladen & prüfen') ?></button></div>
    </form>
    <div class="karte">
        <h2><?= te('Aufbau der Datei') ?></h2>
        <p><?= te('Eine Zeile pro Person, erste Zeile mit Überschriften. Die Reihenfolge der Spalten ist egal.') ?></p>
        <?php /* Spaltennamen gehören zum Dateiformat (Import erkennt deutsche Überschriften) – nicht übersetzen */ ?>
        <table class="tabelle">
            <thead><tr><th><?= te('Spalte') ?></th><th><?= te('Inhalt') ?></th></tr></thead>
            <tbody>
            <tr><td>Name, Vorname</td><td><?= te('Pflicht') ?></td></tr>
            <tr><td>Geschlecht</td><td><?= te('Pflicht: m / w (auch „männlich“, „Mädchen“ …)') ?></td></tr>
            <tr><td><?= th('Geburtsdatum <em>oder</em> Alter') ?></td><td><?= te('03.05.2012 bzw. 13 (Alter am Turniertag); Geburtsdatum bevorzugt') ?></td></tr>
            <tr><td>Dojo</td><td><?= te('Vereinsname; neue Vereine werden angelegt') ?></td></tr>
            <tr><td>Graduierung</td><td>„5. Kyu“, „5 Kyu“, „5K“, „1. Dan“, „1D“</td></tr>
            <tr><td>Kata Einzel</td><td><?= te('x / ja') ?></td></tr>
            <tr><td>Kumite Einzel</td><td><?= te('x / ja oder Kampfform: „Kihon“, „Jiyu Ippon“, „Jiyu“') ?></td></tr>
            <tr><td>Kata Team, Kumite Team</td><td><?= te('Teamname – gleicher Name = gleiches Team') ?></td></tr>
            <tr><td>Klasse</td><td><?= te('optional: Code zum Überschreiben der automatischen Zuordnung') ?></td></tr>
            </tbody>
        </table>
        <p style="margin-top:.75rem"><a class="knopf" href="<?= e(url("turnier/$tid/import/vorlage")) ?>"><?= te('Vorlage herunterladen (CSV für Excel)') ?></a></p>
    </div>
</div>
