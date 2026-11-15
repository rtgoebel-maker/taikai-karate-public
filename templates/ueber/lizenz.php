<?php
/**
 * Lizenztext (LICENSE) und Urheberhinweis (NOTICE), öffentlich.
 * @var string $text
 * @var string $hinweis
 */
$this->layout('layout', ['titel' => t('Lizenz'), 'aktiv' => 'ueber']);
?>
<p class="obertitel"><a href="<?= e(url('ueber')) ?>"><?= te('Über diese Software') ?></a></p>
<h1><?= te('Lizenz') ?></h1>
<?php if ($hinweis !== ''): ?>
    <section class="karte">
        <h2><?= te('Urheberhinweis') ?></h2>
        <?php if (sprache() !== 'de'): ?><p><?= te('Der Urheberhinweis (Datei NOTICE) ist auf Deutsch. Kurz gefasst: freie Software unter der AGPL-3.0 (oder einer späteren Version); wer sie weitergibt oder über ein Netzwerk bereitstellt, muss die Nennung „Robert Göbel und Wolfgang Maier“ und die Seite „Über diese Software“ erhalten.') ?></p><?php endif; ?>
        <pre class="lizenztext"><?= e($hinweis) ?></pre>
    </section>
<?php endif; ?>
<section class="karte">
    <h2>GNU Affero General Public License, Version 3</h2>
    <pre class="lizenztext"><?= e($text) ?></pre>
</section>
