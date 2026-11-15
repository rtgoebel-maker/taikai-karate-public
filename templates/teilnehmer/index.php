<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $teilnehmer
 * @var list<array<string, mixed>> $vereine
 * @var string $suche
 * @var int|null $vereinId
 * @var string $status Filter
 */
use Wettkampf\Kern\Sprache;
use Wettkampf\Turnier\StammdatenService;

$this->layout('layout', ['titel' => t('Teilnehmer'), 'turnier' => $turnier, 'aktiv' => 'teilnehmer']);
$tid = $turnier['id'];
$statusTag = ['gemeldet' => '', 'anwesend' => 'ok', 'gesperrt' => 'fehler', 'abgemeldet' => '', 'nicht_erschienen' => 'fehler'];
?>
<div class="titelzeile">
    <h1><?= te('Teilnehmer') ?> <span class="leise">(<?= count($teilnehmer) ?>)</span> <?= info('teilnehmer') ?></h1>
    <a class="knopf" href="<?= e(url("turnier/$tid/import")) ?>"><?= te('Import (XLSX/CSV)') ?></a>
    <a class="knopf primaer" href="<?= e(url("turnier/$tid/teilnehmer/neu")) ?>"><?= te('Teilnehmer erfassen') ?></a>
</div>
<form method="get" class="knopfleiste karte">
    <input name="q" value="<?= e($suche) ?>" placeholder="<?= te('Name oder Verein suchen') ?>" style="max-width:320px">
    <select name="verein" style="max-width:280px">
        <option value=""><?= te('Alle Vereine') ?></option>
        <?php foreach ($vereine as $v): ?>
            <option value="<?= $v['id'] ?>"<?= sel($v['id'], $vereinId) ?>><?= e($v['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status" style="max-width:200px" aria-label="<?= te('Status') ?>">
        <option value=""><?= te('Alle Status') ?></option>
        <?php foreach (StammdatenService::STATUS as $wert => $text): ?>
            <option value="<?= $wert ?>"<?= sel($wert, $status) ?>><?= e(Sprache::t($text)) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="knopf" type="submit"><?= te('Filtern') ?></button>
</form>
<div class="tabelle-scroll">
<table class="tabelle">
    <thead><tr><th><?= te('Nr.') ?></th><th><?= te('Name') ?></th><th><?= te('Verein') ?></th><th><?= te('G.') ?></th><th><?= te('Alter') ?></th><th><?= te('Grad') ?></th><th><?= te('Meldung') ?></th><th><?= te('Klassen') ?></th><th><?= te('Status') ?></th></tr></thead>
    <tbody>
    <?php foreach ($teilnehmer as $t): ?>
        <tr<?= in_array($t['status'], ['abgemeldet', 'nicht_erschienen'], true) ? ' class="inaktiv"' : '' ?>>
            <td class="zahl"><?= e($t['startnummer']) ?></td>
            <td><a href="<?= e(url("turnier/$tid/teilnehmer/{$t['id']}")) ?>"><strong><?= e($t['nachname']) ?></strong>, <?= e($t['vorname']) ?></a></td>
            <td><?= e($t['verein']) ?></td>
            <td><?= e(strtoupper($t['geschlecht'])) ?></td>
            <td class="zahl"><?= e($t['alter']) ?><?= $t['geburtsdatum'] ? '' : '*' ?></td>
            <td class="nowrap"><?= e(grad((int) $t['graduierung'])) ?></td>
            <td><?= e(implode(', ', array_map('disziplin_text', array_filter(explode(',', (string) $t['disziplinen']))))) ?></td>
            <td>
                <?php foreach ($t['klassen'] as $k): ?>
                    <a class="tag info" href="<?= e(url("turnier/$tid/klasse/{$k['id']}")) ?>"><?= e($k['code']) ?></a>
                <?php endforeach; ?>
                <?php if ($t['disziplinen'] && !$t['klassen'] && !in_array($t['status'], ['abgemeldet', 'nicht_erschienen'], true)): ?>
                    <span class="tag warn"><?= te('keine Klasse') ?></span>
                <?php endif; ?>
            </td>
            <td>
                <span class="tag <?= $statusTag[$t['status']] ?? '' ?>"><?= e(status_text((string) $t['status'])) ?></span>
                <?php if (!in_array($t['status'], StammdatenService::AUSGEFALLEN, true)): ?>
                    <form method="post" action="<?= e(url("turnier/$tid/teilnehmer/{$t['id']}/nicht-erschienen")) ?>" class="inline"
                          data-bestaetigen="<?= te('{name} als nicht erschienen kennzeichnen? Alle offenen Kämpfe gehen kampflos (Kiken) an die Gegner.', ['name' => "{$t['vorname']} {$t['nachname']}"]) ?>">
                        <?= csrf_feld() ?><input type="hidden" name="zurueck" value="liste"><input type="hidden" name="q" value="<?= e($suche) ?>"><input type="hidden" name="status" value="<?= e($status) ?>">
                        <button class="knopf klein" type="submit" title="<?= te('Als nicht erschienen kennzeichnen') ?>"><?= te('nicht erschienen') ?></button>
                    </form>
                <?php endif; ?>
                <?php if ($t['ausweis_geprueft']): ?><span class="tag ok" title="<?= te('Ausweis geprüft') ?>">✓ <?= te('Ausweis') ?></span><?php endif; ?>
                <?php if ($t['nachmeldung']): ?><span class="tag warn"><?= te('Nachmeldung') ?></span><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$teilnehmer): ?>
        <tr><td colspan="9" class="leise"><?= te('Keine Teilnehmer gefunden.') ?></td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
<p class="klein"><?= te('* Alter aus der Meldung übernommen (kein Geburtsdatum vorhanden).') ?></p>
