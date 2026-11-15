<?php
/** @var string $botschutz versteckte Felder (Kern\BotSchutz) */
$this->layout('layout_einfach', ['titel' => t('Passwort vergessen')]);
?>
<p class="obertitel"><?= te('Konto') ?></p>
<h1><?= te('Passwort vergessen') ?></h1>
<p><?= te('Geben Sie die E-Mail-Adresse Ihres Kontos ein. Sie erhalten einen Link, mit dem Sie ein neues Passwort setzen (60 Minuten gültig). Ist die Adresse noch nicht bestätigt, kommt stattdessen ein neuer Bestätigungslink.') ?></p>
<form method="post" action="<?= e(url('passwort-vergessen')) ?>" class="formular">
    <?= csrf_feld() ?>
    <?= $botschutz ?>
    <label><?= te('E-Mail') ?> <input type="email" name="email" required autofocus autocomplete="username" maxlength="190"></label>
    <button type="submit" class="knopf primaer"><?= te('Link anfordern') ?></button>
</form>
<p class="klein"><a href="<?= e(url('login')) ?>"><?= te('Zurück zur Anmeldung') ?></a></p>
<script src="<?= e(asset('botschutz.js')) ?>" defer></script>
