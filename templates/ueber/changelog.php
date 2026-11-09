<?php
/**
 * Changelog aus CHANGELOG.md (öffentlich). $html stammt aus Kern\Markdown – Text ist dort bereits maskiert.
 * @var string $html
 * @var string $version
 */
$this->layout('layout', ['titel' => 'Changelog', 'aktiv' => 'ueber']);
?>
<p class="obertitel"><a href="<?= e(url('ueber')) ?>">Über diese Software</a> · installiert: <code><?= e($version) ?></code></p>
<article class="karte markdown">
    <?= $html ?>
</article>
