<?php
/**
 * Coaching-Portal: die eigenen Starter mit nächstem Kampf/Vortrag, Tatami und Ausruf.
 * @var array<string, mixed> $portal
 * @var string $bereich
 * @var string $verein
 * @var list<array<string, mixed>> $starter
 */
use Wettkampf\Domain\TurnierPhase;

$this->layout('layout', ['titel' => 'Meine Starter · ' . $portal['name'], 'aktiv' => 'coach']);
$tid = (int) $portal['id'];
$gestartet = TurnierPhase::gestartet($portal);
$stand = static function (array $s) use ($gestartet): string {
    $art = $s['team'] ? 'Kämpfe' : 'Kämpfe/Vorträge';
    return match ($s['stand']) {
        'laeuft' => '<strong class="coach-jetzt">ist jetzt dran</strong>',
        'tatami' => $s['vorher'] === null ? 'auf der Tatami eingeplant' : ($s['vorher'] === 0 ? '<strong class="coach-jetzt">als Nächstes</strong>' : 'noch ' . (int) $s['vorher'] . ' ' . $art . ' davor'),
        'warteschlange' => 'Gruppe wartet auf eine freie Tatami',
        'folgerunde' => 'wartet auf die Kämpfe davor',
        'fertig' => 'fertig – <strong>Platz ' . (int) $s['platz'] . '</strong>',
        'ausgeschieden' => 'ausgeschieden',
        'nicht_ausgelost' => 'Klasse noch nicht ausgelost',
        default => $gestartet ? 'wartet' : 'Turnier noch nicht gestartet',
    };
};
$ausruf = static fn (array $s) => match ($s['ausruf']) {
    'gerufen' => '<span class="tag warn">📣 bitte zur ' . e((string) $s['tatami']) . '</span>',
    'da' => '<span class="tag ok">✓ da</span>',
    'fehlt' => '<span class="tag warn">als fehlend gemeldet</span>',
    default => '',
};
?>
<?= $this->teil('coach/kopf', compact('portal', 'bereich', 'verein')) ?>

<section class="karte" id="coach-live" data-revision-url="<?= e(url("coach/turnier/$tid/revision")) ?>">
    <h2>Meine Starter <span class="zaehler"><?= count($starter) ?></span></h2>
    <p class="hilfe">Aktualisiert sich automatisch. „Davor“ zählt die Kämpfe bzw. Vorträge, die auf derselben Tatami vorher drankommen.</p>
    <?php if (!$starter): ?>
        <p class="leise">Für dieses Turnier sind noch keine Starter des Dojos einer Klasse zugeordnet.</p>
    <?php else: ?>
        <div class="tabelle-scroll">
        <table class="tabelle">
            <thead><tr><th>Starter</th><th>Klasse</th><th>Runde</th><th>Tatami</th><th>Stand</th><th>Gegner</th><th>Ausruf</th></tr></thead>
            <tbody>
            <?php foreach ($starter as $s): ?>
                <tr class="<?= $s['stand'] === 'laeuft' || ($s['stand'] === 'tatami' && $s['vorher'] === 0) ? 'coach-bald' : '' ?>">
                    <td><strong><?= e($s['name']) ?></strong><?= $s['team'] ? ' <span class="tag">Team</span>' : '' ?><?= $s['seite'] === 'aka' ? ' <span class="aka-text">AKA</span>' : ($s['seite'] === 'shiro' ? ' <span class="klein">SHIRO</span>' : '') ?></td>
                    <td><?= e($s['code']) ?> <span class="klein"><?= e($s['klasse']) ?></span></td>
                    <td class="klein"><?= e((string) $s['runde']) ?></td>
                    <td class="nowrap"><?= e((string) ($s['tatami'] ?? '–')) ?></td>
                    <td><?= $stand($s) ?></td>
                    <td class="klein"><?= e((string) ($s['gegner'] ?? '')) ?></td>
                    <td><?= $ausruf($s) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
<script src="<?= e(asset('coach.js')) ?>" defer></script>
