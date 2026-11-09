<?php
/**
 * Kampfliste für die Tischbesatzung einer Tatami.
 * @var array<string, mixed> $turnier
 * @var array<string, mixed> $tatami
 * @var list<array{klasse: array<string, mixed>, phase: array<string, mixed>}> $bloecke
 */
// Klassen mit Punktwertung (Kata) quer, damit Kata und Wertungen Platz haben; aufeinanderfolgende
// Klassen gleichen Formats teilen sich ein Blatt
$quer = [];
foreach ($bloecke as ['klasse' => $k, 'phase' => $p]) {
    $quer[$k['id']] = ($quer[$k['id']] ?? false) || $p['art'] === 'auftritte';
}
$blaetter = [];
foreach ($bloecke as $block) {
    $q = $quer[$block['klasse']['id']];
    if (!$blaetter || end($blaetter)['quer'] !== $q) {
        $blaetter[] = ['quer' => $q, 'bloecke' => []];
    }
    $blaetter[array_key_last($blaetter)]['bloecke'][] = $block;
}
$blaetter = $blaetter ?: [['quer' => false, 'bloecke' => []]];
?>
<?php foreach ($blaetter as $blatt): ?>
<div class="blatt<?= $blatt['quer'] ? ' quer' : '' ?>">
    <?= $this->teil('druck/kopf', ['turnier' => $turnier, 'ueberschrift' => 'Kampfliste ' . $tatami['bezeichnung'], 'zusatz' => 'Tischbesatzung']) ?>
    <?= $this->teil('druck/kampf_bloecke', ['bloecke' => $blatt['bloecke']]) ?>
    <?php if (!$bloecke): ?><p>Dieser Tatami sind noch keine Phasen zugeordnet (Tatami-Plan).</p><?php endif; ?>
    <div class="unterschrift"><div>Tischbesatzung</div><div>Hauptkampfrichter</div><div>Wettkampfleitung</div></div>
</div>
<?php endforeach; ?>
