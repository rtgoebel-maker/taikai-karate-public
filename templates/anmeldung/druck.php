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
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anmeldung <?= e($anmeldung['dojo']) ?> · <?= e($turnier['name']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('druck.css')) ?>">
    <script src="<?= e(asset('verhalten.js')) ?>" defer></script>
</head>
<body>
<div class="werkzeug">
    <strong>Anmeldung · <?= e($anmeldung['dojo']) ?></strong>
    <button type="button" data-drucken>Drucken / als PDF speichern</button>
    <a href="<?= e($zurueck) ?>"><?= $bearbeitbar ? 'Anmeldung bearbeiten' : 'Zurück' ?></a>
    <?php if ($rechnungLink !== null): ?><a href="<?= e($rechnungLink) ?>">Rechnung</a><?php endif; ?>
    <?php if ($rechnungLeitung !== null && $mitGeld && $anmeldung['status'] !== 'abgelehnt'): ?><a href="<?= e($rechnungLeitung) ?>">Rechnung erstellen</a><?php endif; ?>
    <span style="margin-left:auto;font-size:9pt">Tipp: Im Druckdialog „Kopf- und Fußzeilen“ ausschalten.</span>
</div>
<?php foreach (Session::flashHolen() as $meldung): ?>
    <div class="bildschirm-meldung"><?= e($meldung['text']) ?></div>
