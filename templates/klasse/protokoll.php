<?php
/**
 * @var array<string, mixed> $turnier
 * @var array<string, mixed> $klasse
 * @var list<array<string, mixed>> $auslosungen
 */
$this->layout('layout', ['titel' => 'Auslosungsprotokoll ' . $klasse['code'], 'turnier' => $turnier, 'aktiv' => 'klassen']);
$tid = $turnier['id'];
?>
<p class="obertitel">Auslosungsprotokoll</p>
<h1><a href="<?= e(url("turnier/$tid/klasse/{$klasse['id']}")) ?>"><?= e($klasse['code']) ?></a> · <?= e($klasse['bezeichnung']) ?></h1>
<p class="leise">Jede Auslosung ist über Seed und Algorithmus reproduzierbar: Dieselbe Starterliste mit demselben Seed ergibt exakt denselben Baum.</p>
<?php foreach ($auslosungen as $a):
    $p = json_decode((string) $a['protokoll'], true) ?: [];
    $o = json_decode((string) $a['optionen'], true) ?: [];
    $namen = $p['starter'] ?? [];
    ?>
    <div class="karte">
        <div class="titelzeile">
            <h2 style="margin:0;margin-right:auto"><?= e(datum_de(substr($a['erstellt_am'], 0, 10))) ?> <?= e(substr($a['erstellt_am'], 11, 5)) ?> Uhr</h2>
            <?= $a['aktiv'] ? '<span class="tag ok">aktiv</span>' : '<span class="tag">ersetzt</span>' ?>
        </div>
        <table class="tabelle">
            <tr><th>Seed</th><td><code><?= e($a['seed']) ?></code></td></tr>
            <tr><th>Algorithmus</th><td><?= e($a['algorithmus']) ?></td></tr>
            <tr><th>Ausgelost von</th><td><?= e($a['erstellt_von']) ?></td></tr>
            <tr><th>Optionen</th><td><?= e(($o['modus'] ?? '') . ' · Dojo-Trennung ' . (($o['dojo_trennung'] ?? false) ? 'ja' : 'nein')) ?></td></tr>
        </table>
        <?php foreach ($p['phasen'] ?? [] as $phase): ?>
            <h3><?= e($phase['phase']) ?></h3>
            <ol class="klein">
                <?php foreach ($phase['positionen'] ?? $phase['reihenfolge'] ?? [] as $startId): ?>
                    <li><?= $startId === null ? '<em>Freilos</em>' : e($namen[$startId] ?? "Start $startId") ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php if (!$auslosungen): ?><div class="karte leer">Noch keine Auslosung.</div><?php endif; ?>
