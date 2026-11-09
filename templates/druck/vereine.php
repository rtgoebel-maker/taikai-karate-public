<?php
/**
 * @var array<string, mixed> $turnier
 * @var array<string, list<array<string, mixed>>> $vereine
 */
?>
<div class="blatt">
    <?= $this->teil('druck/kopf', ['turnier' => $turnier, 'ueberschrift' => 'Starter nach Verein', 'zusatz' => count($vereine) . ' Vereine']) ?>
    <?php foreach ($vereine as $verein => $starter): ?>
        <div class="keinumbruch">
            <h2><?= e($verein) ?> <span class="klein">(<?= count($starter) ?>)</span></h2>
            <table>
                <thead><tr><th style="width:12mm">Nr.</th><th>Name</th><th style="width:22mm">Grad</th><th>Klassen</th></tr></thead>
                <tbody>
                <?php foreach ($starter as $s): ?>
                    <tr><td><?= e($s['startnummer']) ?></td><td><?= e($s['nachname'] . ', ' . $s['vorname']) ?></td><td><?= e(grad((int) $s['graduierung'])) ?></td><td><?= e(str_replace(',', ', ', (string) $s['klassen'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</div>
