<?php
/**
 * Vorschau einer Musterurkunde (im iframe des Druckcenters): nur das Blatt, ohne Werkzeugleiste.
 * @var string $inhalt gerenderte Urkunde (druck/urkunden)
 */
?><!doctype html>
<html lang="<?= e(sprache()) ?>">
<head>
    <meta charset="utf-8">
    <title><?= te('Vorschau') ?></title>
    <link rel="stylesheet" href="<?= e(asset('druck.css')) ?>">
</head>
<body class="urkunde-vorschau">
<?= $inhalt ?>
</body>
</html>
