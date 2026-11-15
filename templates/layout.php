<?php
/**
 * Layout der Wettkampfleitung.
 * @var string $inhalt
 * @var string|null $titel
 * @var array|null $turnier   aktives Turnier (blendet die Turnier-Navigation ein)
 * @var string|null $aktiv    Schlüssel des aktiven Menüpunkts
 * @var string|null $instanzId Kennung dieser Installation (Hinweisband zur Führung, Schritt 9c)
 * @var int|null $neuLaden Seite nach so vielen Sekunden neu laden (z. B. solange Geräte auf Freigabe warten)
 * @var array<string, mixed>|null $syncStand Übertragung an Online (Hallen-Laptop, Schritt 9e)
 */
use Wettkampf\Kern\Http\Session;
use Wettkampf\Sync\Fuehrung;

$turnier ??= null;
$aktiv ??= '';
$benutzer = Session::benutzer();
$menue = $turnier ? [
    'uebersicht' => [t('Übersicht'), "turnier/{$turnier['id']}"],
    'anmeldungen' => [t('Anmeldungen'), "turnier/{$turnier['id']}/anmeldungen"],
    'teilnehmer' => [t('Teilnehmer'), "turnier/{$turnier['id']}/teilnehmer"],
    'teams' => [t('Teams'), "turnier/{$turnier['id']}/teams"],
    'klassen' => [t('Klassen'), "turnier/{$turnier['id']}/klassen"],
    'plan' => [t('Tatami-Plan'), "turnier/{$turnier['id']}/plan"],
    'ergebnisse' => [t('Ergebnisse'), "turnier/{$turnier['id']}/ergebnisse"],
    'druck' => [t('Druck'), "turnier/{$turnier['id']}/druck"],
    'einstellungen' => [t('Einstellungen'), "turnier/{$turnier['id']}/einstellungen"],
    'verlauf' => [t('Verlauf'), "turnier/{$turnier['id']}/verlauf"],
] : [];
?><!doctype html>
<html lang="<?= e(sprache()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (!empty($neuLaden)): ?><meta http-equiv="refresh" content="<?= (int) $neuLaden ?>"><?php endif; ?>
    <title><?= e(($titel ?? '') !== '' ? $titel . ' · ' : '') ?>Taikai</title>
    <?= $this->teil('teile/icons') ?>
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('dialog.css')) ?>">
    <?= $this->teil('teile/farben') ?>
    <?= $this->teil('teile/sprache_skript') ?>
    <script src="<?= e(asset('app.js')) ?>" defer></script>
    <script src="<?= e(asset('verhalten.js')) ?>" defer></script>
