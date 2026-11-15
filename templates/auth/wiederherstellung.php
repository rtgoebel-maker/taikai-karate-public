<?php
/**
 * Wiederherstellungscodes einmalig anzeigen.
 * @var list<string> $codes
 * @var string $weiter
 */
$this->layout('layout_einfach', ['titel' => t('Wiederherstellungscodes')]);
?>
<p class="obertitel"><?= te('Authenticator-App eingerichtet') ?></p>
<h1><?= te('Wiederherstellungscodes') ?></h1>
<p><?= th('Falls das Handy verloren geht oder die App gelöscht wird, kommen Sie mit einem dieser Codes noch in Ihr Konto – <strong>jeder gilt nur einmal</strong>. Bitte jetzt ausdrucken, abschreiben oder im Passwort-Manager speichern. <strong>Sie werden nur dieses eine Mal angezeigt.</strong>') ?></p>
<ul class="wiederherstellung">
    <?php foreach ($codes as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?>
</ul>
<p class="hilfe"><?= te('Drucken mit Strg+P (Mac: ⌘+P).') ?></p>
<p><a class="knopf primaer" href="<?= e($weiter) ?>"><?= te('Gespeichert – weiter') ?></a></p>
