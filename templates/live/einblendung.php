<?php
/**
 * Einblendung für den Livestream einer Tatami (OBS-Browserquelle, 1920×1080, transparenter Hintergrund).
 * Inhalt kommt per Abfrage aus /api/v1/turnier/{id}/tatami/{nummer}/einblendung (einblendung.js, nur textContent).
 * @var array<string, mixed> $turnier
 * @var array{nummer: int|string, bezeichnung: string} $tatami
 * @var bool $oben
 * @var bool $gruen
 * @var bool $ergebnis
 */
$tid = (int) $turnier['id'];
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Einblendung <?= e($tatami['bezeichnung']) ?> · <?= e($turnier['name']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('einblendung.css')) ?>">
</head>
<body class="<?= $oben ? 'oben' : 'unten' ?><?= $gruen ? ' gruen' : '' ?>"
      data-quelle="<?= e(url("api/v1/turnier/$tid/tatami/{$tatami['nummer']}/einblendung")) ?>"
      data-ergebnis="<?= $ergebnis ? '1' : '0' ?>">
<div class="band" id="band" hidden>
    <div class="kopf"><span id="tatami"><?= e($tatami['bezeichnung']) ?></span><span id="info"></span></div>
    <div class="paar">
        <div class="seite aka"><span class="kennung">AKA</span><span class="name" id="aka-name"></span><span class="verein" id="aka-verein"></span></div>
        <div class="seite shiro"><span class="kennung">SHIRO</span><span class="name" id="shiro-name"></span><span class="verein" id="shiro-verein"></span></div>
    </div>
    <div class="einzel"><span class="name" id="starter-name"></span><span class="verein" id="starter-verein"></span></div>
    <div class="sieger" id="sieger"><span class="titel" id="sieger-titel"></span><span class="name" id="sieger-name"></span><span class="verein" id="sieger-verein"></span><span class="text" id="sieger-text"></span></div>
</div>
<script src="<?= e(asset('einblendung.js')) ?>" defer></script>
</body>
</html>
