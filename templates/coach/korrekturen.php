<?php
/**
 * Turnierleitung: Korrekturanträge der Dojos aus dem Coaching-Portal bearbeiten.
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $antraege
 * @var array{aktiv: bool, bereiche: array<string, bool>} $portal
 */
use Wettkampf\Meldung\CoachService;

$this->layout('layout', ['titel' => 'Korrekturanträge', 'turnier' => $turnier, 'aktiv' => 'anmeldungen']);
$tid = (int) $turnier['id'];
$offen = count(array_filter($antraege, static fn ($a) => $a['status'] === 'offen'));
?>
<h1>Korrekturanträge <?= $offen ? '<span class="zaehler">' . $offen . '</span>' : '' ?></h1>
<p class="leise">Anträge der Dojos aus dem Coaching-Portal. Änderungen am Teilnehmer bitte im Teilnehmer selbst vornehmen, dann den Antrag annehmen; die Antwort sieht das Dojo im Portal.
    <?= $portal['aktiv'] && $portal['bereiche']['antraege'] ? '' : '<br><strong>Hinweis:</strong> Neue Anträge sind zurzeit nicht möglich (Coaching-Portal bzw. Bereich unter Einstellungen ausgeschaltet).' ?></p>

<?php if (!$antraege): ?>
    <p class="karte leise">Keine Anträge.</p>
<?php endif; ?>
<?php foreach ($antraege as $a): ?>
    <form method="post" action="<?= e(url("turnier/$tid/korrektur/{$a['id']}")) ?>" class="karte formular antrag-leitung">
        <?= csrf_feld() ?>
        <div><span class="tag <?= ['angenommen' => 'ok', 'abgelehnt' => 'warn'][$a['status']] ?? '' ?>"><?= e(CoachService::ANTRAG_STATUS[$a['status']] ?? $a['status']) ?></span>
            <strong><?= e($a['verein']) ?></strong> · <?= e(CoachService::ANTRAG_ARTEN[$a['art']] ?? $a['art']) ?>
            <?php if ($a['teilnahme']): ?> · <a href="<?= e(url("turnier/$tid/teilnehmer/{$a['teilnahme']}")) ?>"><?= e(trim($a['vorname'] . ' ' . $a['nachname'])) ?></a><?php endif; ?>
            <span class="klein leise"> · <?= e($a['erstellt_von']) ?>, <?= e(datum_de(substr((string) $a['erstellt_am'], 0, 10))) ?> <?= e(substr((string) $a['erstellt_am'], 11, 5)) ?></span></div>
        <div>Richtig wäre: <strong><?= e($a['neu']) ?></strong></div>
        <div class="klein">Begründung: <?= e($a['begruendung']) ?></div>
        <div class="zeile">
            <label>Antwort an das Dojo <input name="antwort" maxlength="500" value="<?= e((string) $a['antwort']) ?>"></label>
            <label>Status
                <select name="status"><?php foreach (CoachService::ANTRAG_STATUS as $s => $text): ?><option value="<?= e($s) ?>"<?= sel($s, $a['status']) ?>><?= e($text) ?></option><?php endforeach; ?></select></label>
            <button class="knopf" type="submit">Speichern</button>
        </div>
    </form>
<?php endforeach; ?>
