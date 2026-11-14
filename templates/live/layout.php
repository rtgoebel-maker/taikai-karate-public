<?php
/**
 * Layout der öffentlichen Statusseite.
 * @var string $inhalt
 * @var array<string, mixed> $turnier
 * @var string $aktiv
 * @var bool|null $automatisch  Seite bei Änderungen automatisch neu laden
 * @var bool|null $offline      eigenständige Offlinekopie (CSS eingebettet, keine Live-Abfrage)
 * @var string|null $css        eingebettetes Stylesheet (Offlinekopie)
 * @var list<array{art: string, src: string}>|null $logos  Logos des Ausrichters (Systemeinstellungen)
 */
$tid = $turnier['id'];
$automatisch ??= true;
$offline ??= false;
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($turnier['name']) ?> · Live</title>
    <?php if (empty($offline)): ?><?= $this->teil('teile/icons') ?><?php endif; ?>
    <?php if ($offline): ?>
        <style><?= $css ?? '' ?></style>
    <?php else: ?>
        <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
        <link rel="stylesheet" href="<?= e(asset('baum.css')) ?>">
        <script src="<?= e(asset('app.js')) ?>" defer></script>
        <script src="<?= e(asset('verhalten.js')) ?>" defer></script>
        <?php if ($automatisch): ?><script src="<?= e(asset('live.js')) ?>" defer></script><?php endif; ?>
    <?php endif; ?>
    <?= $this->teil('teile/farben') ?>
</head>
<body class="live<?= count($menue ?? []) <= 1 ? ' nur-tatamis' : '' ?>"<?= $automatisch && !$offline ? ' data-beobachten="' . e(url("api/v1/turnier/$tid")) . '"' : '' ?>>
<header class="kopf">
    <?php if ($logos ?? []): ?>
        <span class="live-logos"><?php foreach ($logos as $logo): ?><img src="<?= e($logo['src']) ?>" alt="<?= $logo['art'] === 'dojo' ? 'Dojo-Logo' : 'Vereinslogo' ?>"><?php endforeach; ?></span>
    <?php endif; ?>
    <a class="marke" href="<?= e(url("live/$tid")) ?>"><?= e($turnier['name']) ?></a>
    <span class="kopf-turnier"><?= e(datum_de($turnier['datum'])) ?><?= $turnier['ort'] ? ' · ' . e($turnier['ort']) : '' ?></span>
    <?php if ($offline): ?>
        <span class="kopf-rechts" id="live-status">Offlinekopie</span>
    <?php else: ?>
        <span class="kopf-rechts live-anzeige" id="live-status" title="Die Anzeige aktualisiert sich automatisch"><span class="live-punkt" aria-hidden="true"></span><span class="live-text">live</span></span>
    <?php endif; ?>
</header>
<?php
$bereiche = $statusBereiche ?? ['starterlisten' => true, 'ergebnisse' => true];
$menue = array_filter(
    ['tatamis' => ['Tatamis live', "live/$tid"], 'starterlisten' => ['Starterlisten & Bäume', "live/$tid/starterlisten"], 'ergebnisse' => ['Ergebnisse', "live/$tid/ergebnisse"]],
    static fn ($k) => $k === 'tatamis' || !empty($bereiche[$k]),
    ARRAY_FILTER_USE_KEY,
);
?>
<?php if (!$offline && count($menue) > 1): ?>
<nav class="menue">
    <?php foreach ($menue as $k => [$text, $pfad]): ?>
        <a href="<?= e(url($pfad)) ?>"<?= $k === $aktiv ? ' class="aktiv"' : '' ?>><?= e($text) ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
<main class="inhalt">
    <?php $livePhase = \Wettkampf\Domain\TurnierPhase::von($turnier); ?>
    <?php if (!$offline && in_array($livePhase, ['vorbereitung', 'meldephase'], true)): ?>
        <div class="meldung meldung-hinweis">Das Turnier beginnt am <?= e(datum_de($turnier['datum'])) ?>. Die Live-Anzeige startet, sobald die Wettkampfleitung das Turnier gestartet hat.</div>
    <?php elseif (!$offline && $livePhase === 'abgeschlossen'): ?>
        <div class="meldung meldung-erfolg">Das Turnier ist beendet – vielen Dank an alle Starter, Kampfrichter und Helfer. Die Ergebnisse stehen unter „Ergebnisse“.</div>
    <?php endif; ?>
    <?= $inhalt ?>
    <?php if (!$offline): ?><p class="klein leise datenschutz-link"><a href="<?= e(url("live/$tid/datenschutz")) ?>">Datenschutzhinweise zum Turnier</a></p><?php endif; ?>
</main>
<?= $offline ? '' : $this->teil('teile/fuss') ?>
</body>
</html>
