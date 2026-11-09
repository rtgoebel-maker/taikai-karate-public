<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $klassen
 * @var bool $alle
 */
?>
<div class="blatt">
    <?= $this->teil('druck/kopf', ['turnier' => $turnier, 'ueberschrift' => 'Siegerliste', 'zusatz' => $alle ? 'inkl. vorläufiger Ergebnisse' : 'bestätigte Ergebnisse']) ?>
    <?php foreach ($klassen as $k): ?>
        <div class="keinumbruch">
            <h2><?= e($k['code']) ?> · <?= e($k['bezeichnung']) ?><?= $k['status'] !== 'bestaetigt' ? ' <span class="klein">(vorläufig)</span>' : '' ?></h2>
            <table class="podest">
                <thead><tr><th>Platz</th><th>Name</th><th>Verein</th></tr></thead>
                <tbody>
                <?php foreach ($k['platzierungen'] as $p): ?>
                    <tr><td class="platz"><?= $p['platz'] ?>.</td><td><?= e($p['name']) ?></td><td><?= e($p['verein_voll'] ?? $p['verein']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
    <?php if (!$klassen): ?><p>Noch keine <?= $alle ? 'abgeschlossenen' : 'bestätigten' ?> Klassen.</p><?php endif; ?>
</div>
