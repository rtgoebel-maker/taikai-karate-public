<?php
/**
 * Druckdarstellung einer Phase (Baum, Tabelle oder Vortragsliste).
 * @var array<string, mixed> $phase
 * @var array<string, mixed> $klasse
 * @var string|null $stand
 * @var int|null $revision
 * @var bool|null $handschrift  Felder zum Fortschreiben von Hand (Notfall, Aushang)
 * @var int|null $leerZeilen    wartendes Punktefinale: so viele leere Zeilen zum Ausfüllen
 */
$handschrift ??= false;
$leerZeilen ??= 0;
$kr = (int) $klasse['kampfrichter'];
?>
<?php if ($phase['status'] === 'wartet' && $handschrift && $leerZeilen > 0): ?>
    <p class="klein">Finalisten stehen nach der Vorrunde fest – bei Technikausfall hier von Hand eintragen.</p>
    <table class="kata-tabelle">
        <thead><tr><th>#</th><th>Name</th><th>Verein</th><th class="kata-spalte">Kata</th><?php for ($i = 1; $i <= $kr; $i++): ?><th>KR <?= $i ?></th><?php endfor; ?><th>Summe</th><th>Platz</th></tr></thead>
        <tbody>
        <?php for ($z = 1; $z <= $leerZeilen; $z++): ?>
            <tr class="leerzeile"><td><?= $z ?></td><td></td><td></td><td></td><?= str_repeat('<td></td>', $kr) ?><td></td><td></td></tr>
        <?php endfor; ?>
        </tbody>
    </table>
<?php elseif ($phase['status'] === 'wartet'): ?>
    <p class="leise">Finalrunde – Teilnehmer stehen nach der Vorrunde fest.</p>
<?php elseif ($phase['art'] === 'ko'): ?>
    <?= $this->teil('teile/sdm_baum', [
        'phase' => $phase, 'code' => $klasse['code'] . ' · ' . $phase['bezeichnung'],
        'stand' => $stand ?? date('Y-m-d H:i:s'), 'revision' => $revision ?? 0,
        'zielText' => $phase['typ'] === 'pool' ? ($klasse['finale_art'] === 'punkte' ? 'Punktefinale' : 'Finalrunde') : null,
        'druck' => true,
    ]) ?>
<?php elseif ($phase['art'] === 'jgj'): ?>
    <table class="sb-jgj">
        <thead><tr><th style="width:12mm">Nr.</th><th>Shiro</th><th>Aka</th><?= $handschrift ? '<th style="width:35mm">Sieger</th>' : '' ?></tr></thead>
        <tbody>
        <?php foreach ($phase['begegnungen'] as $b):
            $sieger = $b['sieger_start_id'] === null ? null : ((int) $b['sieger_start_id'] === (int) $b['aka_start_id'] ? 'aka' : 'shiro');
            ?>
            <tr>
                <td><?= e($b['nr']) ?></td>
                <?php foreach (['shiro', 'aka'] as $s): ?>
                    <td><?= $sieger === $s ? '★ ' : '' ?><?= e($b["{$s}_name"]) ?><div class="sb-dojo"><?= e($b["{$s}_verein"]) ?></div></td>
                <?php endforeach; ?>
                <?php if ($handschrift): ?><td></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (array_filter($phase['tabelle'] ?? [], static fn ($z) => $z['kaempfe'] > 0)): ?>
        <h3>Tabelle</h3>
        <table>
            <thead><tr><th>Platz</th><th>Name</th><th>Verein</th><th>Kämpfe</th><th>Siege</th><th>Ippon</th><th>Wazaari</th></tr></thead>
            <tbody>
            <?php foreach ($phase['tabelle'] as $z): ?>
                <tr><td><?= $z['platz'] ?></td><td><?= e($z['name']) ?></td><td><?= e($z['verein']) ?></td>
                    <td class="zahl"><?= $z['kaempfe'] ?></td><td class="zahl"><?= $z['siege'] ?></td><td class="zahl"><?= $z['ippon'] ?></td><td class="zahl"><?= $z['wazaari'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php else: ?>
    <table class="kata-tabelle">
        <thead><tr><th>#</th><th>Name</th><th>Verein</th><th class="kata-spalte">Kata</th><?php for ($i = 1; $i <= $kr; $i++): ?><th>KR <?= $i ?></th><?php endfor; ?><th>Summe</th></tr></thead>
        <tbody>
        <?php foreach ($phase['auftritte'] as $a):
            $werte = $a['wertung_daten']['werte'] ?? [];
            $gestrichen = $a['wertung_daten']['gestrichen'] ?? [];
            ?>
            <tr>
                <td><?= $a['reihenfolge'] ?></td><td><?= e($a['name']) ?></td><td class="klein"><?= e($a['verein']) ?></td><td><?= e($a['kata']) ?></td>
                <?php for ($i = 0; $i < $kr; $i++): ?>
                    <td class="zahl"><?= isset($werte[$i]) ? (in_array($i, $gestrichen, true) ? '(' . e(str_replace('.', ',', (string) $werte[$i])) . ')' : e(str_replace('.', ',', (string) $werte[$i]))) : '' ?></td>
                <?php endfor; ?>
                <td class="zahl"><strong><?= $a['status'] === 'beendet' ? e(number_format((float) $a['gesamt'], 1, ',', '')) : '' ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
