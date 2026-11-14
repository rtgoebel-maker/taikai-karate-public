<?php
/**
 * Öffentliche Datenschutzhinweise eines Turniers (Art. 13/14 DSGVO), erreichbar über den Anmeldelink bzw. die Statusseite.
 * @var array<string, mixed> $turnier
 * @var list<array{titel: string, absaetze: list<string>, punkte: list<string>}> $abschnitte
 * @var bool $vollstaendig
 * @var string $zurueck
 */
$this->layout('layout_einfach', ['titel' => 'Datenschutz · ' . $turnier['name']]);
?>
<p class="obertitel"><a href="<?= e($zurueck) ?>"><?= e($turnier['name']) ?></a></p>
<h1>Datenschutzhinweise zum Turnier</h1>
<p class="leise"><?= e($turnier['name']) ?> · <?= e(datum_de((string) $turnier['datum'])) ?><?= $turnier['ort'] ? ' · ' . e($turnier['ort']) : '' ?></p>
<?php if (!$vollstaendig): ?>
    <div class="meldung meldung-hinweis">Der Veranstalter hat die Angaben zum Datenschutz noch nicht vollständig eingetragen.</div>
<?php endif; ?>
<?php foreach ($abschnitte as $a): ?>
    <section class="hinweis-abschnitt">
        <h2><?= e($a['titel']) ?></h2>
        <?php foreach ($a['absaetze'] as $p): ?><p><?= nl2br(e($p)) ?></p><?php endforeach; ?>
        <?php if ($a['punkte']): ?><ul><?php foreach ($a['punkte'] as $p): ?><li><?= nl2br(e($p)) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
<?php endforeach; ?>
