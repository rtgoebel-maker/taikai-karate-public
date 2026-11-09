<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $klassen
 */
$ergebnisText = static function (array $b): string {
    $w = $b['wertung_daten'];
    return match (true) {
        in_array($b['entscheidung'], ['kiken', 'hansoku', 'shikkaku'], true) => ucfirst((string) $b['entscheidung']) . ' ' . (($w['gegen'] ?? '') === 'aka' ? 'Aka' : 'Shiro'),
        $b['system'] === 'flaggen' && isset($w['flaggen']) => $w['flaggen']['aka'] . ' : ' . $w['flaggen']['shiro'] . ' Flaggen' . (!empty($w['flaggen']['unentschieden']) ? ' (' . $w['flaggen']['unentschieden'] . ' unentsch.)' : ''),
        $b['system'] === 'punkte' && isset($w['aka']['gesamt']) => number_format((float) $w['aka']['gesamt'], 1, ',', '') . ' : ' . number_format((float) $w['shiro']['gesamt'], 1, ',', ''),
        isset($w['summe']) => $w['summe']['aka']['siege'] . ' : ' . $w['summe']['shiro']['siege'] . ' Einzelsiege',
        isset($w['aka']['ippon']) => sprintf('I%d W%d : I%d W%d', $w['aka']['ippon'], $w['aka']['wazaari'], $w['shiro']['ippon'], $w['shiro']['wazaari'])
            . ($b['entscheidung'] === 'hantei' ? ' (Hantei)' : ''),
        default => (string) $b['entscheidung'],
    };
};
?>
<div class="blatt">
    <?= $this->teil('druck/kopf', ['turnier' => $turnier, 'ueberschrift' => 'Ergebnisliste', 'zusatz' => count($klassen) . ' Klassen']) ?>
    <?php if (!$klassen): ?><p>Noch keine Ergebnisse.</p><?php endif; ?>
    <?php foreach ($klassen as $k): $klasse = $k['klasse']; ?>
        <div class="keinumbruch">
            <h2><?= e($klasse['code']) ?> · <?= e($klasse['bezeichnung']) ?><?= $klasse['status'] === 'bestaetigt' ? '' : ' <span class="klein">(' . e(status_text($klasse['status'])) . ')</span>' ?></h2>
            <?php if ($k['platzierungen']): ?>
                <p><?php foreach ($k['platzierungen'] as $p): ?><strong><?= $p['platz'] ?>.</strong> <?= e($p['name']) ?> <span class="klein">(<?= e($p['verein_voll'] ?? $p['verein']) ?>)</span> &nbsp; <?php endforeach; ?></p>
            <?php endif; ?>
            <?php foreach ($k['phasen'] as $phase): ?>
                <?php if ($phase['begegnungen']): ?>
                    <table>
                        <thead><tr><th style="width:12mm">Kampf</th><th style="width:26mm">Runde</th><th>Aka</th><th>Shiro</th><th style="width:38mm">Ergebnis</th></tr></thead>
                        <tbody>
                        <?php foreach ($phase['begegnungen'] as $b): if ($b['status'] === 'freilos') { continue; } ?>
                            <tr>
                                <td><?= $b['nr'] ?></td>
                                <td class="klein"><?= e($phase['bezeichnung'] !== 'K.-o.-Runde' ? $phase['bezeichnung'] . ' · ' : '') ?><?= e($b['runden_name']) ?></td>
                                <td<?= (int) $b['sieger_start_id'] === (int) $b['aka_start_id'] && $b['sieger_start_id'] ? ' style="font-weight:700"' : '' ?>><?= e($b['aka_name'] ?: $b['aka_platzhalter']) ?><?= $b['kata_aka'] ? ' <span class="klein">(' . e($b['kata_aka']) . ')</span>' : '' ?></td>
                                <td<?= (int) $b['sieger_start_id'] === (int) $b['shiro_start_id'] && $b['sieger_start_id'] ? ' style="font-weight:700"' : '' ?>><?= e($b['shiro_name'] ?: $b['shiro_platzhalter']) ?><?= $b['kata_shiro'] ? ' <span class="klein">(' . e($b['kata_shiro']) . ')</span>' : '' ?></td>
                                <td class="klein"><?= $b['status'] === 'beendet' ? e($ergebnisText($b)) : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php elseif ($phase['auftritte']): ?>
                    <h3><?= e($phase['bezeichnung']) ?></h3>
                    <?= $this->teil('druck/phase', ['phase' => $phase, 'klasse' => $klasse]) ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>
