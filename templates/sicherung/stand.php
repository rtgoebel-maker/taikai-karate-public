<?php
/**
 * Automatische Sicherung: enthaltene Turniere, einzeln als Kopie wiederherstellen.
 * @var array{name: string, zeit: int, groesse: int} $stand
 * @var list<array{id: int, name: string, datum: string|null}> $turniere
 */
$this->layout('layout', ['titel' => 'Sicherung wiederherstellen']);
?>
<p class="obertitel"><a href="<?= e(url('import-turnier')) ?>">Sicherung &amp; Umzug</a></p>
<h1>Stand vom <?= e(date('d.m.Y H:i:s', $stand['zeit'])) ?></h1>
<p class="leise">Ein Turnier aus diesem Stand wird <strong>als eigenes, neues Turnier</strong> angelegt – das aktuelle Turnier bleibt unverändert.
    So lässt sich vergleichen und danach das nicht mehr benötigte löschen. Tatami- und Leitungs-PINs bleiben erhalten.</p>
<section class="karte">
    <?php if (!$turniere): ?>
        <p class="leise">Dieser Stand enthält keine Turniere.</p>
    <?php else: ?>
        <table class="tabelle">
            <thead><tr><th>Turnier</th><th>Datum</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($turniere as $t): ?>
                <tr>
                    <td><?= e($t['name']) ?></td>
                    <td><?= $t['datum'] ? e(datum_de($t['datum'])) : '' ?></td>
                    <td class="rechts">
                        <form method="post" action="<?= e(url('sicherung/auto/' . $stand['name'] . '/turnier/' . $t['id'])) ?>" data-bestaetigen="Turnier „<?= e($t['name']) ?>“ aus diesem Stand als neues Turnier anlegen?">
                            <?= csrf_feld() ?>
                            <button class="knopf" type="submit">Als Kopie wiederherstellen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
