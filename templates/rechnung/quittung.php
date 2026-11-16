<?php
/**
 * Quittung (A4) statt Rechnung – für gemeinnützige Ausrichter: Startgelder sind nach § 4 Nr. 22 Buchst. b UStG
 * umsatzsteuerfrei, eine Rechnung ist dann nicht vorgeschrieben (§ 14 Abs. 2 UStG). Die Quittung bestätigt den Empfang
 * (§ 368 BGB) und wird von Kassenwart bzw. Wettkampfleitung unterschrieben. Eingebunden aus rechnung/blatt.
 *
 * @var array<string, mixed> $turnier
 * @var array<string, mixed> $einstellungen RechnungService::einstellungen()
 * @var array{verein: string, ansprechpartner: string, email: string} $empfaenger
 * @var string $nummer
 * @var array{cent: int, am: string|null}|null $bezahlt erfasster Zahlungseingang (sonst Felder zum Ausfüllen)
 * @var array{posten: list<array{text: string, anzahl: int, einzel: int, summe: int}>, summe: int} $preis
 * @var list<array{name: string, kata: bool, kumite: string}> $teilnehmer
 * @var list<array{name: string, disziplin: string}> $teams
 */
use Wettkampf\Meldung\AnmeldungService;
use Wettkampf\Meldung\RechnungService;

$euro = static fn (int $cent) => AnmeldungService::euro($cent);
$e = $einstellungen;
$steuer = $e['steuerhinweis'] !== '' ? $e['steuerhinweis'] : t(RechnungService::STEUERHINWEIS_GEMEINNUETZIG);
$worte = sprache() === 'de' ? RechnungService::inWorten((int) $preis['summe']) : '';
?>
<div class="blatt rechnung quittung">
    <div class="r-kopf">
        <div class="r-absender"><?= nl2br(e($e['absender'] !== '' ? $e['absender'] : t('Absender bitte unter Rechnungen eintragen'))) ?></div>
    </div>

    <h1 class="r-titel"><?= te('Quittung') ?></h1>
    <table class="r-daten q-daten">
        <tr><th><?= te('Quittungs-Nr.') ?></th><td><?= e($nummer) ?></td></tr>
        <tr><th><?= te('Veranstaltung') ?></th><td><?= e($turnier['name']) ?>, <?= e(datum_de($turnier['datum'])) ?><?= $turnier['ort'] ? ', ' . e($turnier['ort']) : '' ?></td></tr>
    </table>

    <div class="q-betrag">
        <div><span class="klein"><?= te('Betrag') ?></span><strong><?= e($euro((int) $preis['summe'])) ?></strong></div>
        <?php if ($worte !== ''): ?><div class="q-worte"><?= te('in Worten: {worte}', ['worte' => $worte]) ?></div><?php endif; ?>
    </div>

    <table class="q-felder">
        <tr><th><?= te('erhalten von') ?></th><td><strong><?= e($empfaenger['verein']) ?></strong><?= $empfaenger['ansprechpartner'] !== '' ? ' · ' . e($empfaenger['ansprechpartner']) : '' ?></td></tr>
        <tr><th><?= te('für') ?></th><td><?= te('Startgelder (Teilnehmergebühren) gemäß Meldung Nr. {nummer}:', ['nummer' => $nummer]) ?></td></tr>
    </table>
    <table>
        <thead><tr><th><?= te('Posten') ?></th><th class="rechts"><?= te('Anzahl') ?></th><th class="rechts"><?= te('Einzelpreis') ?></th><th class="rechts"><?= te('Betrag') ?></th></tr></thead>
        <tbody>
        <?php foreach ($preis['posten'] as $p): ?>
            <tr><td><?= e($p['text']) ?></td><td class="rechts zahl"><?= $p['anzahl'] ?></td><td class="rechts zahl"><?= e($euro($p['einzel'])) ?></td><td class="rechts zahl"><?= e($euro($p['summe'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$preis['posten']): ?><tr><td colspan="4"><?= te('Keine Starts.') ?></td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><th colspan="3"><?= te('Summe') ?></th><th class="rechts zahl"><?= e($euro((int) $preis['summe'])) ?></th></tr></tfoot>
    </table>
    <p class="klein"><?= e($steuer) ?></p>

    <table class="q-felder q-zahlung">
        <tr><th><?= te('Betrag erhalten am') ?></th>
            <td><?= $bezahlt !== null && $bezahlt['am'] ? e(datum_de((string) $bezahlt['am'])) . ' <span class="klein">(' . te('Zahlungseingang in der Software erfasst') . ')</span>' : '<span class="q-linie"></span>' ?></td></tr>
        <?php if ($bezahlt === null || !$bezahlt['am']): ?>
            <tr><th><?= te('Zahlungsart') ?></th><td>☐ <?= te('bar') ?> &nbsp; ☐ <?= te('Überweisung') ?></td></tr>
        <?php endif; ?>
    </table>
    <?php if ($e['text'] !== ''): ?><p><?= nl2br(e($e['text'])) ?></p><?php endif; ?>

    <div class="q-unterschrift">
        <div><span class="q-linie"></span><span class="klein"><?= te('Ort, Datum') ?></span></div>
        <div><span class="q-linie"></span><span class="klein"><?= te('Betrag erhalten – Unterschrift (Kassenwart bzw. Wettkampfleitung)') ?></span></div>
    </div>
</div>
<?php if (($teilnehmer || $teams) && !empty($e['starterliste'])): /* nur auf Wunsch, als eigene Seite; im Buchhaltungsarchiv nie */ ?>
<?= $this->teil('rechnung/anlage', ['turnier' => $turnier, 'empfaenger' => $empfaenger, 'nummer' => $nummer, 'art' => t('Quittung'), 'teilnehmer' => $teilnehmer, 'teams' => $teams]) ?>
<?php endif; ?>
