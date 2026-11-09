<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $klassen
 */
$this->layout('layout', ['titel' => 'Ergebnisse', 'turnier' => $turnier, 'aktiv' => 'ergebnisse']);
$tid = $turnier['id'];
$statusText = ['ausgelost' => 'ausgelost', 'laeuft' => 'läuft', 'beendet' => 'beendet – zu bestätigen', 'bestaetigt' => 'bestätigt'];
$statusTag = ['ausgelost' => '', 'laeuft' => 'warn', 'beendet' => 'info', 'bestaetigt' => 'ok'];
?>
<div class="titelzeile">
    <h1>Ergebnisse <?= info('ergebnisse') ?></h1>
    <a class="knopf" href="<?= e(url("turnier/$tid/druck/siegerliste")) ?>" target="_blank">Siegerliste drucken</a>
</div>
<p class="leise">Platzierungen werden nach dem letzten Kampf einer Klasse automatisch berechnet und erscheinen erst nach Bestätigung in Siegerliste und Statusseite.</p>
<table class="tabelle">
    <thead><tr><th>Klasse</th><th>Fortschritt</th><th>Status</th><th>1.</th><th>2.</th><th>3.</th><th>4.</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($klassen as $k):
        $platz = array_column($k['platzierungen'], null, 'platz');
        ?>
        <tr>
            <td><a href="<?= e(url("turnier/$tid/klasse/{$k['id']}")) ?>"><strong><?= e($k['code']) ?></strong></a><br><span class="klein"><?= e($k['bezeichnung']) ?></span></td>
            <td class="zahl nowrap"><?= (int) $k['beendet'] ?> / <?= (int) $k['kaempfe'] ?></td>
            <td><span class="tag <?= $statusTag[$k['status']] ?? '' ?>"><?= e($statusText[$k['status']] ?? $k['status']) ?></span></td>
            <?php for ($p = 1; $p <= 4; $p++): ?>
                <td><?= isset($platz[$p]) ? e($platz[$p]['name']) . '<br><span class="klein">' . e($platz[$p]['verein']) . '</span>' : '' ?></td>
            <?php endfor; ?>
            <td>
                <?php if ($k['status'] === 'beendet'): ?>
                    <form method="post" action="<?= e(url("turnier/$tid/klasse/{$k['id']}/bestaetigen")) ?>"><?= csrf_feld() ?><button class="knopf klein primaer" type="submit">Bestätigen</button></form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$klassen): ?><tr><td colspan="8" class="leise">Noch keine ausgelosten Klassen.</td></tr><?php endif; ?>
    </tbody>
</table>
