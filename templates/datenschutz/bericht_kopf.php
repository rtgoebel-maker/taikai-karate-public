<?php
/**
 * Gemeinsamer Kopf der Auskunftsberichte (eigenständige Druckseite).
 * @var string $titel
 * @var string $json Link zum JSON-Download
 */
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title><?= e($titel) ?></title>
    <link rel="stylesheet" href="<?= e(asset('druck.css')) ?>">
    <script src="<?= e(asset('verhalten.js')) ?>" defer></script>
</head>
<body>
<div class="werkzeug">
    <strong><?= e($titel) ?></strong>
    <button type="button" data-drucken>Drucken / als PDF speichern</button>
    <a href="<?= e($json) ?>">Als JSON herunterladen</a>
    <a href="<?= e(url('datenschutz/auskunft')) ?>">Zur Suche</a>
</div>
