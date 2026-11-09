<?php
/** @var array<string, mixed> $turnier @var list<string> $kopf */
$this->layout('layout', ['titel' => 'Import', 'turnier' => $turnier, 'aktiv' => 'teilnehmer']);
$tid = $turnier['id'];
?>
<h1>Teilnehmer importieren <?= info('import') ?></h1>
<div class="zwei-spalten">
    <form method="post" action="<?= e(url("turnier/$tid/import")) ?>" enctype="multipart/form-data" class="karte formular">
        <?= csrf_feld() ?>
        <h2>Datei hochladen</h2>
        <label>Meldeliste (XLSX oder CSV) <input type="file" name="datei" accept=".xlsx,.csv,.txt" required></label>
        <p class="hilfe">Im nächsten Schritt siehst du eine Vorschau mit allen erkannten Fehlern. Erst danach wird importiert.</p>
        <div><button class="knopf primaer" type="submit">Hochladen &amp; prüfen</button></div>
    </form>
    <div class="karte">
        <h2>Aufbau der Datei</h2>
        <p>Eine Zeile pro Person, erste Zeile mit Überschriften. Die Reihenfolge der Spalten ist egal.</p>
        <table class="tabelle">
            <thead><tr><th>Spalte</th><th>Inhalt</th></tr></thead>
            <tbody>
            <tr><td>Name, Vorname</td><td>Pflicht</td></tr>
            <tr><td>Geschlecht</td><td>Pflicht: m / w (auch „männlich“, „Mädchen“ …)</td></tr>
            <tr><td>Geburtsdatum <em>oder</em> Alter</td><td>03.05.2012 bzw. 13 (Alter am Turniertag); Geburtsdatum bevorzugt</td></tr>
            <tr><td>Dojo</td><td>Vereinsname; neue Vereine werden angelegt</td></tr>
            <tr><td>Graduierung</td><td>„5. Kyu“, „5 Kyu“, „5K“, „1. Dan“, „1D“</td></tr>
            <tr><td>Kata Einzel</td><td>x / ja</td></tr>
            <tr><td>Kumite Einzel</td><td>x / ja oder Kampfform: „Kihon“, „Jiyu Ippon“, „Jiyu“</td></tr>
            <tr><td>Kata Team, Kumite Team</td><td>Teamname – gleicher Name = gleiches Team</td></tr>
            <tr><td>Klasse</td><td>optional: Code zum Überschreiben der automatischen Zuordnung</td></tr>
            </tbody>
        </table>
        <p style="margin-top:.75rem"><a class="knopf" href="<?= e(url("turnier/$tid/import/vorlage")) ?>">Vorlage herunterladen (CSV für Excel)</a></p>
    </div>
</div>
