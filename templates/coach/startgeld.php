<?php
/**
 * Coaching-Portal: Startgeld-Übersicht des Dojos (A4, Drucken bzw. als PDF speichern).
 * @var array<string, mixed> $turnier
 * @var string $verein
 * @var array{posten: list<array{text: string, anzahl: int, einzel: int, summe: int}>, summe: int, teilnehmer: list<array<string, mixed>>, teams: list<array<string, mixed>>} $startgeld
 * @var string $hinweis
 */
use Wettkampf\Meldung\AnmeldungService;

$euro = static fn (int $cent) => AnmeldungService::euro($cent);
$tid = (int) $turnier['id'];
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Startgeld <?= e($verein) ?> · <?= e($turnier['name']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('druck.css')) ?>">
</head>
<body>
<div class="werkzeug">
    <strong>Startgeld · <?= e($verein) ?></strong>
    <button type="button" data-drucken>Drucken / als PDF speichern</button>
    <a href="<?= e(url("coach/turnier/$tid")) ?>">Zurück zum Portal</a>
    <span style="margin-left:auto;font-size:9pt">Tipp: Im Druckdialog „Kopf- und Fußzeilen“ ausschalten.</span>
</div>
<div class="blatt">
    <?= $this->teil('druck/kopf', ['turnier' => $turnier, 'ueberschrift' => 'Startgeld ' . $verein]) ?>

    <table>
        <thead><tr><th>Posten</th><th class="rechts">Anzahl</th><th class="rechts">Einzelpreis</th><th class="rechts">Summe</th></tr></thead>
        <tbody>
        <?php foreach ($startgeld['posten'] as $p): ?>
            <tr><td><?= e($p['text']) ?></td><td class="rechts zahl"><?= $p['anzahl'] ?></td><td class="rechts zahl"><?= e($euro($p['einzel'])) ?></td><td class="rechts zahl"><?= e($euro($p['summe'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$startgeld['posten']): ?><tr><td colspan="4">Keine Starts.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><th colspan="3">Gesamt</th><th class="rechts zahl"><?= e($euro($startgeld['summe'])) ?></th></tr></tfoot>
    </table>
    <?php if ($startgeld['posten'] && max(array_column($startgeld['posten'], 'einzel')) === 0): ?>
        <p class="klein"><strong>Hinweis:</strong> Für dieses Turnier sind keine Startgebühren hinterlegt – die Beträge bitte bei der Turnierleitung erfragen.</p>
    <?php endif; ?>

    <h2>Teilnehmer</h2>
    <table>
        <thead><tr><th>Name</th><th>Kata</th><th>Kumite</th></tr></thead>
        <tbody>
        <?php foreach ($startgeld['teilnehmer'] as $t): ?>
            <tr><td><?= e($t['name']) ?><?= !$t['kata'] && $t['kumite'] === '' ? ' <span style="font-size:8pt">(nur im Team)</span>' : '' ?></td><td><?= $t['kata'] ? '✓' : '' ?></td><td><?= $t['kumite'] !== '' ? '✓' : '' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($startgeld['teams']): ?>
        <h2>Teams</h2>
        <table>
            <thead><tr><th>Team</th><th>Disziplin</th></tr></thead>
            <tbody>
            <?php foreach ($startgeld['teams'] as $t): ?>
                <tr><td><?= e($t['name']) ?></td><td><?= e(disziplin_text($t['disziplin'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php if ($hinweis !== ''): ?><p class="hinweis"><?= nl2br(e($hinweis)) ?></p><?php endif; ?>
</div>
<script src="<?= e(asset('coach.js')) ?>" defer></script>
</body>
</html>
