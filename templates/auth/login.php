<?php
/**
 * @var string $zurueck
 * @var string $botschutz versteckte Felder (Kern\BotSchutz)
 * @var bool $mailAktiv „Passwort vergessen“ nur mit E-Mail-Versand
 */
$this->layout('layout_einfach', ['titel' => t('Anmelden')]);
?>
<p class="obertitel"><?= te('Turnierleitung, Administration und Coaches') ?></p>
<h1><?= te('Anmelden') ?></h1>
<form method="post" action="<?= e(url('login')) ?>" class="formular">
    <?= csrf_feld() ?>
    <input type="hidden" name="zurueck" value="<?= e($zurueck) ?>">
    <?= $botschutz ?>
    <label><?= te('E-Mail') ?> <input name="login" required autofocus autocomplete="username" inputmode="email"></label>
    <label><?= te('Passwort') ?> <input type="password" name="passwort" required autocomplete="current-password"></label>
    <button type="submit" class="knopf primaer"><?= te('Anmelden') ?></button>
</form>
<?php if ($mailAktiv): ?><p class="klein"><a href="<?= e(url('passwort-vergessen')) ?>"><?= te('Passwort vergessen?') ?></a> · <?= te('E-Mail-Adresse noch nicht bestätigt? Dort gibt es einen neuen Link.') ?></p><?php endif; ?>
<p class="klein"><?= te('Tischbesatzung?') ?> <a href="<?= e(url('tisch')) ?>"><?= te('Zur Tatami-Anmeldung') ?></a></p>
<script src="<?= e(asset('botschutz.js')) ?>" defer></script>
