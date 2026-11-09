<?php
/**
 * Coaching-Portal: Platzierungen, Medaillenspiegel und Kampfverlauf des Dojos.
 * @var array<string, mixed> $portal
 * @var string $bereich
 * @var string $verein
 * @var list<array<string, mixed>> $platzierungen
 * @var array<int, int> $medaillen
 * @var list<array<string, mixed>> $kaempfe
 */
$this->layout('layout', ['titel' => 'Ergebnisse · ' . $portal['name'], 'aktiv' => 'coach']);
?>
<?= $this->teil('coach/kopf', compact('portal', 'bereich', 'verein')) ?>

<div class="raster">
    <?php foreach ([1 => '🥇 Gold', 2 => '🥈 Silber', 3 => '🥉 Bronze'] as $platz => $text): ?>
        <div class="karte"><div class="klein"><?= e($text) ?></div><div class="zahl" style="font-size:2rem;font-weight:800"><?= (int) $medaillen[$platz] ?></div></div>
    <?php endforeach; ?>
</div>

<section class="karte">
    <h2>Platzierungen</h2>
    <?php if (!$platzierungen): ?>
        <p class="leise">Noch keine Platzierungen.</p>
    <?php else: ?>
        <table class="tabelle">
            <thead><tr><th>Platz</th><th>Starter</th><th>Klasse</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($platzierungen as $p): ?>
                <tr>
                    <td class="zahl"><strong><?= (int) $p['platz'] ?>.</strong></td>
                    <td><?= e($p['name']) ?><?= $p['team'] ? ' <span class="tag">Team</span>' : '' ?></td>
                    <td><?= e($p['code']) ?> <span class="klein"><?= e($p['klasse']) ?></span></td>
                    <td><?= $p['bestaetigt'] ? '<span class="tag ok">bestätigt</span>' : '<span class="tag">vorläufig</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="karte">
    <h2>Kämpfe und Vorträge</h2>
    <?php if (!$kaempfe): ?>
        <p class="leise">Noch keine Ergebnisse.</p>
    <?php else: ?>
        <div class="tabelle-scroll">
        <table class="tabelle">
            <thead><tr><th>Starter</th><th>Klasse</th><th>Runde</th><th>Gegner</th><th>Ergebnis</th></tr></thead>
            <tbody>
            <?php foreach ($kaempfe as $k): ?>
                <tr>
                    <td><?= e($k['name']) ?></td>
                    <td><?= e($k['code']) ?></td>
                    <td class="klein"><?= e((string) $k['runde']) ?></td>
                    <td class="klein"><?= e((string) ($k['gegner'] ?? '')) ?></td>
                    <td><?= $k['sieg'] === null ? '' : ($k['sieg'] ? '<span class="tag ok">Sieg</span> ' : '<span class="tag">Niederlage</span> ') ?><span class="klein"><?= e($k['ergebnis']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
