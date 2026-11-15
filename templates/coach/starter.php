<?php
/**
 * Coaching-Portal: die eigenen Starter mit nächstem Kampf/Vortrag, Tatami und Ausruf.
 * @var array<string, mixed> $portal
 * @var string $bereich
 * @var string $verein
 * @var list<array<string, mixed>> $starter
 */
use Wettkampf\Domain\TurnierPhase;

$this->layout('layout', ['titel' => t('Meine Starter') . ' · ' . $portal['name'], 'aktiv' => 'coach']);
$tid = (int) $portal['id'];
$gestartet = TurnierPhase::gestartet($portal);
$stand = static function (array $s) use ($gestartet): string {
    $vorher = ['n' => (int) $s['vorher']];
    return match ($s['stand']) {
        'laeuft' => '<strong class="coach-jetzt">' . te('ist jetzt dran') . '</strong>',
        'tatami' => $s['vorher'] === null ? te('auf der Tatami eingeplant') : ($s['vorher'] === 0 ? '<strong class="coach-jetzt">' . te('als Nächstes') . '</strong>' : ($s['team'] ? te('noch {n} Kämpfe davor', $vorher) : te('noch {n} Kämpfe/Vorträge davor', $vorher))),
        'warteschlange' => te('Gruppe wartet auf eine freie Tatami'),
        'folgerunde' => te('wartet auf die Kämpfe davor'),
        'fertig' => th('fertig – <strong>Platz {platz}</strong>', ['platz' => (int) $s['platz']]),
        'ausgeschieden' => te('ausgeschieden'),
        'nicht_ausgelost' => te('Klasse noch nicht ausgelost'),
        default => $gestartet ? te('wartet') : te('Turnier noch nicht gestartet'),
    };
};
$ausruf = static fn (array $s) => match ($s['ausruf']) {
    'gerufen' => '<span class="tag warn">📣 ' . te('bitte zur {tatami}', ['tatami' => (string) $s['tatami']]) . '</span>',
    'da' => '<span class="tag ok">✓ ' . te('da') . '</span>',
    'fehlt' => '<span class="tag warn">' . te('als fehlend gemeldet') . '</span>',
    default => '',
};
?>
<?= $this->teil('coach/kopf', compact('portal', 'bereich', 'verein')) ?>

<section class="karte" id="coach-live" data-revision-url="<?= e(url("coach/turnier/$tid/revision")) ?>">
    <h2><?= te('Meine Starter') ?> <span class="zaehler"><?= count($starter) ?></span></h2>
    <p class="hilfe"><?= te('Aktualisiert sich automatisch. „Davor“ zählt die Kämpfe bzw. Vorträge, die auf derselben Tatami vorher drankommen.') ?></p>
    <?php if (!$starter): ?>
        <p class="leise"><?= te('Für dieses Turnier sind noch keine Starter des Dojos einer Klasse zugeordnet.') ?></p>
    <?php else: ?>
        <div class="tabelle-scroll">
        <table class="tabelle">
            <thead><tr><th><?= te('Starter') ?></th><th><?= te('Klasse') ?></th><th><?= te('Runde') ?></th><th><?= te('Tatami') ?></th><th><?= te('Stand') ?></th><th><?= te('Gegner') ?></th><th><?= te('Ausruf') ?></th></tr></thead>
            <tbody>
            <?php foreach ($starter as $s): ?>
                <tr class="<?= $s['stand'] === 'laeuft' || ($s['stand'] === 'tatami' && $s['vorher'] === 0) ? 'coach-bald' : '' ?>">
                    <td><strong><?= e($s['name']) ?></strong><?= $s['team'] ? ' <span class="tag">' . te('Team') . '</span>' : '' ?><?= $s['seite'] === 'aka' ? ' <span class="aka-text">AKA</span>' : ($s['seite'] === 'shiro' ? ' <span class="klein">SHIRO</span>' : '') ?></td>
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
