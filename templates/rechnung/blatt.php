<?php
/**
 * Ein Rechnungsblatt (A4) – für die Rechnungen je Dojo (Leitung) und die Rechnung einer bezahlten Online-Anmeldung.
 * @var array<string, mixed> $turnier
 * @var array{absender: string, bank: string, steuerhinweis: string, zahlungsziel: int, praefix: string, datum: string, text: string, starterliste?: bool} $einstellungen
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

if (!empty($einstellungen['quittung'])) {
    include __DIR__ . '/quittung.php'; // gemeinnütziger Ausrichter: Quittung statt Rechnung
    return;
}
$euro = static fn (int $cent) => AnmeldungService::euro($cent);
$e = $einstellungen;
$absenderZeile = implode(' · ', array_filter(array_map('trim', explode("\n", $e['absender']))));
?>
<div class="blatt rechnung">
    <div class="r-kopf">
        <div class="r-absender"><?= nl2br(e($e['absender'] !== '' ? $e['absender'] : t('Absender bitte unter Rechnungen eintragen'))) ?></div>
    </div>
    <div class="r-adressfeld">
        <div class="r-ruecksende"><?= e($absenderZeile) ?></div>
        <div><strong><?= e($empfaenger['verein']) ?></strong></div>
        <?php if ($empfaenger['ansprechpartner'] !== ''): ?><div><?= te('z. Hd. {name}', ['name' => $empfaenger['ansprechpartner']]) ?></div><?php endif; ?>
        <?php if ($empfaenger['email'] !== ''): ?><div class="klein"><?= e($empfaenger['email']) ?></div><?php endif; ?>
    </div>
    <table class="r-daten">
        <tr><th><?= te('Rechnungs-Nr.') ?></th><td><?= e($nummer) ?></td></tr>
        <tr><th><?= te('Rechnungsdatum') ?></th><td><?= e(datum_de($datum)) ?></td></tr>
        <tr><th><?= te('Leistungsdatum') ?></th><td><?= e(datum_de($turnier['datum'])) ?></td></tr>
    </table>

    <h1 class="r-titel"><?= te('Rechnung') ?></h1>
    <p><?= $turnier['ort']
        ? th('Startgeld für <strong>{name}</strong> am {datum} in {ort}.', ['name' => $turnier['name'], 'datum' => datum_de($turnier['datum']), 'ort' => $turnier['ort']])
        : th('Startgeld für <strong>{name}</strong> am {datum}.', ['name' => $turnier['name'], 'datum' => datum_de($turnier['datum'])]) ?></p>

    <table>
        <thead><tr><th><?= te('Posten') ?></th><th class="rechts"><?= te('Anzahl') ?></th><th class="rechts"><?= te('Einzelpreis') ?></th><th class="rechts"><?= te('Betrag') ?></th></tr></thead>
        <tbody>
        <?php foreach ($preis['posten'] as $p): ?>
            <tr><td><?= e($p['text']) ?></td><td class="rechts zahl"><?= $p['anzahl'] ?></td><td class="rechts zahl"><?= e($euro($p['einzel'])) ?></td><td class="rechts zahl"><?= e($euro($p['summe'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$preis['posten']): ?><tr><td colspan="4"><?= te('Keine Starts.') ?></td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><th colspan="3"><?= te('Rechnungsbetrag') ?></th><th class="rechts zahl"><?= e($euro($preis['summe'])) ?></th></tr></tfoot>
    </table>
    <?php if ($e['steuerhinweis'] !== ''): ?><p class="klein"><?= e($e['steuerhinweis']) ?></p><?php endif; ?>

    <?php if ($bezahlt !== null): ?>
        <p class="r-bezahlt"><strong><?= $bezahlt['am']
            ? te('Betrag {betrag} dankend erhalten am {datum}.', ['betrag' => $euro($bezahlt['cent']), 'datum' => datum_de($bezahlt['am'])])
            : te('Betrag {betrag} dankend erhalten.', ['betrag' => $euro($bezahlt['cent'])]) ?></strong>
            <?php if ($bezahlt['cent'] > $preis['summe']): ?><?= te('Das Guthaben von {betrag} wird erstattet bzw. verrechnet.', ['betrag' => $euro($bezahlt['cent'] - $preis['summe'])]) ?><?php endif; ?></p>
    <?php elseif ($preis['summe'] > 0): ?>
        <p><?= $faellig !== null
            ? th('Bitte überweisen Sie den Betrag bis zum <strong>{datum}</strong> unter Angabe der Rechnungsnummer <strong>{nummer}</strong>.', ['datum' => datum_de($faellig), 'nummer' => $nummer])
            : th('Bitte überweisen Sie den Betrag unter Angabe der Rechnungsnummer <strong>{nummer}</strong>.', ['nummer' => $nummer]) ?></p>
        <?php if ($e['bank'] !== ''): ?><p class="r-bank"><?= nl2br(e($e['bank'])) ?></p><?php endif; ?>
    <?php endif; ?>
    <?php if ($e['text'] !== ''): ?><p><?= nl2br(e($e['text'])) ?></p><?php endif; ?>

    <?php if (($teilnehmer || $teams) && !empty($e['starterliste'])): /* nur auf Wunsch; im Buchhaltungsarchiv nie (Datensparsamkeit) */ ?>
    <h2><?= te('Anlage: gemeldete Starter') ?></h2>
    <table class="r-starter">
        <thead><tr><th><?= te('Name') ?></th><th>Kata</th><th>Kumite</th></tr></thead>
        <tbody>
        <?php foreach ($teilnehmer as $t): ?>
            <tr><td><?= e($t['name']) ?><?= !$t['kata'] && $t['kumite'] === '' ? ' <span class="klein">' . te('(nur im Team)') . '</span>' : '' ?></td><td><?= $t['kata'] ? '✓' : '' ?></td><td><?= $t['kumite'] !== '' ? '✓' : '' ?></td></tr>
        <?php endforeach; ?>
        <?php foreach ($teams as $t): ?>
            <tr><td><?= e($t['name']) ?> <span class="klein"><?= te('(Team)') ?></span></td><td colspan="2"><?= e(disziplin_text($t['disziplin'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
