<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $klassen Lesemodelle (KlassenAnsicht)
 * @var string|null $aushang 'a4' | 'a3' – Aushang statt Statusseite (groß, Feld für die Tatami)
 */
use Wettkampf\Durchfuehrung\PhasenErzeugung;

$aushang ??= null;
foreach ($klassen as $k):
    $klasse = $k['klasse'];
    // K.-o.-Bäume (SDM-Layout) und Listen mit Punktwertung (Kata) quer (A4 landscape)
    $quer = (bool) array_filter($k['phasen'], static fn ($p) => ($p['art'] === 'ko' && $p['begegnungen']) || $p['art'] === 'auftritte');
    $tatamis = array_unique(array_filter(array_column($k['phasen'], 'tatami')));
    ?>
    <div class="blatt einseitig<?= $quer || $aushang ? ' quer' : '' ?><?= $aushang ? ' aushang aushang-' . $aushang : '' ?>">
        <?php if ($aushang): ?>
            <div class="aushang-kopf">
                <div><div class="aushang-turnier"><?= e($turnier['name']) ?></div><div class="aushang-code"><?= e($klasse['code']) ?></div><div class="aushang-name"><?= e($klasse['bezeichnung']) ?></div></div>
                <div class="tatami-feld gross"><?= te('Tatami') ?><span class="feld-linie"></span><?php if ($tatamis): ?><span class="plan"><?= te('laut Plan: {plan}', ['plan' => implode(', ', $tatamis)]) ?></span><?php endif; ?></div>
            </div>
        <?php else: ?>
        <?= $this->teil('druck/kopf', ['turnier' => $turnier, 'ueberschrift' => $klasse['code'] . ' · ' . $klasse['bezeichnung'], 'zusatz' => implode(', ', $tatamis)]) ?>
        <?php endif; ?>
        <p class="klein"<?= $aushang ? ' hidden' : '' ?>>
            <?= e(disziplin_text($klasse['disziplin'])) ?> · <?= te('Wertung:') ?> <?= e(wertung_text($klasse['wertung'])) ?><?= $klasse['wertung_finale'] ? ' / ' . te('Finale') . ' ' . e(wertung_text($klasse['wertung_finale'])) : '' ?>
            <?= $klasse['erlaubte_kata'] ? ' · ' . te('Kata Vorrunde:') . ' ' . e($klasse['erlaubte_kata']) : '' ?>
            <?= $k['auslosung'] ? ' · ' . te('Auslosung {datum} {zeit}, Seed {seed}', ['datum' => datum_de(substr($k['auslosung']['erstellt_am'], 0, 10)), 'zeit' => substr($k['auslosung']['erstellt_am'], 11, 5), 'seed' => $k['auslosung']['seed']]) : '' ?>
        </p>
        <?php foreach ($k['phasen'] as $phase): ?>
            <div class="<?= $phase['art'] === 'ko' ? '' : 'keinumbruch' ?>">
                <h2><?= e(PhasenErzeugung::bezeichnungText($phase['bezeichnung'])) ?><?= $phase['tatami'] ? ' · ' . e($phase['tatami']) : '' ?></h2>
                <?= $this->teil('druck/phase', ['phase' => $phase, 'klasse' => $klasse, 'stand' => date('Y-m-d H:i:s'), 'revision' => (int) $turnier['revision'], 'handschrift' => (bool) $aushang]) ?>
            </div>
        <?php endforeach; ?>
        <?php if ($k['platzierungen']): ?>
            <div class="keinumbruch">
                <h2><?= te('Platzierungen') ?><?= $klasse['status'] === 'bestaetigt' ? '' : ' ' . te('(vorläufig)') ?></h2>
                <table class="podest" style="width:60%">
                    <?php foreach ($k['platzierungen'] as $p): ?>
                        <tr><td class="platz"><?= $p['platz'] ?>.</td><td><?= e($p['name']) ?></td><td><?= e($p['verein']) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if (!$klassen): ?><div class="blatt"><p><?= te('Keine ausgelosten Klassen.') ?></p></div><?php endif; ?>
