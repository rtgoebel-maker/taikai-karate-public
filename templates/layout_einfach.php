<?php
/**
 * Schlichtes Layout für Anmeldung, Einrichtung und Fehlerseiten.
 * @var string $inhalt
 * @var string|null $titel
 * @var int|null $neuLaden Seite nach so vielen Sekunden neu laden (ohne JavaScript, z. B. Warten auf Freigabe)
 */
use Wettkampf\Kern\Http\Session;
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (!empty($neuLaden)): ?><meta http-equiv="refresh" content="<?= (int) $neuLaden ?>"><?php endif; ?>
    <title><?= e(($titel ?? '') !== '' ? $titel . ' · ' : '') ?>Taikai – Karate</title>
    <?= $this->teil('teile/icons') ?>
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <?= $this->teil('teile/farben') ?>
</head>
<body class="einfach">
<p class="einfach-logo"><img src="<?= e(asset('logo/taikai-logo.svg')) ?>" alt="Taikai – Karate" width="225" height="72"></p>
<main class="karte schmal">
    <?php foreach (Session::flashHolen() as $meldung): ?>
        <div class="meldung meldung-<?= e($meldung['typ']) ?>"><?= e($meldung['text']) ?></div>
    <?php endforeach; ?>
    <?= $inhalt ?>
</main>
<?= $this->teil('teile/fuss') ?>
</body>
</html>
