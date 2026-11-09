<?php
/**
 * Rechnung zu einer Online-Anmeldung (A4; Drucken bzw. als PDF speichern) – für die Leitung auf Wunsch,
 * für das Dojo nur, wenn das Turnier sie anbietet und die Zahlung eingegangen ist.
 * @var array<string, mixed> $blatt Daten für rechnung/blatt (RechnungService::anmeldungRechnung)
 * @var string $zurueck
 */
$empfaenger = $blatt['empfaenger'];
$nummer = $blatt['nummer'];
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rechnung <?= e($nummer) ?> · <?= e($empfaenger['verein']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('druck.css')) ?>">
    <script src="<?= e(asset('verhalten.js')) ?>" defer></script>
</head>
<body>
<div class="werkzeug">
    <strong>Rechnung · <?= e($empfaenger['verein']) ?></strong>
    <button type="button" data-drucken>Drucken / als PDF speichern</button>
    <a href="<?= e($zurueck) ?>">Zurück</a>
    <span style="margin-left:auto;font-size:9pt">Tipp: Im Druckdialog „Kopf- und Fußzeilen“ ausschalten.</span>
</div>
<?= $this->teil('rechnung/blatt', $blatt) ?>
</body>
</html>
