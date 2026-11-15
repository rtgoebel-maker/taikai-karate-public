<?php
/**
 * Übersicht einer Anmeldung (für Dojo und Wettkampfleitung).
 * @var array<string, mixed> $turnier
 * @var array{teilnehmer: array<string, array<string, mixed>>, teams: list<array<string, mixed>>} $angaben
 * @var array{posten: list<array<string, mixed>>, summe: int} $preis
 */
use Wettkampf\Domain\Alter;
use Wettkampf\Domain\Graduierung;

$name = static fn (?string $schluessel) => $schluessel !== null && isset($angaben['teilnehmer'][$schluessel])
    ? $angaben['teilnehmer'][$schluessel]['vorname'] . ' ' . $angaben['teilnehmer'][$schluessel]['nachname'] : '–';
$teamsVon = [];
foreach ($angaben['teams'] as $team) {
    foreach (array_merge($team['mitglieder'], array_filter([$team['ersatz']])) as $m) {
        $teamsVon[$m][] = $team['name'] . ($m === $team['ersatz'] ? t(' (Ersatz)') : '');
    }
}
?>
<h2><?= te('Teilnehmer ({n})', ['n' => count($angaben['teilnehmer'])]) ?></h2>
<div class="tabelle-scroll">
<table class="tabelle">
    <thead><tr><th>#</th><th><?= te('Name') ?></th><th><?= te('Geschlecht') ?></th><th><?= te('Geburtsdatum') ?></th><th><?= te('Alter*') ?></th><th><?= te('Graduierung') ?></th><th>Kata</th><th>Kumite</th><th><?= te('Team') ?></th></tr></thead>
    <tbody>
    <?php $nr = 0; foreach ($angaben['teilnehmer'] as $schluessel => $t): $nr++; ?>
        <tr>
            <td><?= $nr ?></td>
            <td><?= e($t['vorname'] . ' ' . $t['nachname']) ?></td>
            <td><?= e(geschlecht_text($t['geschlecht'])) ?></td>
            <td><?= e(datum_de($t['geburtsdatum'])) ?></td>
            <td><?= Alter::amStichtag($t['geburtsdatum'], $turnier['datum']) ?></td>
            <td><?= e(Graduierung::text((int) $t['graduierung'])) ?></td>
            <td><?= $t['kata'] ? '✓' : '' ?></td>
            <td><?= $t['kumite'] === '' ? '' : e($t['kumite'] === 'kumite' ? '✓' : kumite_form_text(substr($t['kumite'], 7))) ?></td>
            <td><?= e(implode(', ', $teamsVon[$schluessel] ?? [])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="klein"><?= te('* Alter am Turniertag – danach richtet sich die Klasse.') ?></p>

<?php if ($angaben['teams']): ?>
    <h2><?= te('Teams ({n})', ['n' => count($angaben['teams'])]) ?></h2>
    <table class="tabelle">
        <thead><tr><th><?= te('Team') ?></th><th><?= te('Disziplin') ?></th><th><?= te('Mitglieder') ?></th><th><?= te('Ersatz') ?></th></tr></thead>
        <tbody>
        <?php foreach ($angaben['teams'] as $team): ?>
            <tr>
                <td><?= e($team['name']) ?></td>
                <td><?= e(disziplin_text($team['disziplin'])) ?></td>
                <td><?= e(implode(', ', array_map($name, $team['mitglieder']))) ?></td>
                <td><?= e($team['ersatz'] === null ? '' : $name($team['ersatz'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2><?= te('Startgeld') ?></h2>
<?= $this->teil('anmeldung/preis', ['preis' => $preis]) ?>
