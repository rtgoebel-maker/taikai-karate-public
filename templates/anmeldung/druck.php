<?php
/**
 * Druckbare Übersicht einer Anmeldung (A4) – für das Dojo nach dem Absenden
 * und für die Wettkampfleitung.
 * @var array<string, mixed> $turnier
 * @var array<string, mixed> $anmeldung
 * @var array{teilnehmer: array<string, array<string, mixed>>, teams: list<array<string, mixed>>} $angaben
 * @var array{posten: list<array<string, mixed>>, summe: int} $preis
 * @var array<string, mixed> $einstellungen
 * @var bool $bearbeitbar
 * @var string $zurueck Ziel des Knopfs „zurück“
 * @var array<string, mixed> $stand Bearbeitungsstand (AnmeldeStand)
 * @var array<string, mixed> $zahlungsangaben Absender, Bank, Zahlungsziel, nummer (Verwendungszweck)
 * @var string|null $girocode Überweisungs-QR-Code (SVG) für Banking-Apps
 * @var string|null $rechnungLink Rechnung für das Dojo (nur wenn angeboten und bezahlt)
 * @var string|null $rechnungLeitung Rechnung auf Wunsch (Leitung)
 * @var bool $fuerDojo
 */
use Wettkampf\Meldung\AnmeldungService;
use Wettkampf\Domain\Alter;
use Wettkampf\Domain\Graduierung;
use Wettkampf\Kern\Http\Session;

$tid = $turnier['id'];
$euro = static fn (int $cent) => AnmeldungService::euro($cent);
$name = static fn (?string $k) => $k !== null && isset($angaben['teilnehmer'][$k]) ? $angaben['teilnehmer'][$k]['vorname'] . ' ' . $angaben['teilnehmer'][$k]['nachname'] : '–';
$teamsVon = [];
foreach ($angaben['teams'] as $team) {
    foreach (array_merge($team['mitglieder'], array_filter([$team['ersatz']])) as $m) {
        $teamsVon[$m][] = $team['name'] . ($m === $team['ersatz'] ? ' (E)' : '');
    }
}
$z = $zahlungsangaben;
$version = (int) $anmeldung['aufforderung_version'];
$mitGeld = $stand['betrag'] > 0 || $stand['bezahlt'] > 0;
$frist = $anmeldung['aufforderung_am'] ? date('Y-m-d', (int) strtotime($anmeldung['aufforderung_am'] . ' +' . (int) $z['zahlungsziel'] . ' days')) : null;
if ($frist !== null && !empty($turnier['meldeschluss']) && $turnier['meldeschluss'] < $frist && $turnier['meldeschluss'] >= date('Y-m-d')) {
    $frist = $turnier['meldeschluss']; // spätestens zum Meldeschluss
}
$zeit = static fn (string $z): string => datum_de($z) . date(' H:i', (int) strtotime($z));
?><!doctype html>
<html lang="<?= e(sprache()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= te('Anmeldung {dojo}', ['dojo' => $anmeldung['dojo']]) ?> · <?= e($turnier['name']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('druck.css')) ?>">
    <script src="<?= e(asset('verhalten.js')) ?>" defer></script>
</head>
<body>
<div class="werkzeug">
    <strong><?= te('Anmeldung') ?> · <?= e($anmeldung['dojo']) ?></strong>
    <button type="button" data-drucken><?= te('Drucken / als PDF speichern') ?></button>
    <a href="<?= e($zurueck) ?>"><?= $bearbeitbar ? te('Anmeldung bearbeiten') : te('Zurück') ?></a>
    <?php if ($rechnungLink !== null): ?><a href="<?= e($rechnungLink) ?>"><?= te('Rechnung') ?></a><?php endif; ?>
    <?php if ($rechnungLeitung !== null && $mitGeld && $anmeldung['status'] !== 'abgelehnt'): ?><a href="<?= e($rechnungLeitung) ?>"><?= te('Rechnung erstellen') ?></a><?php endif; ?>
    <span style="margin-left:auto;font-size:9pt"><?= te('Tipp: Im Druckdialog „Kopf- und Fußzeilen“ ausschalten.') ?></span>
    <?= $this->teil('teile/sprache_umschalter') ?>
</div>
<?php foreach (Session::flashHolen() as $meldung): ?>
    <div class="bildschirm-meldung"><?= e($meldung['text']) ?></div>
