<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $tatamis
 * @var array<string, mixed>|null $zahlen null = Turnier noch nicht gestartet
 */
$this->layout('live/layout', ['turnier' => $turnier, 'aktiv' => 'tatamis']);
if ($zahlen === null) {
    return; // vor dem Start: nur der Hinweis aus dem Layout
}
$tid = $turnier['id'];
// im Einsatz: Kampf läuft, Gruppe hat begonnen oder ihr Ausruf ist abgeschlossen (wie „Jetzt tritt an“)
$laufend = count(array_filter($tatamis, static fn ($t) => $t['laufend'] !== null
    && ($t['laufend']['status'] === 'laeuft' || !empty($t['laufend']['gruppe_begonnen']) || !empty($t['laufend']['aufruf']['fertig']))));
?>
<div class="live-kopf">
    <div><strong><?= $laufend ?></strong> Tatamis im Einsatz</div>
    <div><strong><?= (int) $zahlen['beendet'] ?> / <?= (int) $zahlen['gesamt'] ?></strong> Kämpfe und Vorträge beendet</div>
</div>
<div class="tatami-reihe-rahmen">
    <div class="tatami-reihe" style="--tatamis: <?= max(1, count($tatamis)) ?>">
        <?php foreach ($tatamis as $t): ?>
            <div class="karte tatami-karte"><?= $this->teil('live/tatami_anzeige', ['turnier' => $turnier, 't' => $t]) ?></div>
        <?php endforeach; ?>
    </div>
</div>
