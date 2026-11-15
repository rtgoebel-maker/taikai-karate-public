<?php
/**
 * Druckbares Handout (A4) mit Screenshots – Hülle mit Werkzeugleiste, Inhalt aus handout/{name}
 * bzw. handout/en/{name} in der englischen Oberfläche (falls vorhanden).
 * @var string $name flyer | tisch | ausruf | leitung
 * @var string $titel
 * @var string $zurueck
 */
$titel = match ($name) {
    'flyer' => t('Überblick – Flyer'),
    'tisch' => t('Tischbesatzung – Tablet'),
    'ausruf' => t('Ausruf – Handy'),
    'leitung' => t('Wettkampfleitung'),
    default => $titel,
};
$inhalt = sprache() === 'en' && is_file(__DIR__ . '/en/' . $name . '.php') ? 'handout/en/' . $name : 'handout/' . $name;
?><!doctype html>
<html lang="<?= e(sprache()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= te('Handout') ?> <?= e($titel) ?></title>
    <link rel="stylesheet" href="<?= e(asset('druck.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('handout.css')) ?>">
    <script src="<?= e(asset('verhalten.js')) ?>" defer></script>
</head>
<body class="handout">
<div class="werkzeug">
    <strong><?= te('Handout') ?> · <?= e($titel) ?></strong>
    <button type="button" data-drucken><?= te('Drucken / als PDF speichern') ?></button>
    <a href="<?= e($zurueck) ?>"><?= te('Zurück') ?></a>
    <span style="margin-left:auto;font-size:9pt"><?= te('Tipp: Im Druckdialog „Kopf- und Fußzeilen“ ausschalten, „Hintergrundgrafiken“ einschalten.') ?></span>
</div>
<?= $this->teil($inhalt) ?>
</body>
</html>
