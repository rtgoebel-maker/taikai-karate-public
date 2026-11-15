<?php
/**
 * Automatische Sicherung: enthaltene Turniere, einzeln als Kopie wiederherstellen.
 * @var array{name: string, zeit: int, groesse: int} $stand
 * @var list<array{id: int, name: string, datum: string|null}> $turniere
 */
$this->layout('layout', ['titel' => t('Sicherung wiederherstellen')]);
?>
<p class="obertitel"><a href="<?= e(url('import-turnier')) ?>"><?= te('Sicherung & Umzug') ?></a></p>
<h1><?= te('Stand vom {zeit}', ['zeit' => date('d.m.Y H:i:s', $stand['zeit'])]) ?></h1>
<p class="leise"><?= th('Ein Turnier aus diesem Stand wird <strong>als eigenes, neues Turnier</strong> angelegt – das aktuelle Turnier bleibt unverändert.') ?>
    <?= te('So lässt sich vergleichen und danach das nicht mehr benötigte löschen. Tatami- und Leitungs-PINs bleiben erhalten.') ?></p>
<section class="karte">
    <?php if (!$turniere): ?>
        <p class="leise"><?= te('Dieser Stand enthält keine Turniere.') ?></p>
    <?php else: ?>
        <table class="tabelle">
            <thead><tr><th><?= te('Turnier') ?></th><th><?= te('Datum') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($turniere as $t): ?>
                <tr>
                    <td><?= e($t['name']) ?></td>
                    <td><?= $t['datum'] ? e(datum_de($t['datum'])) : '' ?></td>
                    <td class="rechts">
                        <form method="post" action="<?= e(url('sicherung/auto/' . $stand['name'] . '/turnier/' . $t['id'])) ?>" data-bestaetigen="<?= te('Turnier „{name}“ aus diesem Stand als neues Turnier anlegen?', ['name' => $t['name']]) ?>">
                            <?= csrf_feld() ?>
                            <button class="knopf" type="submit"><?= te('Als Kopie wiederherstellen') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
