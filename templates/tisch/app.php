<?php
/**
 * Tablet-Oberfläche der Tischbesatzung (eine Tatami).
 * @var array<string, mixed> $tatami
 * @var bool $alsLeitung
 * @var bool $pinPflicht   Korrekturen nur mit Leitungs-PIN
 */
use Wettkampf\Kern\Http\Session;

$query = $alsLeitung ? '?tatami=' . (int) $tatami['id'] : '';
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?= e($tatami['bezeichnung']) ?> · Tisch</title>
    <?= $this->teil('teile/icons') ?>
    <link rel="stylesheet" href="<?= e(asset('tisch.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('dialog.css')) ?>">
    <?= $this->teil('teile/farben') ?>
</head>
<body data-programm="<?= e(programm_version()) ?>" data-csrf="<?= e(Session::csrfToken()) ?>"
      data-api="<?= e(url('api/v1/tisch')) ?>"
      data-query="<?= e($query) ?>"
      data-leitung="<?= $alsLeitung ? '1' : '0' ?>"
      data-pin-pflicht="<?= $pinPflicht ? '1' : '0' ?>">
<header class="leiste">
    <div>
        <div class="klein"><?= e($tatami['turnier']) ?></div>
        <strong class="tatami-name"><?= e($tatami['bezeichnung']) ?></strong>
    </div>
    <div id="fortschritt" class="klein"></div>
    <div id="verbindung" class="verbindung ok">online</div>
    <span data-feedback-platz="knopf-leise"></span>
    <a class="knopf-leise" href="<?= e(url('tisch/ausruf') . $query) ?>" title="Starter aufrufen und Anwesenheit bestätigen – auch auf dem Handy">📣 Ausruf</a>
    <?php if ($alsLeitung): ?>
        <span class="knopf-leise" title="Von der Wettkampfleitung geöffnet. Korrekturen erfordern trotzdem die Leitungs-PIN, sobald eine vergeben ist.">Leitungsansicht</span>
    <?php endif; ?>
    <?php if (!$alsLeitung): ?>
        <form method="post" action="<?= e(url('tisch/abmelden')) ?>" data-bestaetigen="Tablet von dieser Tatami abmelden?" data-bestaetigen-ja="Abmelden">
            <input type="hidden" name="_csrf" value="<?= e(Session::csrfToken()) ?>">
            <button class="knopf-leise" type="submit">Abmelden</button>
        </form>
    <?php endif; ?>
</header>
<div id="meldung" class="meldung" hidden></div>
<main class="haupt">
    <section id="karte" class="karte-gross"><p class="leer">Lade …</p></section>
    <aside class="seitenleiste">
        <div id="ausruf-stand"></div>
        <h2>Als Nächstes</h2>
        <ol id="naechste" class="liste"></ol>
        <h2>Zuletzt eingetragen</h2>
        <ol id="letzte" class="liste"></ol>
    </aside>
</main>
<script src="<?= e(asset('app.js')) ?>"></script>
<script src="<?= e(asset('tisch.js')) ?>"></script>
<?= $this->teil('teile/feedback', ['kompakt' => true]) ?>
</body>
</html>
