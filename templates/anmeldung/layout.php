<?php
/**
 * Öffentliches Layout der Online-Anmeldung.
 * @var string $inhalt
 * @var array<string, mixed> $turnier
 */
use Wettkampf\Kern\Http\Session;
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anmeldung · <?= e($turnier['name']) ?></title>
    <?= $this->teil('teile/icons') ?>
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <?= $this->teil('teile/farben') ?>
    <script src="<?= e(asset('verhalten.js')) ?>" defer></script>
    <script src="<?= e(asset('anmeldung.js')) ?>" defer></script>
</head>
<body class="anmeldung-seite">
<header class="kopf">
    <span class="marke"><?= e($turnier['name']) ?></span>
    <span class="kopf-turnier"><?= e(datum_de($turnier['datum'])) ?><?= $turnier['ort'] ? ' · ' . e($turnier['ort']) : '' ?></span>
    <?php if (Session::coachVerein() !== null): ?>
        <nav class="kopf-rechts"><a href="<?= e(url('coach')) ?>">Meine Meldungen</a></nav>
    <?php endif; ?>
</header>
<main class="inhalt anmeldung-inhalt">
    <?php foreach (Session::flashHolen() as $meldung): ?>
        <div class="meldung meldung-<?= e($meldung['typ']) ?>"><?= e($meldung['text']) ?></div>
    <?php endforeach; ?>
    <?= $inhalt ?>
</main>
<?= $this->teil('teile/fuss') ?>
</body>
</html>
