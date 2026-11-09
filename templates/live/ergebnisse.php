<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $klassen
 */
$this->layout('live/layout', ['turnier' => $turnier, 'aktiv' => 'ergebnisse']);
$tid = $turnier['id'];

// Vereinswertung aus bestätigten Platzierungen (3/2/1 Punkte, Rang mit Medaillen als Gleichstandsregel)
$wertung = \Wettkampf\Domain\Wertung\Vereinswertung::berechnen(array_merge([], ...array_column($klassen, 'platzierungen')));
?>
<h1>Ergebnisse</h1>
<p class="leise">Nur von der Wettkampfleitung bestätigte Ergebnisse.</p>
<div class="zwei-spalten">
    <div>
        <?php foreach ($klassen as $k): ?>
            <div class="karte">
                <h3><a href="<?= e(url("live/$tid/klasse/{$k['id']}")) ?>"><?= e($k['code']) ?></a> · <?= e($k['bezeichnung']) ?></h3>
                <table class="tabelle podest">
                    <?php foreach ($k['platzierungen'] as $p): ?>
                        <tr><td class="platz"><?= $p['platz'] ?>.</td><td><?= e($p['name']) ?></td><td class="leise"><?= e($p['verein']) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endforeach; ?>
        <?php if (!$klassen): ?><div class="karte leer">Noch keine bestätigten Ergebnisse.</div><?php endif; ?>
    </div>
    <?php if ($wertung): ?>
        <div class="karte" style="align-self:start">
            <h2>Vereinswertung</h2>
            <p class="klein">3 Punkte für Platz 1, 2 für Platz 2, 1 für Platz 3; bei Punktgleichheit entscheiden die Gold-, dann die Silbermedaillen.</p>
            <table class="tabelle">
                <thead><tr><th>Rang</th><th>Verein</th><th class="rechts">Punkte</th><th class="rechts">1.</th><th class="rechts">2.</th><th class="rechts">3.</th></tr></thead>
                <tbody>
                <?php foreach ($wertung as $w): ?>
                    <tr><td><?= $w['rang'] ?></td><td><?= e($w['verein']) ?></td><td class="rechts"><strong><?= $w['punkte'] ?></strong></td><td class="rechts"><?= $w['gold'] ?></td><td class="rechts"><?= $w['silber'] ?></td><td class="rechts"><?= $w['bronze'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