</head>
<body data-csrf="<?= e(Session::csrfToken()) ?>">
<header class="kopf">
    <a class="marke" href="<?= e(url(Session::istLeitung() || Session::coachVerein() === null ? '/' : 'coach')) ?>"><img src="<?= e(asset('logo/taikai-zeichen-dunkel.svg')) ?>" alt="" width="30" height="30">Taikai</a>
    <?php if ($turnier): ?>
        <span class="kopf-turnier"><?= e($turnier['name']) ?> · <?= e(datum_de($turnier['datum'])) ?></span>
        <?php $kopfPhase = \Wettkampf\Domain\TurnierPhase::von($turnier); ?>
        <a class="phase-marke phase-<?= e($kopfPhase) ?>" href="<?= e(url("turnier/{$turnier['id']}")) ?>" title="<?= te('Turnierphase – in der Übersicht umschalten') ?>"><?= e(\Wettkampf\Domain\TurnierPhase::text($kopfPhase)) ?></a>
        <?php if (($nichtErschienenAnzahl ?? 0) > 0): ?>
            <a class="phase-marke warn-marke" href="<?= e(url("turnier/{$turnier['id']}")) ?>#nicht-erschienen" title="<?= te('Rückmeldungen der Ausrufer – Entscheidung in der Übersicht') ?>">⚠ <?= te('{n} nicht erschienen', ['n' => (int) $nichtErschienenAnzahl]) ?></a>
        <?php endif; ?>
        <?php if (!empty($kataBeendet)): ?>
            <a class="phase-marke pause-marke" href="<?= e(url("turnier/{$turnier['id']}")) ?>#kata-beendet" title="<?= te('Alle Kata-Gruppen sind gelaufen – Gelegenheit für eine allgemeine Pause') ?>">☕ <?= te('Kata beendet') ?></a>
        <?php endif; ?>
    <?php endif; ?>
    <nav class="kopf-rechts">
        <?php if (Session::istDemo()): ?>
            <a href="<?= e(url('vereine')) ?>"><?= te('Vereine') ?></a>
            <a href="<?= e(url('import-turnier')) ?>"><?= te('Sicherung') ?></a>
            <a href="<?= e(url('system')) ?>"<?= $aktiv === 'system' ? ' class="aktiv"' : '' ?>><?= te('System') ?></a>
            <a href="<?= e(url('verlauf')) ?>"<?= $aktiv === 'verlauf' && !$turnier ? ' class="aktiv"' : '' ?>><?= te('Verlauf') ?></a>
            <span class="tag" title="<?= te('Demo-Zugang: alle Demo-Turniere und eigene Turniere, keine Konten und keine Zugangsdaten') ?>"><?= te('Demo') ?></span>
        <?php endif; ?>
        <?php if (Session::istAdmin()): ?>
            <a href="<?= e(url('vereine')) ?>"><?= te('Vereine') ?></a>
            <a href="<?= e(url('benutzer')) ?>"><?= te('Benutzer') ?></a>
            <a href="<?= e(url('import-turnier')) ?>"><?= te('Sicherung') ?></a>
            <a href="<?= e(url('system')) ?>"<?= $aktiv === 'system' ? ' class="aktiv"' : '' ?>><?= te('System') ?></a>
            <a href="<?= e(url('verlauf')) ?>"<?= $aktiv === 'verlauf' && !$turnier ? ' class="aktiv"' : '' ?>><?= te('Verlauf') ?></a>
            <a href="<?= e(url('halle')) ?>"<?= $aktiv === 'halle' ? ' class="aktiv"' : '' ?> title="<?= te('Turnier an einen Hallen-Laptop übergeben bzw. dort übernehmen und zurückgeben') ?>"><?= te('Hallen-Laptop') ?></a>
            <?php if (($feedbackAktiv ?? false) || ($feedbackNeu ?? 0) > 0): ?>
                <a href="<?= e(url('feedback')) ?>"<?= $aktiv === 'feedback' ? ' class="aktiv"' : '' ?>><?= te('Feedback') ?><?= ($feedbackNeu ?? 0) > 0 ? ' <span class="zaehler">' . (int) $feedbackNeu . '</span>' : '' ?></a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (Session::coachVerein() !== null): ?>
            <a href="<?= e(url('coach')) ?>"<?= $aktiv === 'coach' ? ' class="aktiv"' : '' ?>><?= te('Meine Meldungen') ?></a>
        <?php endif; ?>
        <?php if (Session::istLeitung()): ?>
            <a href="<?= e(url('hilfe', $turnier ? ['turnier' => $turnier['id']] : [])) ?>"<?= $aktiv === 'hilfe' ? ' class="aktiv"' : '' ?>><?= te('Hilfe') ?></a>
        <?php endif; ?>
        <?= $this->teil('teile/sprache_umschalter') ?>
        <?php if ($benutzer): ?>
            <a href="<?= e(url('konto/sicherheit')) ?>"<?= $aktiv === 'sicherheit' ? ' class="aktiv"' : '' ?> title="<?= te('Authenticator-App, Wiederherstellungscodes, vertraute Geräte') ?>"><?= te('Konto & Sicherheit') ?></a>
            <form method="post" action="<?= e(url('logout')) ?>" class="inline">
                <?= csrf_feld() ?>
                <button class="link" type="submit"><?= te('Abmelden ({name})', ['name' => $benutzer['name']]) ?></button>
            </form>
        <?php endif; ?>
    </nav>
</header>
<?php if ($menue): ?>
    <nav class="menue">
        <?php foreach ($menue as $schluessel => [$text, $pfad]): ?>
            <a href="<?= e(url($pfad)) ?>"<?= $schluessel === $aktiv ? ' class="aktiv"' : '' ?>><?= e($text) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(url("live/{$turnier['id']}")) ?>" target="_blank" rel="noopener"><?= te('Statusseite') ?> ↗</a>
    </nav>
<?php endif; ?>
<?php if ($turnier && !empty($turnier['fuehrung_instanz'])): ?>
    <?php if (hash_equals((string) ($instanzId ?? ''), (string) $turnier['fuehrung_instanz'])): ?>
        <div class="meldung meldung-hinweis fuehrung-band"><?= (int) $turnier['uebergabe_nr'] > 0 ? te('Diese Installation führt das Turnier (Übergabe Nr. {nr}).', ['nr' => (int) $turnier['uebergabe_nr']]) : te('Diese Installation führt das Turnier.') ?>
            <a href="<?= e(url("turnier/{$turnier['id']}/halle")) ?>"><?= te('An Online zurückgeben') ?> →</a>
            <?php if (!empty($syncStand)): ?><?= $this->teil('sync/stand', ['stand' => $syncStand]) ?><?php endif; ?></div>
    <?php else: ?>
        <div class="meldung meldung-warnung fuehrung-band"><?= e(Fuehrung::text($turnier)) ?></div>
    <?php endif; ?>
<?php endif; ?>
<main class="inhalt">
    <?php foreach (Session::flashHolen() as $meldung): ?>
        <div class="meldung meldung-<?= e($meldung['typ']) ?>"><?= e($meldung['text']) ?></div>
    <?php endforeach; ?>
    <?= $inhalt ?>
</main>
<?= $this->teil('teile/fuss') ?>
<?= $benutzer ? $this->teil('teile/feedback') : '' ?>
</body>
</html>
