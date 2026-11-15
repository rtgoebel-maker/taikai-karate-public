<?php
/**
 * Changelog aus CHANGELOG.md (öffentlich). $html stammt aus Kern\Markdown – Text ist dort bereits maskiert.
 * @var string $html
 * @var string $version
 */
$this->layout('layout', ['titel' => t('Changelog'), 'aktiv' => 'ueber']);
?>
<p class="obertitel"><a href="<?= e(url('ueber')) ?>"><?= te('Über diese Software') ?></a> · <?= te('installiert:') ?> <code><?= e($version) ?></code></p>
<?php if (sprache() !== 'de'): ?><div class="meldung meldung-hinweis"><?= te('Der Changelog ist nur auf Deutsch verfügbar.') ?></div><?php endif; ?>
<article class="karte markdown">
    <?= $html ?>
</article>
