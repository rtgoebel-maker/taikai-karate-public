<?php
/**
 * Darstellung einer Phase: K.-o.-Baum, „jeder gegen jeden“ oder Auftritte.
 * @var array<string, mixed> $phase
 * @var string|null $eingabeUrl  Basis-URL für die Ergebniseingabe (nur Leitung), z. B. „/turnier/1/begegnung/“
 */
$eingabeUrl ??= null;
$seite = static function (array $b, string $farbe) {
    $name = $b["{$farbe}_name"] !== '' ? $b["{$farbe}_name"] : $b["{$farbe}_platzhalter"];
    $sieger = $b['sieger_start_id'] !== null && (int) $b['sieger_start_id'] === (int) $b["{$farbe}_start_id"] && $b['status'] !== 'freilos';
    $w = $b['wertung_daten'];
    $zusatz = match ($b['system']) {
        'flaggen' => isset($w['flaggen']) ? (string) $w['flaggen'][$farbe] : '',
        'punkte' => isset($w[$farbe]['gesamt']) ? number_format((float) $w[$farbe]['gesamt'], 1, ',', '') : '',
        'kumite' => isset($w[$farbe]) ? trim(str_repeat('I', (int) ($w[$farbe]['ippon'] ?? 0)) . ' ' . str_repeat('W', (int) ($w[$farbe]['wazaari'] ?? 0))) : '',
        default => '',
    };
    return '<div class="seite ' . $farbe . ($sieger ? ' sieger' : '') . '">'
        . '<span>' . e($name !== '' ? $name : '–') . '</span>'
        . ($zusatz !== '' ? ' <strong class="zahl">' . e($zusatz) . '</strong>' : '')
        . '<span class="verein">' . e($b["{$farbe}_verein"]) . '</span></div>';
};
?>
<?php if ($phase['art'] === 'ko'): ?>
    <?php if (!$phase['begegnungen']): ?>
        <p class="leise">Keine Begegnungen – alle Starter kommen direkt weiter.</p>
    <?php endif; ?>
    <div class="baum">
        <?php foreach ($phase['runden'] as $runde => $liste): ?>
            <div class="baum-runde">
                <h4><?= e($liste[count($liste) - 1]['runden_name']) ?></h4>
                <?php foreach ($liste as $b): ?>
                    <div class="paarung <?= $b['status'] === 'freilos' ? 'freilos' : '' ?> <?= $b['status'] === 'laeuft' ? 'laeuft' : '' ?>">
                        <div class="nr">
                            <span><?= $b['nr'] ? 'Kampf ' . $b['nr'] : 'Freilos' ?><?= $b['art'] === 'platz3' ? ' · Platz 3' : '' ?></span>
                            <?php if ($eingabeUrl && $b['nr'] && in_array($b['status'], ['bereit', 'laeuft', 'beendet'], true)): ?>
                                <a href="<?= e(url($eingabeUrl . $b['id'])) ?>"><?= $b['status'] === 'beendet' ? 'Korrigieren' : 'Ergebnis' ?></a>
                            <?php endif; ?>
                        </div>
                        <?= $seite($b, 'aka') ?>
                        <?= $seite($b, 'shiro') ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($phase['art'] === 'jgj'): ?>
    <div class="zwei-spalten">
        <table class="tabelle">
            <thead><tr><th>Platz</th><th>Name</th><th>Verein</th><th class="rechts">Kämpfe</th><th class="rechts">Siege</th><th class="rechts">Ippon</th><th class="rechts">Wazaari</th></tr></thead>
            <tbody>
            <?php foreach ($phase['tabelle'] ?? [] as $z): ?>
                <tr>
                    <td class="zahl"><?= $z['platz'] ?><?= $z['gleichstand'] ? ' <span class="tag warn" title="Gleichstand – Stichentscheid nötig">=</span>' : '' ?></td>
                    <td><?= e($z['name']) ?></td>
                    <td><?= e($z['verein']) ?></td>
                    <td class="rechts zahl"><?= $z['kaempfe'] ?></td>
                    <td class="rechts zahl"><strong><?= $z['siege'] ?></strong></td>
                    <td class="rechts zahl"><?= $z['ippon'] ?></td>
                    <td class="rechts zahl"><?= $z['wazaari'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div>
            <?php foreach ($phase['begegnungen'] as $b): ?>
                <div class="paarung" style="margin-bottom:.5rem">
                    <div class="nr">
                        <span>Kampf <?= $b['nr'] ?></span>
                        <?php if ($eingabeUrl && in_array($b['status'], ['bereit', 'laeuft', 'beendet'], true)): ?>
                            <a href="<?= e(url($eingabeUrl . $b['id'])) ?>"><?= $b['status'] === 'beendet' ? 'Korrigieren' : 'Ergebnis' ?></a>
                        <?php endif; ?>
                    </div>
                    <?= $seite($b, 'aka') ?>
                    <?= $seite($b, 'shiro') ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$phase['begegnungen']): ?><p class="leise">Keine Begegnungen (kampflos).</p><?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <div class="zwei-spalten">
        <table class="tabelle">
            <thead><tr><th>#</th><th>Name</th><th>Verein</th><th>Kata</th><th class="rechts">Punkte</th></tr></thead>
            <tbody>
            <?php foreach ($phase['auftritte'] as $a): ?>
                <tr>
                    <td class="zahl"><?= $a['reihenfolge'] ?></td>
                    <td><?= e($a['name']) ?></td>
                    <td><?= e($a['verein']) ?></td>
                    <td><?= e($a['kata']) ?></td>
                    <td class="rechts zahl">
                        <?= $a['status'] === 'beendet' ? '<strong>' . e(number_format((float) $a['gesamt'], 1, ',', '')) . '</strong>' : '' ?>
                        <?php if ($eingabeUrl): ?>
                            <a class="knopf klein" href="<?= e(url(str_replace('begegnung/', 'auftritt/', $eingabeUrl) . $a['id'])) ?>"><?= $a['status'] === 'beendet' ? 'Korr.' : 'Wertung' ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($phase['rangliste'] ?? []): ?>
            <table class="tabelle">
                <thead><tr><th>Rang</th><th>Name</th><th class="rechts">Punkte</th><th class="rechts" title="Gleichstand: zuerst die niedrigste Wertung dazu">+ niedrigste</th><th class="rechts" title="dann auch die höchste Wertung (alle fünf)">+ höchste</th></tr></thead>
                <tbody>
                <?php foreach ($phase['rangliste'] as $z): ?>
                    <tr<?= $phase['typ'] === 'punkte_vorrunde' && $phase['aufsteiger'] && $z['platz'] <= (int) $phase['aufsteiger'] ? ' style="background:var(--erfolg-hell)"' : '' ?>>
                        <td class="zahl"><?= $z['platz'] ?><?= $z['gleichstand'] ? ' <span class="tag warn" title="Gleichstand">=</span>' : '' ?></td>
                        <td><?= e($z['name']) ?><?= ($z['verein'] ?? '') !== '' ? ' <span class="klein">' . e($z['verein']) . '</span>' : '' ?></td>
                        <td class="rechts zahl"><strong><?= e(number_format($z['gesamt'], 1, ',', '')) ?></strong></td>
                        <td class="rechts zahl"><?= e(number_format($z['mit_niedrigster'], 1, ',', '')) ?></td>
                        <td class="rechts zahl"><?= e(number_format($z['nebenwertung'], 1, ',', '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>
