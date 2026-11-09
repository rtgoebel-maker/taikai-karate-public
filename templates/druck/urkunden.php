<?php
/**
 * Urkunden (A4 hoch, eine Seite je Urkunde). Mit $vordruck nur der Text –
 * an derselben Stelle, für vorgedrucktes Urkundenpapier.
 * @var array<string, mixed> $turnier
 * @var list<array{klasse: array<string, mixed>, platz: int, name: string, verein: string, team: string|null}> $urkunden
 * @var bool $vordruck
 * @var array{untertitel: string, unterschrift_links: string, unterschrift_rechts: string} $e
 * @var array<string, string> $logos Art (verband, verein, dojo, unterschrift_*, hintergrund) → Bild-URL
 */
$ortDatum = trim(($turnier['ort'] ? $turnier['ort'] . ', ' : '') . 'den ' . datum_de($turnier['datum']));
?>
<?php if (!$urkunden): ?>
    <div class="blatt"><p>Noch keine bestätigten Platzierungen. Urkunden gibt es, sobald eine Klasse bestätigt ist (Klasse → „Platzierungen bestätigen“).</p></div>
<?php endif; ?>
<?php foreach ($urkunden as $u): $medaille = $u['platz'] === 1 ? 'gold' : ($u['platz'] === 2 ? 'silber' : 'bronze'); ?>
    <section class="blatt urkunde <?= $medaille ?><?= $vordruck ? ' vordruck' : '' ?><?= isset($logos['hintergrund']) && !$vordruck ? ' mit-hintergrund' : '' ?>">
        <?php if (isset($logos['hintergrund']) && !$vordruck): ?><img class="u-hintergrund" src="<?= e($logos['hintergrund']) ?>" alt=""><?php endif; ?>
        <div class="u-rahmen">
            <div class="u-wasserzeichen" aria-hidden="true">空手</div>
            <?php if (array_intersect_key($logos, ['verband' => 1, 'verein' => 1, 'dojo' => 1]) !== []): ?>
                <div class="u-logos">
                    <div class="u-logo links"><?php if (isset($logos['verein'])): ?><img src="<?= e($logos['verein']) ?>" alt="Vereinslogo"><?php endif; ?></div>
                    <div class="u-logo mitte"><?php if (isset($logos['verband'])): ?><img src="<?= e($logos['verband']) ?>" alt="Verbandslogo"><?php endif; ?></div>
                    <div class="u-logo rechts"><?php if (isset($logos['dojo'])): ?><img src="<?= e($logos['dojo']) ?>" alt="Dojo-Logo"><?php endif; ?></div>
                </div>
            <?php endif; ?>
            <header class="u-kopf">
                <?php if ($e['untertitel'] !== ''): ?><div class="u-untertitel"><?= e($e['untertitel']) ?></div><?php endif; ?>
                <div class="u-turnier"><?= e($turnier['name']) ?></div>
            </header>

            <h1 class="u-titel">Urkunde</h1>

            <p class="u-text">Wir gratulieren</p>
            <div class="u-name"><?= e($u['name']) ?></div>
            <div class="u-verein"><?= e($u['verein']) ?><?= $u['team'] !== null ? ' · Team ' . e($u['team']) : '' ?></div>

            <p class="u-text">zum</p>
            <div class="u-platz">
                <span class="u-medaille"><span class="u-ziffer"><?= $u['platz'] ?><span class="u-punkt">.</span></span></span>
                <span class="u-platz-text">Platz</span>
            </div>

            <p class="u-text">in der Klasse</p>
            <div class="u-klasse"><?= e($u['klasse']['bezeichnung']) ?></div>
            <div class="u-disziplin"><?= e(disziplin_text($u['klasse']['disziplin'])) ?> · Gruppe <?= e($u['klasse']['code']) ?></div>

            <footer class="u-fuss">
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
