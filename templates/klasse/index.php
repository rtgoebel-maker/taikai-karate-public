<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $klassen
 * @var string $filter
 * @var string $besetzung  '' | besetzt | statt | faellt_aus | leer
 * @var string $ansicht    karten | tabelle
 * @var int $alle          Anzahl aller Klassen (ohne Filter)
 * @var array<int, string> $tatamis Klasse → Tatami-Nummern
 * @var array<int, list<array<string, mixed>>> $vorschlaege
 * @var array<string, string> $vorlagen
 */
use Wettkampf\Durchfuehrung\KlassenService;
use Wettkampf\Domain\Graduierung;

$this->layout('layout', ['titel' => t('Klassen'), 'turnier' => $turnier, 'aktiv' => 'klassen']);
$tid = $turnier['id'];
$modusText = ['auto' => t('K.-o.'), 'pools' => t('Pools + Finalrunde'), 'punkte' => t('Punkte-Vorrunde'), 'jgj' => t('Jeder gegen jeden')];
$statusTag = ['offen' => '', 'ausgelost' => 'info', 'laeuft' => 'warn', 'beendet' => 'ok', 'bestaetigt' => 'ok', 'zusammengelegt' => ''];
$gruppen = [];
foreach ($klassen as $k) {
    $gruppen[$k['disziplin']][] = $k;
}
$offeneMitStartern = count(array_filter($klassen, static fn ($k) => $k['status'] === 'offen' && KlassenService::findetStatt($k, (int) $k['anzahl'])));
$faelltAus = static fn (array $k) => $k['status'] !== 'zusammengelegt' && (int) $k['anzahl'] > 0 && !KlassenService::findetStatt($k, (int) $k['anzahl']);
$alterKurz = static fn (array $k) => $k['alter_min'] === null && $k['alter_max'] === null ? t('alle Alter') : ($k['alter_max'] === null ? t('ab {von} J.', ['von' => $k['alter_min']]) : ($k['alter_min'] === null ? t('bis {bis} J.', ['bis' => $k['alter_max']]) : t('{von}–{bis} J.', ['von' => $k['alter_min'], 'bis' => $k['alter_max']])));
$bereich = static fn (array $k) => $alterKurz($k)
    . ' · ' . Graduierung::bereichText($k['grad_min'] === null ? null : (int) $k['grad_min'], $k['grad_max'] === null ? null : (int) $k['grad_max']);
?>
<div class="titelzeile">
    <h1><?= te('Klassen') ?> <span class="leise">(<?= count($klassen) === $alle ? $alle : te('{n} von {alle}', ['n' => count($klassen), 'alle' => $alle]) ?>)</span> <?= info('klassen') ?></h1>
    <form method="post" action="<?= e(url("turnier/$tid/klassen/zuordnen")) ?>" class="inline">
        <?= csrf_feld() ?><button class="knopf" type="submit" title="<?= te('Alle Meldungen erneut den Klassen zuordnen') ?>"><?= te('Zuordnung aktualisieren') ?></button>
    </form>
    <?php if ($offeneMitStartern > 0): ?>
        <form method="post" action="<?= e(url("turnier/$tid/auslosung/alle")) ?>" class="inline" data-bestaetigen="<?= te('Alle offenen Klassen auslosen, die ihre Mindestanzahl an Startern erreichen?') ?>">
            <?= csrf_feld() ?><button class="knopf" type="submit"><?= te('Alle offenen auslosen ({n})', ['n' => $offeneMitStartern]) ?></button>
        </form>
    <?php endif; ?>
    <a class="knopf primaer" href="<?= e(url("turnier/$tid/klasse/neu")) ?>"><?= te('Neue Klasse') ?></a>
</div>

