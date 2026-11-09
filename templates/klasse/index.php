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

$this->layout('layout', ['titel' => 'Klassen', 'turnier' => $turnier, 'aktiv' => 'klassen']);
$tid = $turnier['id'];
$modusText = ['auto' => 'K.-o.', 'pools' => 'Pools + Finalrunde', 'punkte' => 'Punkte-Vorrunde', 'jgj' => 'Jeder gegen jeden'];
$statusTag = ['offen' => '', 'ausgelost' => 'info', 'laeuft' => 'warn', 'beendet' => 'ok', 'bestaetigt' => 'ok', 'zusammengelegt' => ''];
$gruppen = [];
foreach ($klassen as $k) {
    $gruppen[$k['disziplin']][] = $k;
}
$offeneMitStartern = count(array_filter($klassen, static fn ($k) => $k['status'] === 'offen' && KlassenService::findetStatt($k, (int) $k['anzahl'])));
$faelltAus = static fn (array $k) => $k['status'] !== 'zusammengelegt' && (int) $k['anzahl'] > 0 && !KlassenService::findetStatt($k, (int) $k['anzahl']);
$bereich = static fn (array $k) => ($k['alter_min'] === null && $k['alter_max'] === null ? 'alle Alter' : ($k['alter_max'] === null ? "ab {$k['alter_min']} J." : ($k['alter_min'] === null ? "bis {$k['alter_max']} J." : "{$k['alter_min']}–{$k['alter_max']} J.")))
    . ' · ' . Graduierung::bereichText($k['grad_min'] === null ? null : (int) $k['grad_min'], $k['grad_max'] === null ? null : (int) $k['grad_max']);
?>
<div class="titelzeile">
    <h1>Klassen <span class="leise">(<?= count($klassen) === $alle ? $alle : count($klassen) . ' von ' . $alle ?>)</span> <?= info('klassen') ?></h1>
    <form method="post" action="<?= e(url("turnier/$tid/klassen/zuordnen")) ?>" class="inline">
        <?= csrf_feld() ?><button class="knopf" type="submit" title="Alle Meldungen erneut den Klassen zuordnen">Zuordnung aktualisieren</button>
    </form>
    <?php if ($offeneMitStartern > 0): ?>
        <form method="post" action="<?= e(url("turnier/$tid/auslosung/alle")) ?>" class="inline" data-bestaetigen="Alle offenen Klassen auslosen, die ihre Mindestanzahl an Startern erreichen?">
            <?= csrf_feld() ?><button class="knopf" type="submit">Alle offenen auslosen (<?= $offeneMitStartern ?>)</button>
        </form>
    <?php endif; ?>
    <a class="knopf primaer" href="<?= e(url("turnier/$tid/klasse/neu")) ?>">Neue Klasse</a>
</div>

