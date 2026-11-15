<?php
/**
 * @var array<string, mixed> $turnier
 * @var array<string, mixed> $klasse
 * @var list<array<string, mixed>> $auslosungen
 */
use Wettkampf\Durchfuehrung\PhasenErzeugung;

$this->layout('layout', ['titel' => t('Auslosungsprotokoll {code}', ['code' => $klasse['code']]), 'turnier' => $turnier, 'aktiv' => 'klassen']);
$tid = $turnier['id'];
?>
<p class="obertitel"><?= te('Auslosungsprotokoll') ?></p>
<h1><a href="<?= e(url("turnier/$tid/klasse/{$klasse['id']}")) ?>"><?= e($klasse['code']) ?></a> · <?= e($klasse['bezeichnung']) ?></h1>
<p class="leise"><?= te('Jede Auslosung ist über Seed und Algorithmus reproduzierbar: Dieselbe Starterliste mit demselben Seed ergibt exakt denselben Baum.') ?></p>
<?php foreach ($auslosungen as $a):
    $p = json_decode((string) $a['protokoll'], true) ?: [];
    $o = json_decode((string) $a['optionen'], true) ?: [];
    $namen = $p['starter'] ?? [];
    ?>
    <div class="karte">
        <div class="titelzeile">
            <h2 style="margin:0;margin-right:auto"><?= te('{datum} {zeit} Uhr', ['datum' => datum_de(substr($a['erstellt_am'], 0, 10)), 'zeit' => substr($a['erstellt_am'], 11, 5)]) ?></h2>
            <?= $a['aktiv'] ? '<span class="tag ok">' . te('aktiv') . '</span>' : '<span class="tag">' . te('ersetzt') . '</span>' ?>
        </div>
        <table class="tabelle">
            <tr><th><?= te('Seed') ?></th><td><code><?= e($a['seed']) ?></code></td></tr>
            <tr><th><?= te('Algorithmus') ?></th><td><?= e($a['algorithmus']) ?></td></tr>
            <tr><th><?= te('Ausgelost von') ?></th><td><?= e($a['erstellt_von']) ?></td></tr>
            <tr><th><?= te('Optionen') ?></th><td><?= e(($o['modus'] ?? '') . ' · ' . t('Dojo-Trennung') . ' ' . (($o['dojo_trennung'] ?? false) ? t('ja') : t('nein'))) ?></td></tr>
        </table>
        <?php foreach ($p['phasen'] ?? [] as $phase): ?>
            <h3><?= e(PhasenErzeugung::bezeichnungText((string) $phase['phase'])) ?></h3>
            <ol class="klein">
                <?php foreach ($phase['positionen'] ?? $phase['reihenfolge'] ?? [] as $startId): ?>
                    <li><?= $startId === null ? '<em>' . te('Freilos') . '</em>' : e($namen[$startId] ?? t('Start {id}', ['id' => $startId])) ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php if (!$auslosungen): ?><div class="karte leer"><?= te('Noch keine Auslosung.') ?></div><?php endif; ?>
