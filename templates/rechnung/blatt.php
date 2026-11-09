<?php
/**
 * Ein Rechnungsblatt (A4) – für die Rechnungen je Dojo (Leitung) und die Rechnung einer bezahlten Online-Anmeldung.
 * @var array<string, mixed> $turnier
 * @var array{absender: string, bank: string, steuerhinweis: string, zahlungsziel: int, praefix: string, datum: string, text: string} $einstellungen
 * @var array{verein: string, ansprechpartner: string, email: string} $empfaenger
 * @var string $nummer
 * @var string $datum Rechnungsdatum (Y-m-d)
 * @var string|null $faellig Zahlungsziel (Y-m-d) bzw. null, wenn schon bezahlt
 * @var array{cent: int, am: string|null}|null $bezahlt
 * @var array{posten: list<array{text: string, anzahl: int, einzel: int, summe: int}>, summe: int} $preis
 * @var list<array{name: string, kata: bool, kumite: string}> $teilnehmer
 * @var list<array{name: string, disziplin: string}> $teams
 */
use Wettkampf\Meldung\AnmeldungService;

$euro = static fn (int $cent) => AnmeldungService::euro($cent);
$e = $einstellungen;
$absenderZeile = implode(' · ', array_filter(array_map('trim', explode("\n", $e['absender']))));
?>
<div class="blatt rechnung">
    <div class="r-kopf">
        <div class="r-absender"><?= nl2br(e($e['absender'] !== '' ? $e['absender'] : 'Absender bitte unter Rechnungen eintragen')) ?></div>
    </div>
    <div class="r-adressfeld">
        <div class="r-ruecksende"><?= e($absenderZeile) ?></div>
        <div><strong><?= e($empfaenger['verein']) ?></strong></div>
        <?php if ($empfaenger['ansprechpartner'] !== ''): ?><div>z. Hd. <?= e($empfaenger['ansprechpartner']) ?></div><?php endif; ?>
        <?php if ($empfaenger['email'] !== ''): ?><div class="klein"><?= e($empfaenger['email']) ?></div><?php endif; ?>
    </div>
    <table class="r-daten">
        <tr><th>Rechnungs-Nr.</th><td><?= e($nummer) ?></td></tr>
        <tr><th>Rechnungsdatum</th><td><?= e(datum_de($datum)) ?></td></tr>
        <tr><th>Leistungsdatum</th><td><?= e(datum_de($turnier['datum'])) ?></td></tr>
    </table>

    <h1 class="r-titel">Rechnung</h1>
    <p>Startgeld für <strong><?= e($turnier['name']) ?></strong> am <?= e(datum_de($turnier['datum'])) ?><?= $turnier['ort'] ? ' in ' . e($turnier['ort']) : '' ?>.</p>

    <table>
        <thead><tr><th>Posten</th><th class="rechts">Anzahl</th><th class="rechts">Einzelpreis</th><th class="rechts">Betrag</th></tr></thead>
        <tbody>
        <?php foreach ($preis['posten'] as $p): ?>
            <tr><td><?= e($p['text']) ?></td><td class="rechts zahl"><?= $p['anzahl'] ?></td><td class="rechts zahl"><?= e($euro($p['einzel'])) ?></td><td class="rechts zahl"><?= e($euro($p['summe'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$preis['posten']): ?><tr><td colspan="4">Keine Starts.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><th colspan="3">Rechnungsbetrag</th><th class="rechts zahl"><?= e($euro($preis['summe'])) ?></th></tr></tfoot>
    </table>
    <?php if ($e['steuerhinweis'] !== ''): ?><p class="klein"><?= e($e['steuerhinweis']) ?></p><?php endif; ?>

    <?php if ($bezahlt !== null): ?>
        <p class="r-bezahlt"><strong>Betrag <?= e($euro($bezahlt['cent'])) ?> dankend erhalten<?= $bezahlt['am'] ? ' am ' . e(datum_de($bezahlt['am'])) : '' ?>.</strong>
            <?php if ($bezahlt['cent'] > $preis['summe']): ?>Das Guthaben von <?= e($euro($bezahlt['cent'] - $preis['summe'])) ?> wird erstattet bzw. verrechnet.<?php endif; ?></p>
    <?php elseif ($preis['summe'] > 0): ?>
        <p>Bitte überweisen Sie den Betrag<?= $faellig !== null ? ' bis zum <strong>' . e(datum_de($faellig)) . '</strong>' : '' ?> unter Angabe der Rechnungsnummer
            <strong><?= e($nummer) ?></strong>.</p>
        <?php if ($e['bank'] !== ''): ?><p class="r-bank"><?= nl2br(e($e['bank'])) ?></p><?php endif; ?>
    <?php endif; ?>
    <?php if ($e['text'] !== ''): ?><p><?= nl2br(e($e['text'])) ?></p><?php endif; ?>

    <h2>Gemeldete Starter</h2>
    <table class="r-starter">
        <thead><tr><th>Name</th><th>Kata</th><th>Kumite</th></tr></thead>
        <tbody>
        <?php foreach ($teilnehmer as $t): ?>
            <tr><td><?= e($t['name']) ?><?= !$t['kata'] && $t['kumite'] === '' ? ' <span class="klein">(nur im Team)</span>' : '' ?></td><td><?= $t['kata'] ? '✓' : '' ?></td><td><?= $t['kumite'] !== '' ? '✓' : '' ?></td></tr>
        <?php endforeach; ?>
        <?php foreach ($teams as $t): ?>
            <tr><td><?= e($t['name']) ?> <span class="klein">(Team)</span></td><td colspan="2"><?= e(disziplin_text($t['disziplin'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
