<?php
/**
 * Turnierleitung: Korrekturanträge der Dojos aus dem Coaching-Portal bearbeiten.
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $antraege
 * @var array{aktiv: bool, bereiche: array<string, bool>} $portal
 */
use Wettkampf\Kern\Sprache;
use Wettkampf\Meldung\CoachService;

$this->layout('layout', ['titel' => t('Korrekturanträge'), 'turnier' => $turnier, 'aktiv' => 'anmeldungen']);
$tid = (int) $turnier['id'];
$offen = count(array_filter($antraege, static fn ($a) => $a['status'] === 'offen'));
?>
<h1><?= te('Korrekturanträge') ?> <?= $offen ? '<span class="zaehler">' . $offen . '</span>' : '' ?></h1>
<p class="leise"><?= te('Anträge der Dojos aus dem Coaching-Portal. Änderungen am Teilnehmer bitte im Teilnehmer selbst vornehmen, dann den Antrag annehmen; die Antwort sieht das Dojo im Portal.') ?>
    <?= $portal['aktiv'] && $portal['bereiche']['antraege'] ? '' : '<br>' . th('<strong>Hinweis:</strong> Neue Anträge sind zurzeit nicht möglich (Coaching-Portal bzw. Bereich unter Einstellungen ausgeschaltet).') ?></p>

<?php if (!$antraege): ?>
    <p class="karte leise"><?= te('Keine Anträge.') ?></p>
<?php endif; ?>
<?php foreach ($antraege as $a): ?>
    <form method="post" action="<?= e(url("turnier/$tid/korrektur/{$a['id']}")) ?>" class="karte formular antrag-leitung">
        <?= csrf_feld() ?>
        <div><span class="tag <?= ['angenommen' => 'ok', 'abgelehnt' => 'warn'][$a['status']] ?? '' ?>"><?= e(Sprache::t(CoachService::ANTRAG_STATUS[$a['status']] ?? $a['status'])) ?></span>
            <strong><?= e($a['verein']) ?></strong> · <?= e(Sprache::t(CoachService::ANTRAG_ARTEN[$a['art']] ?? $a['art'])) ?>
            <?php if ($a['teilnahme']): ?> · <a href="<?= e(url("turnier/$tid/teilnehmer/{$a['teilnahme']}")) ?>"><?= e(trim($a['vorname'] . ' ' . $a['nachname'])) ?></a><?php endif; ?>
            <span class="klein leise"> · <?= e($a['erstellt_von']) ?>, <?= e(datum_de(substr((string) $a['erstellt_am'], 0, 10))) ?> <?= e(substr((string) $a['erstellt_am'], 11, 5)) ?></span></div>
        <div><?= th('Richtig wäre: <strong>{neu}</strong>', ['neu' => $a['neu']]) ?></div>
        <div class="klein"><?= te('Begründung: {text}', ['text' => $a['begruendung']]) ?></div>
        <div class="zeile">
            <label><?= te('Antwort an das Dojo') ?> <input name="antwort" maxlength="500" value="<?= e((string) $a['antwort']) ?>"></label>
            <label><?= te('Status') ?>
                <select name="status"><?php foreach (CoachService::ANTRAG_STATUS as $s => $text): ?><option value="<?= e($s) ?>"<?= sel($s, $a['status']) ?>><?= e(Sprache::t($text)) ?></option><?php endforeach; ?></select></label>
            <button class="knopf" type="submit"><?= te('Speichern') ?></button>
        </div>
    </form>
<?php endforeach; ?>
