<?php
/**
 * Datenschutzhinweise der Turniere im Auskunftsbericht (Zwecke, Empfänger, Speicherdauer, Rechte, Herkunft – Art. 15 Abs. 1).
 * @var list<array{name: string, datum: string, abschnitte: list<array{titel: string, absaetze: list<string>, punkte: list<string>}>}> $turniere
 */
foreach ($turniere as $t): ?>
    <div class="blatt">
        <h2>Datenschutzhinweise: <?= e($t['name']) ?> (<?= e(datum_de($t['datum'])) ?>)</h2>
        <?php foreach ($t['abschnitte'] as $a): ?>
            <h3><?= e($a['titel']) ?></h3>
            <?php foreach ($a['absaetze'] as $p): ?><p><?= nl2br(e($p)) ?></p><?php endforeach; ?>
            <?php if ($a['punkte']): ?><ul><?php foreach ($a['punkte'] as $p): ?><li><?= nl2br(e($p)) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endforeach;
