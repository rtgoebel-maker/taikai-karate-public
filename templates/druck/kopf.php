<?php
/**
 * Seitenkopf eines Druckblatts.
 * @var array<string, mixed> $turnier
 * @var string $ueberschrift
 * @var string|null $zusatz
 * @var bool|null $tatamiFeld   Feld „Tatami: ____“ zum Eintragen von Hand
 * @var string|null $plan       Tatami laut Plan (klein unter dem Feld)
 */
?>
<div class="kopf">
    <div>
        <div class="klein"><?= e($turnier['name']) ?></div>
        <h1><?= e($ueberschrift) ?></h1>
    </div>
    <?php if (!empty($tatamiFeld)): ?>
        <div class="tatami-feld">Tatami<span class="feld-linie"></span><?php if (!empty($plan)): ?><span class="plan">laut Plan: <?= e($plan) ?></span><?php endif; ?></div>
    <?php endif; ?>
    <div class="rechts">
        <?= e(datum_de($turnier['datum'])) ?><?= $turnier['ort'] ? ' · ' . e($turnier['ort']) : '' ?><br>
        <?= e($zusatz ?? '') ?><br>
        <span class="klein">Stand: <?= date('d.m.Y H:i') ?></span>
    </div>
</div>
