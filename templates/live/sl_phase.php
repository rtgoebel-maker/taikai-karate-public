<?php
/**
 * Eine Phase im Aushang: Wettkampfbaum (SDM-Layout), „jeder gegen jeden“
 * als Tabelle Shiro/Aka bzw. Starterliste/Final-Starterliste bei Punkterunden.
 * @var array<string, mixed> $phase
 * @var array<string, mixed> $gruppe
 * @var bool $mehrerePhasen
 * @var string $stand
 * @var int $revision
 * @var bool|null $druck
 */
$druck ??= false;
$istFinale = in_array($phase['typ'], ['finalrunde', 'punkte_finale'], true);
$standZeile = 'Stand ' . datum_de(substr($stand, 0, 10)) . ' ' . substr($stand, 11, 5) . ' · Revision ' . (int) $revision;
$zielText = $phase['typ'] === 'pool' ? (($gruppe['finale_art'] ?? 'ko') === 'punkte' ? 'Punktefinale' : 'Finalrunde') : null;
?>
<?php if ($mehrerePhasen || $istFinale): ?>
    <h3 class="sl-phase"><?= e($phase['bezeichnung']) ?><?= $phase['art'] === 'jgj' ? ' · Jeder gegen jeden' : '' ?></h3>
<?php endif; ?>

<?php if ($phase['status'] === 'wartet'): ?>
    <p class="leise"><?= $phase['typ'] === 'punkte_finale' || ($gruppe['finale_art'] ?? 'ko') === 'punkte'
        ? 'Die Final-Starterliste erscheint, sobald die Qualifizierten feststehen.'
        : 'Der Baum der Finalrunde erscheint, sobald die Qualifizierten feststehen.' ?></p>

<?php elseif ($phase['art'] === 'auftritte'): ?>
    <h4 class="sl-unter"><?= $istFinale ? 'Final-Starterliste' : 'Starterliste' ?></h4>
    <table class="tabelle sl-liste">
        <thead><tr><th class="sl-nr">Nr.</th><th>Starter / Team</th><th>Dojo</th><th class="rechts">Wertung</th></tr></thead>
        <tbody>
        <?php foreach ($phase['auftritte'] as $a): ?>
            <tr>
                <td class="sl-nr zahl"><?= (int) $a['reihenfolge'] ?></td>
                <td><strong><?= e($a['name']) ?></strong><?= $a['kata'] ? '<br><span class="klein">' . e($a['kata']) . '</span>' : '' ?></td>
                <td><?= e($a['verein']) ?></td>
                <td class="rechts zahl"><?= $a['status'] === 'beendet' ? e(number_format((float) $a['gesamt'], 1, ',', '')) : '–' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="klein"><?= e($gruppe['code']) ?> · <?= $istFinale ? 'Final-Starterliste' : 'Starterliste' ?> · <?= e($standZeile) ?></p>

<?php elseif ($phase['art'] === 'jgj'): ?>
    <table class="sb-jgj">
        <thead><tr><th style="width:4rem">Nr.</th><th>Shiro</th><th>Aka</th></tr></thead>
        <tbody>
        <?php foreach ($phase['begegnungen'] as $b):
            $sieger = $b['sieger_start_id'] === null ? null : ((int) $b['sieger_start_id'] === (int) $b['aka_start_id'] ? 'aka' : 'shiro');
            ?>
            <tr>
                <td><?= e($b['nr']) ?></td>
                <?php foreach (['shiro', 'aka'] as $s): ?>
                    <td><?= $sieger === $s ? '★ ' : '' ?><?= e($b["{$s}_name"]) ?><div class="sb-dojo"><?= e($b["{$s}_verein"]) ?></div></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (array_filter($phase['tabelle'] ?? [], static fn ($z) => $z['kaempfe'] > 0)): ?>
        <table class="tabelle sl-liste" style="margin-top:.8rem">
            <thead><tr><th class="sl-nr">Platz</th><th>Starter / Team</th><th>Dojo</th><th class="rechts">Siege</th><th class="rechts">Ippon</th><th class="rechts">Wazaari</th></tr></thead>
            <tbody>
            <?php foreach ($phase['tabelle'] as $z): ?>
                <tr><td class="sl-nr zahl"><?= $z['platz'] ?></td><td><strong><?= e($z['name']) ?></strong></td><td><?= e($z['verein']) ?></td>
                    <td class="rechts zahl"><?= $z['siege'] ?></td><td class="rechts zahl"><?= $z['ippon'] ?></td><td class="rechts zahl"><?= $z['wazaari'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p class="klein"><?= e($gruppe['code']) ?><?= $mehrerePhasen ? ' · ' . e($phase['bezeichnung']) : '' ?> · <?= e($standZeile) ?></p>

<?php elseif ($phase['begegnungen']): ?>
    <h4 class="sl-unter">Wettkampfbaum <?= e($gruppe['code']) ?></h4>
    <?= $this->teil('teile/sdm_baum', [
        'phase' => $phase, 'code' => $gruppe['code'] . ($mehrerePhasen ? ' · ' . $phase['bezeichnung'] : ''),
        'stand' => $stand, 'revision' => $revision, 'zielText' => $zielText, 'druck' => $druck,
    ]) ?>
<?php else: ?>
    <p class="leise">Keine Kämpfe – alle Starter kommen direkt weiter.</p>
<?php endif; ?>
