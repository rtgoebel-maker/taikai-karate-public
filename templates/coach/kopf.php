<?php
/**
 * Kopf des Coaching-Portals eines Turniers: Titel und Reiter der freigegebenen Bereiche.
 * @var array<string, mixed> $portal Turnier + 'portal'
 * @var string $bereich aktiver Bereich
 * @var string $verein
 */
use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Meldung\CoachService;

$tid = (int) $portal['id'];
?>
<p class="obertitel"><a href="<?= e(url('coach')) ?>">Coach · <?= e($verein) ?></a></p>
<h1><?= e($portal['name']) ?></h1>
<p class="leise"><?= e(datum_de($portal['datum'])) ?><?= $portal['ort'] ? ' · ' . e($portal['ort']) : '' ?> · <?= e(TurnierPhase::text(TurnierPhase::von($portal))) ?></p>
<nav class="coach-reiter">
    <?php foreach (CoachService::BEREICHE as $b => $text): ?>
        <?php if ($portal['portal']['bereiche'][$b]): ?>
            <a href="<?= e(url("coach/turnier/$tid/$b")) ?>"<?= $b === $bereich ? ' class="aktiv"' : '' ?>><?= e($text) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
