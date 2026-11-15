<?php
/**
 * Preisaufstellung einer Anmeldung.
 * @var array{posten: list<array{text: string, anzahl: int, einzel: int, summe: int}>, summe: int} $preis
 */
use Wettkampf\Meldung\AnmeldungService;
?>
<table class="tabelle preis-tabelle">
    <thead><tr><th><?= te('Posten') ?></th><th class="rechts"><?= te('Anzahl') ?></th><th class="rechts"><?= te('Einzelpreis') ?></th><th class="rechts"><?= te('Summe') ?></th></tr></thead>
    <tbody>
    <?php foreach ($preis['posten'] as $p): ?>
        <tr><td><?= e($p['text']) ?></td><td class="rechts"><?= $p['anzahl'] ?></td><td class="rechts"><?= e(AnmeldungService::euro($p['einzel'])) ?></td><td class="rechts"><?= e(AnmeldungService::euro($p['summe'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$preis['posten']): ?>
        <tr><td colspan="4" class="leise"><?= te('Noch keine Starts eingetragen.') ?></td></tr>
    <?php endif; ?>
    </tbody>
    <tfoot><tr><th colspan="3"><?= te('Gesamtpreis') ?></th><th class="rechts"><?= e(AnmeldungService::euro($preis['summe'])) ?></th></tr></tfoot>
</table>