<?php endforeach; ?>
<div class="blatt">
    <?= $this->teil('druck/kopf', ['turnier' => $turnier, 'ueberschrift' => 'Anmeldung ' . $anmeldung['dojo'], 'zusatz' => 'Anmelde-Nr. ' . $anmeldung['id']]) ?>

    <div class="keinumbruch">
        <h2>Bearbeitungsstand</h2>
        <?= $this->teil('teile/anmeldestand', ['stand' => $stand]) ?>
    </div>

    <table class="angaben">
        <tr><th>Dojo / Verein</th><td><?= e($anmeldung['dojo']) ?></td><th>Stand</th><td><?= e($stand['kurz']) ?></td></tr>
        <tr><th>Ansprechperson</th><td><?= e($anmeldung['ansprechpartner']) ?></td><th>Eingereicht</th><td><?= e(date('d.m.Y H:i', strtotime($anmeldung['eingereicht_am']))) ?><?= $anmeldung['geaendert_am'] ? ' · geändert ' . e(date('d.m.Y H:i', strtotime($anmeldung['geaendert_am']))) : '' ?></td></tr>
        <tr><th>E-Mail</th><td><?= e($anmeldung['email']) ?></td><th>Telefon</th><td><?= e($anmeldung['telefon'] ?? '') ?></td></tr>
        <tr><th>Meldeschluss</th><td><?= $turnier['meldeschluss'] ? e(datum_de($turnier['meldeschluss'])) : '–' ?></td><th>Starts / Teams</th>
            <td><?= count($angaben['teilnehmer']) ?> Teilnehmer · <?= array_sum(array_column($preis['posten'], 'anzahl')) - count($angaben['teams']) ?> Einzelstarts · <?= count($angaben['teams']) ?> <?= count($angaben['teams']) === 1 ? 'Team' : 'Teams' ?></td></tr>
    </table>

    <?php if ($mitGeld && $anmeldung['status'] !== 'abgelehnt'): ?>
    <div class="keinumbruch zahlung-block">
        <?php if ($stand['bezahlt_voll']): ?>
            <h2>Zahlungsbestätigung</h2>
            <p><strong><?= e($euro($stand['bezahlt'])) ?></strong> erhalten<?= $anmeldung['bezahlt_am'] ? ' am <strong>' . e(datum_de($anmeldung['bezahlt_am'])) . '</strong>' : '' ?> – vielen Dank.
                <?php if ($stand['bezahlt'] > $stand['betrag']): ?>Guthaben <?= e($euro($stand['bezahlt'] - $stand['betrag'])) ?> (nach Änderung der Anmeldung) – wird erstattet bzw. verrechnet.<?php endif; ?></p>
            <p class="klein">Diese Übersicht gilt als Anmelde- und Zahlungsbestätigung<?= $rechnungLink !== null ? '; die Rechnung gibt es oben über „Rechnung“' : '. Eine Rechnung (z. B. für die Vereinsbuchhaltung) stellt die Wettkampfleitung auf Wunsch aus' ?>.</p>
        <?php else: ?>
            <h2>Zahlungsaufforderung<?= $version > 1 ? ' · Version ' . $version : '' ?></h2>
            <?php if ($version > 1): ?>
                <p class="klein">Die Anmeldung wurde geändert – maßgeblich ist immer diese neueste Fassung (<?= e(date('d.m.Y H:i', (int) strtotime((string) $anmeldung['aufforderung_am']))) ?>). Frühere Fassungen sind damit ungültig; bereits Gezahltes ist verrechnet.</p>
            <?php endif; ?>
            <div class="zahlung-mit-qr">
            <table class="angaben">
                <tr><th>Startgeld laut Anmeldung</th><td class="zahl"><?= e($euro($stand['betrag'])) ?></td></tr>
                <?php if ($stand['bezahlt'] > 0): ?><tr><th>bereits erhalten</th><td class="zahl"><?= e($euro($stand['bezahlt'])) ?></td></tr><?php endif; ?>
                <tr><th>Zu überweisen</th><td class="zahl"><strong><?= e($euro($stand['offen'])) ?></strong></td></tr>
                <?php if ($frist !== null): ?><tr><th>Zahlbar bis</th><td><?= e(datum_de($frist)) ?></td></tr><?php endif; ?>
                <tr><th>Verwendungszweck</th><td><strong><?= e($z['nummer']) ?></strong> · <?= e($anmeldung['dojo']) ?></td></tr>
                <?php if ($z['absender'] !== ''): ?><tr><th>Empfänger</th><td><?= e(implode(', ', array_filter(array_map('trim', explode("\n", $z['absender']))))) ?></td></tr><?php endif; ?>
                <?php if ($z['bank'] !== ''): ?><tr><th>Bankverbindung</th><td><?= nl2br(e($z['bank'])) ?></td></tr><?php endif; ?>
            </table>
            <?php if (($girocode ?? null) !== null): ?>
                <figure class="girocode">
                    <?= $girocode /* vom Server erzeugtes SVG */ ?>
                    <figcaption>Mit der Banking-App scannen<br><span>(Foto-Überweisung / GiroCode) – Betrag und Verwendungszweck sind ausgefüllt</span></figcaption>
                </figure>
            <?php endif; ?>
            </div>
            <?php if ($z['bank'] === '' && $einstellungen['hinweis'] === ''): ?>
                <p class="klein">Die Bankverbindung teilt die Wettkampfleitung noch mit.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <h2>Teilnehmer</h2>
    <table>
        <thead><tr><th>#</th><th>Name</th><th>G.</th><th>Geburtsdatum</th><th>Alter*</th><th>Graduierung</th><th>Kata</th><th>Kumite</th><th>Team</th><th class="rechts">Startgeld</th></tr></thead>
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
    <p class="klein">* Alter am Turniertag – danach richtet sich die Klasse. G. = Geschlecht (W/M/D), (E) = Ersatz.</p>

    <?php if ($angaben['teams']): ?>
        <h2>Teams</h2>
        <table>
            <thead><tr><th>Team</th><th>Disziplin</th><th>Mitglieder</th><th>Ersatz</th><th class="rechts">Startgeld</th></tr></thead>
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
        <h2>Startgeld</h2>
        <table class="preis">
            <thead><tr><th>Posten</th><th class="rechts">Anzahl</th><th class="rechts">Einzelpreis</th><th class="rechts">Summe</th></tr></thead>
            <tbody>
            <?php foreach ($preis['posten'] as $p): ?>
                <tr><td><?= e($p['text']) ?></td><td class="rechts zahl"><?= $p['anzahl'] ?></td><td class="rechts zahl"><?= e($euro($p['einzel'])) ?></td><td class="rechts zahl"><?= e($euro($p['summe'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="3">Gesamtpreis</th><th class="rechts zahl"><?= e($euro($preis['summe'])) ?></th></tr></tfoot>
        </table>
        <?php if ($einstellungen['hinweis'] !== ''): ?>
            <p class="hinweis"><?= nl2br(e($einstellungen['hinweis'])) ?></p>
        <?php endif; ?>
        <?php if ($anmeldung['bemerkung']): ?>
            <p><strong>Bemerkung:</strong> <?= nl2br(e($anmeldung['bemerkung'])) ?></p>
        <?php endif; ?>
        <p class="klein">Bearbeitungslink (nicht weitergeben): <span id="bearbeitungslink" data-absolut><?= e(url("anmeldung/$tid/{$anmeldung['code']}")) ?></span></p>
    </div>
</div>
</body>
</html>
