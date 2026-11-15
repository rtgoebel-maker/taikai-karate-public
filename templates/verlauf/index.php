<?php
/**
 * Änderungsverlauf (Audit-Log): je Turnier bzw. global (Administration). Nur lesend.
 * @var array<string, mixed>|null $turnier
 * @var list<array<string, mixed>> $eintraege
 * @var int $gesamt
 * @var int $seite
 * @var int $seiten
 * @var list<string> $objekte
 * @var list<string> $aktionen
 * @var array{objekt: string, aktion: string, wer: string} $filter
 */
use Wettkampf\Kern\Protokoll;

$this->layout('layout', ['titel' => t('Verlauf'), 'turnier' => $turnier, 'aktiv' => 'verlauf']);
$pfad = $turnier ? "turnier/{$turnier['id']}/verlauf" : 'verlauf';
$aktiverFilter = array_filter($filter);
?>
<div class="karte">
    <div class="kopfzeile-karte">
        <h1><?= $turnier ? te('Änderungsverlauf') : te('Globaler Verlauf') ?></h1>
        <span class="leise"><?= te('{n} Einträge', ['n' => $gesamt]) ?></span>
    </div>
    <p class="hilfe">
        <?php if ($turnier): ?>
            <?= te('Wer hat wann was im Turnier geändert – Ergebnisse, Korrekturen, Freigaben, Klassen, Plan, Rechte. Der Verlauf gehört zum Turnier: er ist in der Sicherung enthalten und wird mit dem Turnier gelöscht.') ?>
        <?php else: ?>
            <?= te('Einträge ohne Turnier: Konten (angelegt, geändert, Passwort gesetzt), Sperren nach Fehlversuchen beim Login und gelöschte Turniere.') ?>
            <?= te('Sie werden nach {n} Tagen automatisch gelöscht.', ['n' => Protokoll::AUFBEWAHRUNG_GLOBAL_TAGE]) ?>
        <?php endif; ?>
    </p>
    <form method="get" class="knopfleiste filter">
        <select name="objekt" data-auto-absenden aria-label="<?= te('Objekt') ?>">
            <option value=""><?= te('Objekt: alle') ?></option>
            <?php foreach ($objekte as $o): ?><option value="<?= e($o) ?>"<?= $filter['objekt'] === $o ? ' selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
        </select>
        <select name="aktion" data-auto-absenden aria-label="<?= te('Aktion') ?>">
            <option value=""><?= te('Aktion: alle') ?></option>
            <?php foreach ($aktionen as $a): ?><option value="<?= e($a) ?>"<?= $filter['aktion'] === $a ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
        </select>
        <input type="search" name="wer" value="<?= e($filter['wer']) ?>" placeholder="<?= te('Wer (Name, Tisch …)') ?>" aria-label="<?= te('Wer') ?>" style="max-width:220px">
        <button class="knopf" type="submit"><?= te('Filtern') ?></button>
        <?php if ($aktiverFilter): ?><a href="<?= e(url($pfad)) ?>"><?= te('Filter zurücksetzen') ?></a><?php endif; ?>
    </form>
    <div class="tabelle-scroll">
    <table class="tabelle">
        <thead><tr><th><?= te('Zeitpunkt') ?></th><th><?= te('Wer') ?></th><th><?= te('Objekt') ?></th><th><?= te('Aktion') ?></th><th><?= te('Details') ?></th></tr></thead>
        <tbody>
        <?php foreach ($eintraege as $z): ?>
            <tr>
                <td style="white-space:nowrap"><?= e(date('d.m.y H:i:s', strtotime($z['zeit']))) ?></td>
                <td><?= e((string) ($z['benutzer'] ?? '–')) ?></td>
                <td><?= e($z['entitaet']) ?><?= $z['entitaet_id'] !== null ? ' <span class="leise">#' . (int) $z['entitaet_id'] . '</span>' : '' ?></td>
                <td><span class="tag"><?= e($z['aktion']) ?></span></td>
                <td class="klein">
                    <?php foreach ($z['details'] as [$schluessel, $wert]): ?>
                        <div><?php if ($schluessel !== ''): ?><span class="leise"><?= e($schluessel) ?>:</span> <?php endif; ?><?= e(mb_strimwidth($wert, 0, 300, '…')) ?></div>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$eintraege): ?><tr><td colspan="5" class="leise"><?= $aktiverFilter ? te('Keine Einträge mit diesem Filter.') : te('Keine Einträge.') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?php if ($seiten > 1): ?>
        <p class="knopfleiste">
            <?php if ($seite > 1): ?><a class="knopf" href="<?= e(url($pfad, $aktiverFilter + ['seite' => $seite - 1])) ?>">← <?= te('neuere') ?></a><?php endif; ?>
            <span class="leise"><?= te('Seite {seite} von {seiten}', ['seite' => $seite, 'seiten' => $seiten]) ?></span>
            <?php if ($seite < $seiten): ?><a class="knopf" href="<?= e(url($pfad, $aktiverFilter + ['seite' => $seite + 1])) ?>"><?= te('ältere') ?> →</a><?php endif; ?>
        </p>
    <?php endif; ?>
</div>
