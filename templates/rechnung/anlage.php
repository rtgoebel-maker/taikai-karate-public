<?php
/**
 * Anlage zu Rechnung bzw. Quittung: gemeldete Starter auf einer eigenen Seite (nur, wenn unter Rechnungen eingeschaltet).
 * Bewusst getrennt vom Beleg: Der Beleg wird 8 Jahre aufbewahrt, die Anlage mit Namen muss es nicht.
 *
 * @var array<string, mixed> $turnier
 * @var array{verein: string, ansprechpartner: string, email: string} $empfaenger
 * @var string $nummer
 * @var string $art „Rechnung“ bzw. „Quittung“ (übersetzt)
 * @var list<array{name: string, kata: bool, kumite: string}> $teilnehmer
 * @var list<array{name: string, disziplin: string}> $teams
 */
?>
<div class="blatt rechnung anlage">
    <h1 class="r-titel"><?= te('Anlage zur {art} Nr. {nummer}', ['art' => $art, 'nummer' => $nummer]) ?></h1>
    <p><strong><?= e($empfaenger['verein']) ?></strong> · <?= e($turnier['name']) ?>, <?= e(datum_de($turnier['datum'])) ?></p>
    <h2><?= te('Gemeldete Starter') ?> <span class="klein">(<?= count($teilnehmer) ?><?= $teams ? ' · ' . te('{n} Teams', ['n' => count($teams)]) : '' ?>)</span></h2>
    <table class="r-starter">
        <thead><tr><th><?= te('Name') ?></th><th>Kata</th><th>Kumite</th></tr></thead>
        <tbody>
        <?php foreach ($teilnehmer as $t): ?>
            <tr><td><?= e($t['name']) ?><?= !$t['kata'] && $t['kumite'] === '' ? ' <span class="klein">' . te('(nur im Team)') . '</span>' : '' ?></td><td><?= $t['kata'] ? '✓' : '' ?></td><td><?= $t['kumite'] !== '' ? '✓' : '' ?></td></tr>
        <?php endforeach; ?>
        <?php foreach ($teams as $t): ?>
            <tr><td><?= e($t['name']) ?> <span class="klein"><?= te('(Team)') ?></span></td><td colspan="2"><?= e(disziplin_text($t['disziplin'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="klein"><?= te('Diese Anlage ist kein Teil des Belegs und muss nicht mit ihm aufbewahrt werden.') ?></p>
</div>
