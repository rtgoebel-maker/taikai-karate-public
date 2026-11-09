<?php
/**
 * Druck-Layout (Vorschau mit Werkzeugleiste, Druck über den Browser).
 * @var string $inhalt
 * @var string $titel
 * @var array<string, mixed> $turnier
 */
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title><?= e($titel) ?> · <?= e($turnier['name']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('druck.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('baum.css')) ?>">
    <script src="<?= e(asset('verhalten.js')) ?>" defer></script>
</head>
<body>
<div class="werkzeug">
    <strong><?= e($titel) ?></strong>
    <button type="button" data-drucken>Drucken / als PDF speichern</button>
    <a href="<?= e(url("turnier/{$turnier['id']}/druck")) ?>">Zum Druckcenter</a>
    <?= $zusatz ?? '' ?>
    <span style="margin-left:auto;font-size:9pt">Tipp: Im Druckdialog „Kopf- und Fußzeilen“ ausschalten.</span>
</div>
<?= $inhalt ?>
</body>
</html>
