<?php
/**
 * Ansicht „Ausruf“ der Tischbesatzung (Handy, als Web-App installierbar):
 * kommende Kämpfe der Tatami mit AKA/SHIRO, Anwesenheit bestätigen, Ausruf abschließen.
 * @var array<string, mixed> $tatami
 * @var bool $alsLeitung
 */
use Wettkampf\Kern\Http\Session;

$query = $alsLeitung ? '?tatami=' . (int) $tatami['id'] : '';
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1d2b45">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Ausruf">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="<?= e(url('tisch/manifest.webmanifest') . '?ansicht=ausruf') ?>">
    <link rel="icon" href="<?= e(asset('ausruf-icon.svg')) ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= e(asset('ausruf-icon.svg')) ?>">
    <title>Ausruf · <?= e($tatami['bezeichnung']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('ausruf.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('dialog.css')) ?>">
    <?= $this->teil('teile/farben') ?>
</head>
<body data-programm="<?= e(programm_version()) ?>" data-csrf="<?= e(Session::csrfToken()) ?>" data-api="<?= e(url('api/v1/tisch')) ?>" data-query="<?= e($query) ?>" data-leitung="<?= $alsLeitung ? '1' : '0' ?>">
<header class="kopf">
    <div>
        <div class="klein"><?= e($tatami['turnier']) ?></div>
        <strong><?= e($tatami['bezeichnung']) ?> · Ausruf</strong>
    </div>
    <button type="button" class="vollbild" data-vollbild hidden title="Vollbild: Adress- und Tableiste ausblenden">⛶</button>
    <span id="verbindung" class="verbindung ok">online</span>
</header>
<nav class="ansichten">
    <a href="<?= e(url('tisch/app') . $query) ?>">Eingabe</a>
    <a class="aktiv" href="<?= e(url('tisch/ausruf') . $query) ?>">Ausruf</a>
    <span data-feedback-platz class="feedback-platz"></span>
    <?php if (!$alsLeitung): ?>
        <form method="post" action="<?= e(url('tisch/abmelden')) ?>" data-bestaetigen="Von dieser Tatami abmelden?" data-bestaetigen-ja="Abmelden">
            <input type="hidden" name="_csrf" value="<?= e(Session::csrfToken()) ?>"><button type="submit">Abmelden</button>
        </form>
    <?php endif; ?>
</nav>
<div id="meldung" class="meldung" hidden></div>
<main id="inhalt"><p class="leer">Lade …</p></main>
<script src="<?= e(asset('app.js')) ?>"></script>
<script src="<?= e(asset('ausruf.js')) ?>"></script>
<?= $this->teil('teile/feedback', ['kompakt' => true]) ?>
</body>
</html>