<div class="karte knopfleiste">
    <form method="get" class="knopfleiste">
        <select name="disziplin" data-auto-absenden style="max-width:220px" aria-label="<?= te('Disziplin') ?>">
            <option value=""><?= te('Alle Disziplinen') ?></option>
            <?php foreach (['kata', 'kumite', 'kata_team', 'kumite_team'] as $d): ?>
                <option value="<?= $d ?>"<?= sel($d, $filter) ?>><?= e(disziplin_text($d)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="besetzung" data-auto-absenden style="max-width:260px" aria-label="<?= te('Besetzung') ?>">
            <?php foreach (['' => t('Alle Klassen'), 'besetzt' => t('Mit Teilnehmern'), 'statt' => t('Findet statt (Mindestanzahl erreicht)'), 'faellt_aus' => t('Fällt aus (zu wenige Starter)'), 'leer' => t('Ohne Teilnehmer')] as $wert => $text): ?>
                <option value="<?= $wert ?>"<?= sel($wert, $besetzung) ?>><?= e($text) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="ansicht" value="<?= e($ansicht) ?>">
        <span class="umschalter">
            <?php foreach (['karten' => t('Karten'), 'tabelle' => t('Tabelle')] as $wert => $text): ?>
                <a class="knopf klein<?= $ansicht === $wert ? ' aktiv' : '' ?>" href="<?= e(url("turnier/$tid/klassen", array_filter(['disziplin' => $filter, 'besetzung' => $besetzung, 'ansicht' => $wert === 'karten' ? '' : $wert]))) ?>"><?= e($text) ?></a>
            <?php endforeach; ?>
        </span>
    </form>
    <form method="post" action="<?= e(url("turnier/$tid/klassen/vorlage")) ?>" class="knopfleiste" style="margin-left:auto">
        <?= csrf_feld() ?>
        <select name="vorlage" style="max-width:420px">
            <?php foreach ($vorlagen as $schluessel => $text): ?><option value="<?= e($schluessel) ?>"><?= e($text) ?></option><?php endforeach; ?>
        </select>
        <button class="knopf" type="submit"><?= te('Vorlage laden') ?></button>
    </form>
</div>

<?php if (!$klassen): ?>
    <div class="karte leer"><p><?= te('Noch keine Klassen. Am schnellsten: eine Vorlage laden und anschließend anpassen.') ?></p></div>
<?php endif; ?>

<?php if ($klassen === [] && $alle > 0): ?>
    <div class="karte leer"><p><?= te('Keine Klasse passt zum Filter.') ?></p></div>
<?php endif; ?>

<?php if ($ansicht === 'tabelle'): ?>
    <?php foreach ($gruppen as $disziplin => $liste): ?>
        <h2><?= e(disziplin_text($disziplin)) ?></h2>
        <div class="tabelle-scroll">
        <table class="tabelle klassentabelle">
            <thead><tr><th><?= te('Code') ?></th><th><?= te('Klasse') ?></th><th><?= te('Geschlecht') ?></th><th><?= te('Alter · Graduierung') ?></th><th class="rechts"><?= te('Starter') ?></th><th class="rechts"><?= te('mind.') ?></th><th><?= te('Findet statt') ?></th><th><?= te('Status') ?></th><th><?= te('Tatami') ?></th></tr></thead>
            <tbody>
            <?php foreach ($liste as $k): $n = (int) $k['anzahl']; ?>
                <tr class="<?= $faelltAus($k) ? 'faellt-aus' : ($n === 0 ? 'leer' : '') ?>">
                    <td><a href="<?= e(url("turnier/$tid/klasse/{$k['id']}")) ?>"><strong><?= e($k['code']) ?></strong></a></td>
                    <td><?= e($k['bezeichnung']) ?></td>
                    <td><?= e(geschlecht_text($k['geschlecht'])) ?></td>
                    <td class="klein"><?= e($bereich($k)) ?></td>
                    <td class="rechts zahl"><strong><?= $n ?: '–' ?></strong></td>
                    <td class="rechts zahl"><?= KlassenService::mindestens($k) ?></td>
                    <td><?= $k['status'] === 'zusammengelegt' ? '→ ' . e($k['ziel_code']) : ($n === 0 ? '<span class="leise">' . te('leer') . '</span>' : ($faelltAus($k) ? '<span class="tag fehler">' . te('fällt aus') . '</span>' : '<span class="tag ok">' . te('ja') . '</span>')) ?></td>
                    <td><?= $k['status'] !== 'offen' ? '<span class="tag ' . ($statusTag[$k['status']] ?? '') . '">' . e(status_text($k['status'])) . '</span>' : '' ?></td>
                    <td><?= e($tatamis[(int) $k['id']] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endforeach; ?>
<?php else: ?>
<?php foreach ($gruppen as $disziplin => $liste): ?>
    <h2><?= e(disziplin_text($disziplin)) ?></h2>
    <div class="raster">
        <?php foreach ($liste as $k):
            $n = (int) $k['anzahl'];
            $klasseCss = $k['status'] === 'zusammengelegt' ? 'zusammengelegt' : ($faelltAus($k) ? 'fehler' : ($n >= 2 && $n <= 3 ? 'warn' : ($n === 0 ? 'leer-klasse' : '')));
            ?>
            <a class="karte klassenkarte <?= $klasseCss ?>" href="<?= e(url("turnier/$tid/klasse/{$k['id']}")) ?>" style="text-decoration:none;color:inherit">
                <div class="kopfzeile">
                    <span class="code"><?= e($k['code']) ?></span>
                    <?php if ($k['status'] !== 'offen'): ?><span class="tag <?= $statusTag[$k['status']] ?? '' ?>"><?= e(status_text($k['status'])) ?></span><?php endif; ?>
                    <?php if ($faelltAus($k)): ?><span class="tag fehler"><?= te('fällt aus') ?></span><?php endif; ?>
                    <span class="anzahl"><?= $k['status'] === 'zusammengelegt' ? '→ ' . e($k['ziel_code']) : ($n ?: '–') ?></span>
                </div>
                <div><strong><?= e($k['bezeichnung']) ?></strong></div>
                <div class="klein">
                    <?= e(geschlecht_text($k['geschlecht'])) ?> ·
                    <?= e($alterKurz($k)) ?> ·
                    <?= e(Graduierung::bereichText($k['grad_min'] === null ? null : (int) $k['grad_min'], $k['grad_max'] === null ? null : (int) $k['grad_max'])) ?>
                </div>
                <div class="klein"><?= e($modusText[$k['modus']] ?? $k['modus']) ?> · <?= te('mind. {n} Starter', ['n' => KlassenService::mindestens($k)]) ?> · <?= e(wertung_text($k['wertung'])) ?><?= $k['wertung_finale'] && $k['wertung_finale'] !== $k['wertung'] ? ' / ' . te('Finale') . ' ' . e(wertung_text($k['wertung_finale'])) : '' ?></div>
                <?php if (isset($vorschlaege[(int) $k['id']])): ?>
                    <div class="klein" style="margin-top:.3rem">💡 <?= te('zusammenlegen mit {codes}', ['codes' => implode(', ', array_column($vorschlaege[(int) $k['id']], 'code'))]) ?></div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php endif; ?>
