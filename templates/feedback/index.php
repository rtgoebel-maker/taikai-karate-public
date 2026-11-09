<?php
/**
 * @var list<array<string, mixed>> $eintraege
 * @var string|null $status
 * @var string|null $art
 * @var bool $aktiv
 * @var int $anzahlNeu  Einträge im Status „neu“ (nur diese werden exportiert)
 */
use Wettkampf\Kommunikation\FeedbackService;

$this->layout('layout', ['titel' => 'Feedback', 'aktiv' => 'feedback']);
$filter = array_filter(['status' => $status, 'art' => $art]);
?>
<div class="karte feedback-liste">
    <div class="kopfzeile-karte">
        <h1>Feedback und Fehlermeldungen <?= info('feedback') ?></h1>
        <div class="knopfleiste">
            <?php if ($anzahlNeu > 0): ?>
                <a class="knopf" href="<?= e(url('feedback/export', ['format' => 'zip'])) ?>">Neue exportieren (<?= $anzahlNeu ?>, ZIP mit Screenshots)</a>
                <a class="knopf" href="<?= e(url('feedback/export')) ?>">Neue exportieren (<?= $anzahlNeu ?>, JSON)</a>
            <?php else: ?>
                <span class="leise klein">Keine neuen Meldungen zum Exportieren.</span>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$aktiv): ?>
        <p class="meldung meldung-hinweis">Der Feedback-Knopf ist ausgeschaltet – einschalten unter <a href="<?= e(url('system')) ?>">System</a>.</p>
    <?php endif; ?>
    <form method="get" class="knopfleiste filter">
        <select name="status" data-auto-absenden aria-label="Status">
            <option value="">Status: alle</option>
            <?php foreach (FeedbackService::STATUS as $w => $t): ?><option value="<?= e($w) ?>"<?= $status === $w ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
        </select>
        <select name="art" data-auto-absenden aria-label="Art">
            <option value="">Art: alle</option>
            <?php foreach (FeedbackService::ARTEN as $w => $t): ?><option value="<?= e($w) ?>"<?= $art === $w ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
        </select>
        <span class="leise" style="margin-left:auto"><?= count($eintraege) ?></span>
    </form>
    <table class="tabelle" data-speichern="<?= e(url('feedback')) ?>">
        <thead><tr><th>Zeitpunkt</th><th>Art</th><th>Beschreibung</th><th>Screenshot</th><th style="width:230px">Bearbeitung</th></tr></thead>
        <tbody>
        <?php foreach ($eintraege as $f): ?>
            <tr data-feedback="<?= (int) $f['id'] ?>">
                <td><?= e(date('d.m.y, H:i', strtotime($f['erstellt_am']))) ?><br><span class="klein leise"><?= e($f['von']) ?></span></td>
                <td><span class="tag fb-art-<?= e($f['art']) ?>"><?= e(FeedbackService::ARTEN[$f['art']] ?? $f['art']) ?></span></td>
                <td>
                    <div class="fb-beschreibung"><?= nl2br(e($f['beschreibung'])) ?></div>
                    <a class="klein" href="<?= e($f['seite']) ?>"><?= e($f['seite']) ?></a>
                    <?php if ($f['turnier']): ?><span class="klein leise"> · <?= e($f['turnier']) ?></span><?php endif; ?>
                    <?php if ($f['geraet']): ?><div class="klein leise" title="<?= e($f['geraet']) ?>"><?= e(mb_strimwidth($f['geraet'], 0, 60, '…')) ?></div><?php endif; ?>
                </td>
                <td>
                    <?php if ($f['hat_screenshot']): ?>
                        <a href="<?= e(url("feedback/{$f['id']}/screenshot")) ?>" target="_blank" rel="noopener"><img class="fb-vorschau" src="<?= e(url("feedback/{$f['id']}/screenshot")) ?>" alt="Screenshot" loading="lazy"></a>
                    <?php else: ?><span class="leise">…</span><?php endif; ?>
                </td>
                <td>
                    <select class="fb-status" aria-label="Status">
                        <?php foreach (FeedbackService::STATUS as $w => $t): ?><option value="<?= e($w) ?>"<?= $f['status'] === $w ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
                    </select>
                    <textarea class="fb-notiz" rows="2" placeholder="Notiz (wird automatisch gespeichert)" aria-label="Notiz"><?= e((string) $f['notiz']) ?></textarea>
                    <div class="fb-zeile">
                        <span class="fb-gespeichert klein leise"></span>
                        <form method="post" action="<?= e(url("feedback/{$f['id']}/loeschen", $filter)) ?>" data-bestaetigen="Dieses Feedback endgültig löschen?">
                            <?= csrf_feld() ?>
                            <button class="link gefahr" type="submit">Löschen</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$eintraege): ?><tr><td colspan="5" class="leise">Noch kein Feedback<?= $filter ? ' mit diesem Filter' : '' ?>.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<script src="<?= e(asset('feedback-liste.js')) ?>" defer></script>
