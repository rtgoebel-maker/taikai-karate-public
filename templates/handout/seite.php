<?php
/**
 * Druckbares Handout (A4) mit Screenshots – Hülle mit Werkzeugleiste, Inhalt aus handout/{name}.
 * @var string $name flyer | tisch | ausruf | leitung
 * @var string $titel
 * @var string $zurueck
 */
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Handout <?= e($titel) ?></title>
    <link rel="stylesheet" href="<?= e(asset('druck.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('handout.css')) ?>">
    <script src="<?= e(asset('verhalten.js')) ?>" defer></script>
</head>
<body class="handout">
<div class="werkzeug">
    <strong>Handout · <?= e($titel) ?></strong>
    <button type="button" data-drucken>Drucken / als PDF speichern</button>
    <a href="<?= e($zurueck) ?>">Zurück</a>
    <span style="margin-left:auto;font-size:9pt">Tipp: Im Druckdialog „Kopf- und Fußzeilen“ ausschalten, „Hintergrundgrafiken“ einschalten.</span>
</div>
<?= $this->teil('handout/' . $name) ?>
</body>
</html>
