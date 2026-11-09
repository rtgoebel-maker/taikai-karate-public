<?php
/**
 * @var list<array<string, mixed>> $turniere nur die Turniere, die das Konto leiten darf
 * @var bool $istAdmin
 */
$this->layout('layout', ['titel' => 'Turniere']);
?>
<div class="titelzeile">
    <h1><?= $istAdmin ? 'Turniere' : 'Meine Turniere' ?></h1>
    <?php if ($istAdmin): ?><a class="knopf primaer" href="<?= e(url('turnier/neu')) ?>">Neues Turnier</a><?php endif; ?>
</div>
<?php if (!$turniere): ?>
    <div class="karte leer">
        <?php if ($istAdmin): ?>
            <p>Noch kein Turnier angelegt.</p>
            <p class="klein">Ein Turnier kann auch aus einer Sicherung importiert werden: <a href="<?= e(url('import-turnier')) ?>">Turnier importieren</a></p>
        <?php else: ?>
            <p>Diesem Konto ist noch kein Turnier zugeordnet. Die Administration oder eine Turnierleitung kann es zur Turnierleitung ernennen.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <table class="tabelle">
        <thead><tr><th>Datum</th><th>Turnier</th><th>Ort</th><th>Tatamis</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($turniere as $t): ?>
            <tr>
                <td><?= e(datum_de($t['datum'])) ?></td>
                <td><a href="<?= e(url("turnier/{$t['id']}")) ?>"><strong><?= e($t['name']) ?></strong></a></td>
                <td><?= e($t['ort']) ?></td>
                <td><?= e($t['anzahl_tatamis']) ?></td>
                <td><span class="phase-marke phase-<?= e(\Wettkampf\Domain\TurnierPhase::von($t)) ?>"><?= e(\Wettkampf\Domain\TurnierPhase::text(\Wettkampf\Domain\TurnierPhase::von($t))) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($istAdmin): ?><p class="klein"><a href="<?= e(url('import-turnier')) ?>">Turnier aus Sicherung importieren</a></p><?php endif; ?>
<?php endif; ?>
