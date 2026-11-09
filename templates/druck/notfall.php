<?php
/**
 * Notfall-Satz je Klasse: Turnierbaum/Pool-Liste (Übersicht) und Kampfliste aller Kämpfe (Eintragefelder),
 * jeweils mit Feld „Tatami“ zum Eintragen von Hand. Bei Punktwertung (Kata) ist die Liste zugleich die
 * Übersicht – dann nur ein Blatt.
 * @var array<string, mixed> $turnier
 * @var list<array{k: array<string, mixed>, bloecke: list<array<string, mixed>>}> $saetze
 */
?>
<?php if (!$saetze): ?><div class="blatt"><p>Noch keine Klasse ausgelost.</p></div><?php endif; ?>
<?php foreach ($saetze as ['k' => $k, 'bloecke' => $bloecke]):
    $klasse = $k['klasse'];
    $plan = implode(', ', array_unique(array_filter(array_column($k['phasen'], 'tatami'))));
    // K.-o.-Bäume (SDM-Layout) und Listen mit Punktwertung (Kata) quer (A4 landscape)
    $quer = (bool) array_filter($k['phasen'], static fn ($p) => ($p['art'] === 'ko' && $p['begegnungen']) || $p['art'] === 'auftritte');
    $nurListe = (bool) array_filter($k['phasen'], static fn ($p) => $p['art'] === 'auftritte');
    $vorher = null;
    $platzierungen = '<div class="handschrift-platz keinumbruch"><h2>Platzierungen</h2><table>'
        . '<tr><td class="platz">1.</td><td></td></tr><tr><td class="platz">2.</td><td></td></tr>'
        . '<tr><td class="platz">3.</td><td></td></tr><tr><td class="platz">4.</td><td></td></tr></table></div>';
    ?>
    <?php if (!$nurListe): ?>
    <div class="blatt einseitig<?= $quer ? ' quer' : '' ?>">
        <?= $this->teil('druck/kopf', ['turnier' => $turnier, 'ueberschrift' => $klasse['code'] . ' · ' . $klasse['bezeichnung'], 'zusatz' => 'Notfall-Satz · Baum', 'tatamiFeld' => true, 'plan' => $plan]) ?>
        <?php foreach ($k['phasen'] as $phase):
            $leerZeilen = $vorher === 'auftritte' ? (int) $klasse['finalisten'] : 0;
            $vorher = $phase['art'];
            ?>
            <div class="<?= $phase['art'] === 'ko' ? '' : 'keinumbruch' ?>">
                <h2><?= e($phase['bezeichnung']) ?></h2>
                <?= $this->teil('druck/phase', ['phase' => $phase, 'klasse' => $klasse, 'stand' => date('Y-m-d H:i:s'), 'revision' => (int) $turnier['revision'], 'handschrift' => true, 'leerZeilen' => $leerZeilen]) ?>
            </div>
        <?php endforeach; ?>
        <?= $platzierungen ?>
    </div>
    <?php endif; ?>
    <div class="blatt<?= $nurListe ? ' quer' : '' ?>">
        <?= $this->teil('druck/kopf', ['turnier' => $turnier, 'ueberschrift' => 'Kampfliste ' . $klasse['code'], 'zusatz' => 'Notfall-Satz · ' . $klasse['bezeichnung'], 'tatamiFeld' => true, 'plan' => $plan]) ?>
        <?= $this->teil('druck/kampf_bloecke', ['bloecke' => $bloecke, 'handschrift' => true]) ?>
        <?= $nurListe ? $platzierungen : '' ?>
        <div class="unterschrift"><div>Tischbesatzung</div><div>Hauptkampfrichter</div><div>Wettkampfleitung</div></div>
    </div>
<?php endforeach; ?>
