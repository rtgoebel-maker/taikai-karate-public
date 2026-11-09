<?php
/**
 * Coaching-Portal: Korrekturanträge des Dojos stellen und den Stand sehen.
 * @var array<string, mixed> $portal
 * @var string $bereich
 * @var string $verein
 * @var list<array<string, mixed>> $antraege
 * @var list<array{id: int, name: string}> $teilnehmer
 */
use Wettkampf\Meldung\CoachService;

$this->layout('layout', ['titel' => 'Korrekturanträge · ' . $portal['name'], 'aktiv' => 'coach']);
$tid = (int) $portal['id'];
$statusTag = ['offen' => '', 'angenommen' => 'ok', 'abgelehnt' => 'warn'];
?>
<?= $this->teil('coach/kopf', compact('portal', 'bereich', 'verein')) ?>

<div class="zwei-spalten">
    <form method="post" action="<?= e(url("coach/turnier/$tid/antraege")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2>Neuer Antrag</h2>
        <p class="hilfe">Falsche Angaben zu einem Teilnehmer? Die Turnierleitung prüft den Antrag und antwortet hier.</p>
        <label>Teilnehmer
            <select name="teilnahme_id">
                <option value="0">– kein bestimmter (Sonstiges) –</option>
                <?php foreach ($teilnehmer as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
            </select></label>
        <label>Was ist zu korrigieren?
            <select name="art" required>
                <?php foreach (CoachService::ANTRAG_ARTEN as $art => $text): ?><option value="<?= e($art) ?>"><?= e($text) ?></option><?php endforeach; ?>
            </select></label>
        <label>Richtig wäre <input name="neu" maxlength="200" required placeholder="z. B. 4. Kyu, Geburtsdatum 12.03.2014"></label>
        <label>Begründung <textarea name="begruendung" maxlength="500" rows="3" required></textarea></label>
        <div><button class="knopf primaer" type="submit">Antrag stellen</button></div>
    </form>

    <section class="karte">
        <h2>Unsere Anträge</h2>
        <?php if (!$antraege): ?>
            <p class="leise">Noch keine Anträge.</p>
        <?php else: ?>
            <?php foreach ($antraege as $a): ?>
                <div class="antrag">
                    <div><span class="tag <?= $statusTag[$a['status']] ?? '' ?>"><?= e(CoachService::ANTRAG_STATUS[$a['status']] ?? $a['status']) ?></span>
                        <strong><?= e(CoachService::ANTRAG_ARTEN[$a['art']] ?? $a['art']) ?></strong><?= $a['teilnahme'] ? ' · ' . e(trim($a['vorname'] . ' ' . $a['nachname'])) : '' ?>
                        <span class="klein leise"><?= e(datum_de(substr((string) $a['erstellt_am'], 0, 10))) ?></span></div>
                    <div>→ <?= e($a['neu']) ?></div>
                    <div class="klein"><?= e($a['begruendung']) ?></div>
                    <?php if ($a['antwort']): ?><div class="antrag-antwort">Turnierleitung: <?= e($a['antwort']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
