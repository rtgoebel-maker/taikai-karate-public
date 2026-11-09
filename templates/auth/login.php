<?php
/**
 * @var string $zurueck
 * @var string $botschutz versteckte Felder (Kern\BotSchutz)
 * @var bool $mailAktiv „Passwort vergessen“ nur mit E-Mail-Versand
 */
$this->layout('layout_einfach', ['titel' => 'Anmelden']);
?>
<p class="obertitel">Turnierleitung, Administration und Coaches</p>
<h1>Anmelden</h1>
<form method="post" action="<?= e(url('login')) ?>" class="formular">
    <?= csrf_feld() ?>
    <input type="hidden" name="zurueck" value="<?= e($zurueck) ?>">
    <?= $botschutz ?>
    <label>E-Mail <input name="login" required autofocus autocomplete="username" inputmode="email"></label>
    <label>Passwort <input type="password" name="passwort" required autocomplete="current-password"></label>
    <button type="submit" class="knopf primaer">Anmelden</button>
</form>
<?php if ($mailAktiv): ?><p class="klein"><a href="<?= e(url('passwort-vergessen')) ?>">Passwort vergessen?</a> · E-Mail-Adresse noch nicht bestätigt? Dort gibt es einen neuen Link.</p><?php endif; ?>
<p class="klein">Tischbesatzung? <a href="<?= e(url('tisch')) ?>">Zur Tatami-Anmeldung</a></p>
<script src="<?= e(asset('botschutz.js')) ?>" defer></script>
