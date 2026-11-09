<?php
/**
 * @var array<string, mixed> $turnier
 * @var array{spalten: array<string, int>, fehlend: list<string>, zeilen: list<array<string, mixed>>} $analyse
 * @var string $token
 * @var string $dateiname
 */
$this->layout('layout', ['titel' => 'Import-Vorschau', 'turnier' => $turnier, 'aktiv' => 'teilnehmer']);
$tid = $turnier['id'];
$zeilen = $analyse['zeilen'];
$ok = array_filter($zeilen, static fn ($z) => !$z['fehler'] && !$z['dublette']);
$mitFehler = array_filter($zeilen, static fn ($z) => (bool) $z['fehler']);
$feldNamen = ['nachname' => 'Name', 'vorname' => 'Vorname', 'geschlecht' => 'Geschlecht', 'dojo' => 'Dojo', 'graduierung' => 'Graduierung', 'geburtsdatum/alter' => 'Geburtsdatum oder Alter'];
?>
<h1>Import-Vorschau</h1>
<p class="leise"><?= e($dateiname) ?> · <?= count($zeilen) ?> Zeilen · erkannte Spalten: <?= e(implode(', ', array_keys($analyse['spalten']))) ?></p>

<?php if ($analyse['fehlend']): ?>
    <div class="meldung meldung-fehler">Fehlende Spalten: <?= e(implode(', ', array_map(static fn ($f) => $feldNamen[$f] ?? $f, $analyse['fehlend']))) ?>. Bitte die Überschriften prüfen (siehe Vorlage).</div>
<?php endif; ?>

<div class="karte knopfleiste">
    <span><strong><?= count($ok) ?></strong> werden importiert</span>
    <span class="tag <?= $mitFehler ? 'fehler' : 'ok' ?>"><?= count($mitFehler) ?> mit Fehlern</span>
    <span class="tag"><?= count(array_filter($zeilen, static fn ($z) => $z['dublette'])) ?> Dubletten</span>
    <form method="post" action="<?= e(url("turnier/$tid/import/uebernehmen")) ?>" style="margin-left:auto" class="knopfleiste">
        <?= csrf_feld() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <a class="knopf" href="<?= e(url("turnier/$tid/import")) ?>">Andere Datei</a>
        <button class="knopf primaer" type="submit"<?= $ok ? '' : ' disabled' ?>><?= count($ok) ?> Teilnehmer importieren</button>
    </form>
</div>

<div class="tabelle-scroll">
<table class="tabelle">
    <thead><tr><th>Zeile</th><th>Name</th><th>G.</th><th>Alter</th><th>Grad</th><th>Dojo</th><th>Meldung</th><th>Teams</th><th>Prüfung</th></tr></thead>
    <tbody>
    <?php foreach ($zeilen as $z): ?>
        <tr<?= $z['dublette'] ? ' class="inaktiv"' : '' ?>>
            <td class="zahl"><?= $z['zeile'] ?></td>
            <td><?= e($z['nachname']) ?>, <?= e($z['vorname']) ?></td>
            <td><?= e(strtoupper((string) $z['geschlecht'])) ?></td>
            <td><?= e($z['alter']) ?></td>
            <td class="nowrap"><?= e(grad($z['graduierung'])) ?></td>
            <td><?= e($z['dojo']) ?></td>
            <td><?= e(implode(', ', array_map('disziplin_text', $z['disziplinen']))) ?><?= $z['klasse'] ? ' → ' . e($z['klasse']) : '' ?></td>
            <td><?= e(implode(' / ', array_filter([$z['kata_team'], $z['kumite_team']]))) ?></td>
            <td>
                <?php foreach ($z['fehler'] as $f): ?><span class="tag fehler"><?= e($f) ?></span> <?php endforeach; ?>
                <?php foreach ($z['hinweise'] as $h): ?><span class="tag warn"><?= e($h) ?></span> <?php endforeach; ?>
                <?php if (!$z['fehler'] && !$z['hinweise']): ?><span class="tag ok">ok</span><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
