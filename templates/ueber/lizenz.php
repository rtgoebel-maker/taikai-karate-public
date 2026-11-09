<?php
/**
 * Lizenztext (LICENSE) und Urheberhinweis (NOTICE), öffentlich.
 * @var string $text
 * @var string $hinweis
 */
$this->layout('layout', ['titel' => 'Lizenz', 'aktiv' => 'ueber']);
?>
<p class="obertitel"><a href="<?= e(url('ueber')) ?>">Über diese Software</a></p>
<h1>Lizenz</h1>
<?php if ($hinweis !== ''): ?>
    <section class="karte">
        <h2>Urheberhinweis</h2>
        <pre class="lizenztext"><?= e($hinweis) ?></pre>
    </section>
<?php endif; ?>
<section class="karte">
    <h2>GNU Affero General Public License, Version 3</h2>
    <pre class="lizenztext"><?= e($text) ?></pre>
</section>
