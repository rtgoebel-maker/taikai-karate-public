<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $klassen
 * @var bool $alle
 */
?>
<div class="blatt">
    <?= $this->teil('druck/kopf', ['turnier' => $turnier, 'ueberschrift' => t('Siegerliste'), 'zusatz' => $alle ? t('inkl. vorläufiger Ergebnisse') : t('bestätigte Ergebnisse')]) ?>
    <?php foreach ($klassen as $k): ?>
        <div class="keinumbruch">
            <h2><?= e($k['code']) ?> · <?= e($k['bezeichnung']) ?><?= $k['status'] !== 'bestaetigt' ? ' <span class="klein">' . te('(vorläufig)') . '</span>' : '' ?></h2>
            <table class="podest">
                <thead><tr><th><?= te('Platz') ?></th><th><?= te('Name') ?></th><th><?= te('Verein') ?></th></tr></thead>
                <tbody>
                <?php foreach ($k['platzierungen'] as $p): ?>
                    <tr><td class="platz"><?= $p['platz'] ?>.</td><td><?= e($p['name']) ?></td><td><?= e($p['verein_voll'] ?? $p['verein']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
    <?php if (!$klassen): ?><p><?= $alle ? te('Noch keine abgeschlossenen Klassen.') : te('Noch keine bestätigten Klassen.') ?></p><?php endif; ?>
</div>
