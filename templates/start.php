<?php
/**
 * @var list<array<string, mixed>> $turniere nur die Turniere, die das Konto leiten darf (Administration: gefiltert)
 * @var bool $istAdmin
 * @var array{q: string, status: string, art: string} $filter
 * @var int $alle Anzahl ohne Filter
 */
use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Turnier\Http\StartController;

$this->layout('layout', ['titel' => t('Turniere')]);
$gefiltert = $filter['q'] !== '' || $filter['status'] !== '' || $filter['art'] !== '';
$mitDemo = $istAdmin && array_filter($turniere, static fn(array $t) => $t['demo'] !== null) !== [];
?>
<div class="titelzeile">
    <h1><?= $istAdmin ? te('Turniere') : te('Meine Turniere') ?></h1>
    <?php if ($istAdmin || Session::istDemo()): ?><a class="knopf primaer" href="<?= e(url('turnier/neu')) ?>"><?= te('Neues Turnier') ?></a><?php endif; ?>
</div>
<?php if ($istAdmin && $alle > 0): ?>
    <form method="get" action="<?= e(url('')) ?>" class="karte turnier-filter">
        <label><?= te('Suche') ?> <input type="search" name="q" value="<?= e($filter['q']) ?>" placeholder="<?= te('Name oder Ort') ?>" maxlength="100"></label>
        <label><?= te('Status') ?>
            <select name="status">
                <option value=""><?= te('alle') ?></option>
                <?php foreach (array_keys(TurnierPhase::ALLE) as $phase): ?>
                    <option value="<?= e($phase) ?>"<?= sel($filter['status'], $phase) ?>><?= e(TurnierPhase::text($phase)) ?></option>
                <?php endforeach; ?>
            </select></label>
        <label><?= te('Art') ?>
            <select name="art">
                <option value=""><?= te('alle') ?></option>
                <?php foreach (StartController::ARTEN as $wert => $text): ?>
                    <option value="<?= e($wert) ?>"<?= sel($filter['art'], $wert) ?>><?= te($text) ?></option>
                <?php endforeach; ?>
            </select></label>
        <div class="knopfleiste">
            <button class="knopf" type="submit"><?= te('Filtern') ?></button>
            <?php if ($gefiltert): ?><a href="<?= e(url('')) ?>"><?= te('Filter zurücksetzen') ?></a> <span class="leise klein"><?= te('{n} von {alle}', ['n' => count($turniere), 'alle' => $alle]) ?></span><?php endif; ?>
        </div>
    </form>
<?php endif; ?>
<?php if (!$turniere): ?>
    <div class="karte leer">
        <?php if ($istAdmin && $gefiltert): ?>
            <p><?= te('Kein Turnier passt zum Filter.') ?> <a href="<?= e(url('')) ?>"><?= te('Filter zurücksetzen') ?></a></p>
        <?php elseif ($istAdmin): ?>
            <p><?= te('Noch kein Turnier angelegt.') ?></p>
            <p class="klein"><?= te('Ein Turnier kann auch aus einer Sicherung importiert werden:') ?> <a href="<?= e(url('import-turnier')) ?>"><?= te('Turnier importieren') ?></a></p>
        <?php else: ?>
            <p><?= te('Diesem Konto ist noch kein Turnier zugeordnet. Die Administration oder eine Turnierleitung kann es zur Turnierleitung ernennen.') ?></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <table class="tabelle">
        <thead><tr><th><?= te('Datum') ?></th><th><?= te('Turnier') ?></th><th><?= te('Ort') ?></th><th><?= te('Tatamis') ?></th><th><?= te('Status') ?></th><?php if ($mitDemo): ?><th><?= te('Demo') ?></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($turniere as $t): ?>
            <tr>
                <td><?= e(datum_de($t['datum'])) ?></td>
                <td><a href="<?= e(url("turnier/{$t['id']}")) ?>"><strong><?= e($t['name']) ?></strong></a></td>
                <td><?= e($t['ort']) ?></td>
                <td><?= e($t['anzahl_tatamis']) ?></td>
                <td><span class="phase-marke phase-<?= e(TurnierPhase::von($t)) ?>"><?= e(TurnierPhase::text(TurnierPhase::von($t))) ?></span></td>
                <?php if ($mitDemo): ?>
                    <td class="klein">
                        <?php if ($t['demo'] !== null): ?>
                            <?= $t['demo']['besitzer'] !== null ? te('für {name}', ['name' => $t['demo']['besitzer']]) : te('gemeinsam') ?>
                            <?php if ($t['demo']['freigegeben']): ?><br><?= te('freigegeben für {namen}', ['namen' => implode(', ', $t['demo']['freigegeben'])]) ?><?php endif; ?>
                            <?php if ($t['demo']['loeschen_ab'] !== null): ?>
                                <br><span class="tag fehler" title="<?= te('Der Demo-Zugang wurde entzogen, deaktiviert oder gelöscht.') ?>"><?= te('Löschung am {datum} um {uhrzeit}', ['datum' => datum_de(substr($t['demo']['loeschen_ab'], 0, 10)), 'uhrzeit' => substr($t['demo']['loeschen_ab'], 11, 5)]) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($istAdmin || Session::istDemo()): ?><p class="klein"><a href="<?= e(url('import-turnier')) ?>"><?= te('Turnier aus Sicherung importieren') ?></a></p><?php endif; ?>
<?php endif; ?>