<div class="karte knopfleiste">
    <form method="get" class="knopfleiste">
        <select name="disziplin" data-auto-absenden style="max-width:220px" aria-label="Disziplin">
            <option value="">Alle Disziplinen</option>
            <?php foreach (['kata', 'kumite', 'kata_team', 'kumite_team'] as $d): ?>
                <option value="<?= $d ?>"<?= sel($d, $filter) ?>><?= e(disziplin_text($d)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="besetzung" data-auto-absenden style="max-width:260px" aria-label="Besetzung">
            <?php foreach (['' => 'Alle Klassen', 'besetzt' => 'Mit Teilnehmern', 'statt' => 'Findet statt (Mindestanzahl erreicht)', 'faellt_aus' => 'Fällt aus (zu wenige Starter)', 'leer' => 'Ohne Teilnehmer'] as $wert => $text): ?>
                <option value="<?= $wert ?>"<?= sel($wert, $besetzung) ?>><?= e($text) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="ansicht" value="<?= e($ansicht) ?>">
        <span class="umschalter">
            <?php foreach (['karten' => 'Karten', 'tabelle' => 'Tabelle'] as $wert => $text): ?>
                <a class="knopf klein<?= $ansicht === $wert ? ' aktiv' : '' ?>" href="<?= e(url("turnier/$tid/klassen", array_filter(['disziplin' => $filter, 'besetzung' => $besetzung, 'ansicht' => $wert === 'karten' ? '' : $wert]))) ?>"><?= $text ?></a>
            <?php endforeach; ?>
        </span>
    </form>
    <form method="post" action="<?= e(url("turnier/$tid/klassen/vorlage")) ?>" class="knopfleiste" style="margin-left:auto">
        <?= csrf_feld() ?>
        <select name="vorlage" style="max-width:420px">
            <?php foreach ($vorlagen as $schluessel => $text): ?><option value="<?= e($schluessel) ?>"><?= e($text) ?></option><?php endforeach; ?>
        </select>
        <button class="knopf" type="submit">Vorlage laden</button>
    </form>
</div>

<?php if (!$klassen): ?>
    <div class="karte leer"><p>Noch keine Klassen. Am schnellsten: eine Vorlage laden und anschließend anpassen.</p></div>
<?php endif; ?>

<?php if ($klassen === [] && $alle > 0): ?>
    <div class="karte leer"><p>Keine Klasse passt zum Filter.</p></div>
<?php endif; ?>

<?php if ($ansicht === 'tabelle'): ?>
    <?php foreach ($gruppen as $disziplin => $liste): ?>
        <h2><?= e(disziplin_text($disziplin)) ?></h2>
        <div class="tabelle-scroll">
        <table class="tabelle klassentabelle">
            <thead><tr><th>Code</th><th>Klasse</th><th>Geschlecht</th><th>Alter · Graduierung</th><th class="rechts">Starter</th><th class="rechts">mind.</th><th>Findet statt</th><th>Status</th><th>Tatami</th></tr></thead>
            <tbody>
            <?php foreach ($liste as $k): $n = (int) $k['anzahl']; ?>
                <tr class="<?= $faelltAus($k) ? 'faellt-aus' : ($n === 0 ? 'leer' : '') ?>">
                    <td><a href="<?= e(url("turnier/$tid/klasse/{$k['id']}")) ?>"><strong><?= e($k['code']) ?></strong></a></td>
                    <td><?= e($k['bezeichnung']) ?></td>
                    <td><?= e(geschlecht_text($k['geschlecht'])) ?></td>
                    <td class="klein"><?= e($bereich($k)) ?></td>
                    <td class="rechts zahl"><strong><?= $n ?: '–' ?></strong></td>
                    <td class="rechts zahl"><?= KlassenService::mindestens($k) ?></td>
                    <td><?= $k['status'] === 'zusammengelegt' ? '→ ' . e($k['ziel_code']) : ($n === 0 ? '<span class="leise">leer</span>' : ($faelltAus($k) ? '<span class="tag fehler">fällt aus</span>' : '<span class="tag ok">ja</span>')) ?></td>
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
                    <?php if ($faelltAus($k)): ?><span class="tag fehler">fällt aus</span><?php endif; ?>
                    <span class="anzahl"><?= $k['status'] === 'zusammengelegt' ? '→ ' . e($k['ziel_code']) : ($n ?: '–') ?></span>
                </div>
                <div><strong><?= e($k['bezeichnung']) ?></strong></div>
                <div class="klein">
                    <?= e(geschlecht_text($k['geschlecht'])) ?> ·
                    <?= e($k['alter_min'] === null && $k['alter_max'] === null ? 'alle Alter' : ($k['alter_max'] === null ? "ab {$k['alter_min']} J." : ($k['alter_min'] === null ? "bis {$k['alter_max']} J." : "{$k['alter_min']}–{$k['alter_max']} J."))) ?> ·
                    <?= e(Graduierung::bereichText($k['grad_min'] === null ? null : (int) $k['grad_min'], $k['grad_max'] === null ? null : (int) $k['grad_max'])) ?>
                </div>
                <div class="klein"><?= e($modusText[$k['modus']] ?? $k['modus']) ?> · mind. <?= KlassenService::mindestens($k) ?> Starter · <?= e(wertung_text($k['wertung'])) ?><?= $k['wertung_finale'] && $k['wertung_finale'] !== $k['wertung'] ? ' / Finale ' . e(wertung_text($k['wertung_finale'])) : '' ?></div>
                <?php if (isset($vorschlaege[(int) $k['id']])): ?>
                    <div class="klein" style="margin-top:.3rem">💡 zusammenlegen mit <?= e(implode(', ', array_column($vorschlaege[(int) $k['id']], 'code'))) ?></div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php endif; ?>