<?php endforeach; ?>
<div class="blatt">
    <?= $this->teil('druck/kopf', ['turnier' => $turnier, 'ueberschrift' => t('Anmeldung {dojo}', ['dojo' => $anmeldung['dojo']]), 'zusatz' => t('Anmelde-Nr. {nr}', ['nr' => $anmeldung['id']])]) ?>

    <div class="keinumbruch">
        <h2><?= te('Bearbeitungsstand') ?></h2>
        <?= $this->teil('teile/anmeldestand', ['stand' => $stand]) ?>
    </div>

    <table class="angaben">
        <tr><th><?= te('Dojo / Verein') ?></th><td><?= e($anmeldung['dojo']) ?></td><th><?= te('Stand') ?></th><td><?= e($stand['kurz']) ?></td></tr>
        <tr><th><?= te('Ansprechperson') ?></th><td><?= e($anmeldung['ansprechpartner']) ?></td><th><?= te('Eingereicht') ?></th><td><?= e($zeit($anmeldung['eingereicht_am'])) ?><?= $anmeldung['geaendert_am'] ? te(' · geändert {zeit}', ['zeit' => $zeit($anmeldung['geaendert_am'])]) : '' ?></td></tr>
        <tr><th><?= te('E-Mail') ?></th><td><?= e($anmeldung['email']) ?></td><th><?= te('Telefon') ?></th><td><?= e($anmeldung['telefon'] ?? '') ?></td></tr>
        <tr><th><?= te('Meldeschluss') ?></th><td><?= $turnier['meldeschluss'] ? e(datum_de($turnier['meldeschluss'])) : '–' ?></td><th><?= te('Starts / Teams') ?></th>
            <?php $starts = ['n' => count($angaben['teilnehmer']), 'einzel' => array_sum(array_column($preis['posten'], 'anzahl')) - count($angaben['teams']), 'teams' => count($angaben['teams'])]; ?>
            <td><?= count($angaben['teams']) === 1 ? te('{n} Teilnehmer · {einzel} Einzelstarts · {teams} Team', $starts) : te('{n} Teilnehmer · {einzel} Einzelstarts · {teams} Teams', $starts) ?></td></tr>
    </table>

    <?php if ($mitGeld && $anmeldung['status'] !== 'abgelehnt'): ?>
    <div class="keinumbruch zahlung-block">
        <?php if ($stand['bezahlt_voll']): ?>
            <h2><?= te('Zahlungsbestätigung') ?></h2>
            <p><?= $anmeldung['bezahlt_am']
                ? th('<strong>{betrag}</strong> erhalten am <strong>{datum}</strong> – vielen Dank.', ['betrag' => $euro($stand['bezahlt']), 'datum' => datum_de($anmeldung['bezahlt_am'])])
                : th('<strong>{betrag}</strong> erhalten – vielen Dank.', ['betrag' => $euro($stand['bezahlt'])]) ?>
                <?php if ($stand['bezahlt'] > $stand['betrag']): ?><?= te('Guthaben {betrag} (nach Änderung der Anmeldung) – wird erstattet bzw. verrechnet.', ['betrag' => $euro($stand['bezahlt'] - $stand['betrag'])]) ?><?php endif; ?></p>
            <p class="klein"><?= $rechnungLink !== null ? te('Diese Übersicht gilt als Anmelde- und Zahlungsbestätigung; die Rechnung gibt es oben über „Rechnung“.') : te('Diese Übersicht gilt als Anmelde- und Zahlungsbestätigung. Eine Rechnung (z. B. für die Vereinsbuchhaltung) stellt die Wettkampfleitung auf Wunsch aus.') ?></p>
        <?php else: ?>
            <h2><?= te('Zahlungsaufforderung') ?><?= $version > 1 ? te(' · Version {version}', ['version' => $version]) : '' ?></h2>
            <?php if ($version > 1): ?>
                <p class="klein"><?= te('Die Anmeldung wurde geändert – maßgeblich ist immer diese neueste Fassung ({zeit}). Frühere Fassungen sind damit ungültig; bereits Gezahltes ist verrechnet.', ['zeit' => $zeit((string) $anmeldung['aufforderung_am'])]) ?></p>
            <?php endif; ?>
            <div class="zahlung-mit-qr">
            <table class="angaben">
                <tr><th><?= te('Startgeld laut Anmeldung') ?></th><td class="zahl"><?= e($euro($stand['betrag'])) ?></td></tr>
                <?php if ($stand['bezahlt'] > 0): ?><tr><th><?= te('bereits erhalten') ?></th><td class="zahl"><?= e($euro($stand['bezahlt'])) ?></td></tr><?php endif; ?>
                <tr><th><?= te('Zu überweisen') ?></th><td class="zahl"><strong><?= e($euro($stand['offen'])) ?></strong></td></tr>
                <?php if ($frist !== null): ?><tr><th><?= te('Zahlbar bis') ?></th><td><?= e(datum_de($frist)) ?></td></tr><?php endif; ?>
                <tr><th><?= te('Verwendungszweck') ?></th><td><strong><?= e($z['nummer']) ?></strong> · <?= e($anmeldung['dojo']) ?></td></tr>
                <?php if ($z['absender'] !== ''): ?><tr><th><?= te('Empfänger') ?></th><td><?= e(implode(', ', array_filter(array_map('trim', explode("\n", $z['absender']))))) ?></td></tr><?php endif; ?>
                <?php if ($z['bank'] !== ''): ?><tr><th><?= te('Bankverbindung') ?></th><td><?= nl2br(e($z['bank'])) ?></td></tr><?php endif; ?>
            </table>
            <?php if (($girocode ?? null) !== null): ?>
                <figure class="girocode">
                    <?= $girocode /* vom Server erzeugtes SVG */ ?>
                    <figcaption><?= te('Mit der Banking-App scannen') ?><br><span><?= te('(Foto-Überweisung / GiroCode) – Betrag und Verwendungszweck sind ausgefüllt') ?></span></figcaption>
                </figure>
            <?php endif; ?>
            </div>
            <?php if ($z['bank'] === '' && $einstellungen['hinweis'] === ''): ?>
                <p class="klein"><?= te('Die Bankverbindung teilt die Wettkampfleitung noch mit.') ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <h2><?= te('Teilnehmer') ?></h2>
    <table>
        <thead><tr><th>#</th><th><?= te('Name') ?></th><th><?= te('G.') ?></th><th><?= te('Geburtsdatum') ?></th><th><?= te('Alter*') ?></th><th><?= te('Graduierung') ?></th><th>Kata</th><th>Kumite</th><th><?= te('Team') ?></th><th class="rechts"><?= te('Startgeld') ?></th></tr></thead>
        <tbody>
        <?php $nr = 0; foreach ($angaben['teilnehmer'] as $k => $t): $nr++; ?>
            <tr>
                <td><?= $nr ?></td>
                <td><?= e($t['nachname'] . ', ' . $t['vorname']) ?></td>
                <td><?= e(strtoupper($t['geschlecht'])) ?></td>
                <td><?= e(datum_de($t['geburtsdatum'])) ?></td>
                <td><?= Alter::amStichtag($t['geburtsdatum'], $turnier['datum']) ?></td>
                <td><?= e(Graduierung::text((int) $t['graduierung'])) ?></td>
                <td><?= $t['kata'] ? '✓' : '' ?></td>
                <td><?= $t['kumite'] === '' ? '' : e($t['kumite'] === 'kumite' ? '✓' : kumite_form_text(substr($t['kumite'], 7))) ?></td>
                <td class="klein"><?= e(implode(', ', $teamsVon[$k] ?? [])) ?></td>
                <td class="rechts zahl"><?= e($euro(($t['kata'] ? $einstellungen['preis_kata'] : 0) + ($t['kumite'] !== '' ? $einstellungen['preis_kumite'] : 0))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="klein"><?= te('* Alter am Turniertag – danach richtet sich die Klasse. G. = Geschlecht (W/M/D), (E) = Ersatz.') ?></p>

    <?php if ($angaben['teams']): ?>
        <h2><?= te('Teams') ?></h2>
        <table>
            <thead><tr><th><?= te('Team') ?></th><th><?= te('Disziplin') ?></th><th><?= te('Mitglieder') ?></th><th><?= te('Ersatz') ?></th><th class="rechts"><?= te('Startgeld') ?></th></tr></thead>
            <tbody>
            <?php foreach ($angaben['teams'] as $team): ?>
                <tr>
                    <td><?= e($team['name']) ?></td>
                    <td><?= e(disziplin_text($team['disziplin'])) ?></td>
                    <td><?= e(implode(', ', array_map($name, $team['mitglieder']))) ?></td>
                    <td><?= e($team['ersatz'] === null ? '' : $name($team['ersatz'])) ?></td>
                    <td class="rechts zahl"><?= e($euro((int) ($einstellungen['preis_' . $team['disziplin']] ?? 0))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="keinumbruch">
        <h2><?= te('Startgeld') ?></h2>
        <table class="preis">
            <thead><tr><th><?= te('Posten') ?></th><th class="rechts"><?= te('Anzahl') ?></th><th class="rechts"><?= te('Einzelpreis') ?></th><th class="rechts"><?= te('Summe') ?></th></tr></thead>
            <tbody>
            <?php foreach ($preis['posten'] as $p): ?>
                <tr><td><?= e($p['text']) ?></td><td class="rechts zahl"><?= $p['anzahl'] ?></td><td class="rechts zahl"><?= e($euro($p['einzel'])) ?></td><td class="rechts zahl"><?= e($euro($p['summe'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="3"><?= te('Gesamtpreis') ?></th><th class="rechts zahl"><?= e($euro($preis['summe'])) ?></th></tr></tfoot>
        </table>
        <?php if ($einstellungen['hinweis'] !== ''): ?>
            <p class="hinweis"><?= nl2br(e($einstellungen['hinweis'])) ?></p>
        <?php endif; ?>
        <?php if ($anmeldung['bemerkung']): ?>
            <p><strong><?= te('Bemerkung:') ?></strong> <?= nl2br(e($anmeldung['bemerkung'])) ?></p>
        <?php endif; ?>
        <p class="klein"><?= te('Bearbeitungslink (nicht weitergeben):') ?> <span id="bearbeitungslink" data-absolut><?= e(url("anmeldung/$tid/{$anmeldung['code']}")) ?></span></p>
    </div>
</div>
</body>
</html>
