<?php
/**
 * Urkunden (A4 hoch, eine Seite je Urkunde). Mit $vordruck nur der Text –
 * an derselben Stelle, für vorgedrucktes Urkundenpapier.
 * @var array<string, mixed> $turnier
 * @var list<array{klasse: array<string, mixed>, platz: int, name: string, verein: string, team: string|null}> $urkunden
 * @var bool $vordruck
 * @var array{untertitel: string, unterschrift_links: string, unterschrift_rechts: string} $e
 * @var array<string, string> $logos Art (verband, verein, dojo, unterschrift_*, hintergrund) → Bild-URL
 * @var array<string, array{pos: string, groesse: int}>|null $layout Lage und Größe der Logos (Ausgaben\UrkundenLayout)
 */
use Wettkampf\Ausgaben\UrkundenLayout;

$layout ??= UrkundenLayout::lesen(null);
$logoBilder = array_intersect_key($logos, UrkundenLayout::STANDARD);
$logoAlt = ['verband' => t('Verbandslogo'), 'verein' => t('Vereinslogo'), 'dojo' => t('Dojo-Logo')];
$logoBild = static fn(string $art): string => '<img src="' . e($logos[$art]) . '" alt="' . e($logoAlt[$art]) . '" style="' . e(UrkundenLayout::stil($layout[$art])) . '">';
$logoZeile = static function (string $zeile) use ($layout, $logoBilder, $logoBild): string {
    $spalten = UrkundenLayout::zeile($layout, $logoBilder, $zeile);
    if ($spalten === []) {
        return '';
    }
    $html = '<div class="u-logos' . ($zeile === 'unten' ? ' unten' : '') . '">';
    foreach ($spalten as $spalte => $arten) {
        $html .= '<div class="u-logo ' . $spalte . '">' . implode('', array_map($logoBild, $arten)) . '</div>';
    }
    return $html . '</div>';
};
$ortDatum = trim($turnier['ort'] ? t('{ort}, den {datum}', ['ort' => $turnier['ort'], 'datum' => datum_de($turnier['datum'])]) : t('den {datum}', ['datum' => datum_de($turnier['datum'])]));
?>
<?php if (!$urkunden): ?>
    <div class="blatt"><p><?= te('Noch keine bestätigten Platzierungen. Urkunden gibt es, sobald eine Klasse bestätigt ist (Klasse → „Platzierungen bestätigen“).') ?></p></div>
<?php endif; ?>
<?php foreach ($urkunden as $u): $medaille = $u['platz'] === 1 ? 'gold' : ($u['platz'] === 2 ? 'silber' : 'bronze'); ?>
    <section class="blatt urkunde <?= $medaille ?><?= $vordruck ? ' vordruck' : '' ?><?= isset($logos['hintergrund']) && !$vordruck ? ' mit-hintergrund' : '' ?>">
        <?php if (isset($logos['hintergrund']) && !$vordruck): ?><img class="u-hintergrund" src="<?= e($logos['hintergrund']) ?>" alt=""><?php endif; ?>
        <div class="u-rahmen">
            <div class="u-wasserzeichen" aria-hidden="true">空手</div>
            <?= $logoZeile('oben') ?>
            <?php foreach ($logoBilder as $art => $_): if (str_starts_with($layout[$art]['pos'], 'mitte-')): ?>
                <div class="u-logo-seite <?= e(substr($layout[$art]['pos'], 6)) ?>"><?= $logoBild($art) ?></div>
            <?php endif; endforeach; ?>
            <header class="u-kopf">
                <?php if ($e['untertitel'] !== ''): ?><div class="u-untertitel"><?= e($e['untertitel']) ?></div><?php endif; ?>
                <div class="u-turnier"><?= e($turnier['name']) ?></div>
            </header>

            <h1 class="u-titel"><?= te('Urkunde') ?></h1>

            <p class="u-text"><?= te('Wir gratulieren') ?></p>
            <div class="u-name"><?= e($u['name']) ?></div>
            <div class="u-verein"><?= e($u['verein']) ?><?= $u['team'] !== null ? ' · ' . te('Team {name}', ['name' => $u['team']]) : '' ?></div>

            <p class="u-text"><?= te('zum') ?></p>
            <div class="u-platz">
                <span class="u-medaille"><span class="u-ziffer"><?= $u['platz'] ?><span class="u-punkt">.</span></span></span>
                <span class="u-platz-text"><?= te('Platz') ?></span>
            </div>

            <p class="u-text"><?= te('in der Klasse') ?></p>
            <div class="u-klasse"><?= e($u['klasse']['bezeichnung']) ?></div>
            <div class="u-disziplin"><?= e(disziplin_text($u['klasse']['disziplin'])) ?> · <?= te('Gruppe {code}', ['code' => $u['klasse']['code']]) ?></div>

            <footer class="u-fuss">
                <?= $logoZeile('unten') ?>
                <div class="u-ort"><?= e($ortDatum) ?></div>
                <div class="u-unterschriften">
                    <?php foreach (['unterschrift_links', 'unterschrift_rechts'] as $seite): ?>
                        <div><?php if (isset($logos[$seite])): ?><img class="u-unterschrift" src="<?= e($logos[$seite]) ?>" alt=""><?php endif; ?><span><?= e($e[$seite]) ?></span></div>
                    <?php endforeach; ?>
                </div>
            </footer>
        </div>
    </section>
<?php endforeach; ?>
