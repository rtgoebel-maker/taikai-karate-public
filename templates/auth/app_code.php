<?php
/**
 * Zweiter Faktor: Code aus der Authenticator-App (oder Wiederherstellungscode) eingeben.
 * @var string $email verdeckte Adresse
 * @var bool $mailMoeglich Code stattdessen per E-Mail möglich
 * @var int $tage wie lange ein vertrautes Gerät ohne Code auskommt
 */
$this->layout('layout_einfach', ['titel' => t('Anmeldecode')]);
?>
<p class="obertitel"><?= te('Anmelden · Schritt 2 von 2') ?></p>
<h1><?= te('Code aus der App') ?></h1>
<p><?= th('Bitte den 6-stelligen Code aus Ihrer Authenticator-App für <strong>Taikai – Karate</strong> eingeben.') ?></p>
<form method="post" action="<?= e(url('login/code')) ?>" class="formular">
    <?= csrf_feld() ?>
    <label><?= te('Code') ?>
        <input name="code" required autofocus autocomplete="one-time-code" maxlength="10" placeholder="123 456" class="code-eingabe"></label>
    <label class="check"><input type="checkbox" name="vertrauen" value="1"> <?= te('Diesem Gerät {tage} Tage vertrauen', ['tage' => (int) $tage]) ?></label>
    <p class="hilfe"><?= te('Dann fragen wir auf diesem Gerät {tage} Tage lang nicht mehr nach dem Code – das Passwort bleibt nötig. Nicht auf fremden oder gemeinsam genutzten Geräten wählen.', ['tage' => (int) $tage]) ?></p>
    <button type="submit" class="knopf primaer"><?= te('Anmelden') ?></button>
</form>
<details class="klein">
    <summary><?= te('Handy nicht zur Hand?') ?></summary>
    <p><?= th('Einen <strong>Wiederherstellungscode</strong> (z. B. <code>abcd-efgh</code>) oben ins Feld eingeben – jeder gilt nur einmal. Ohne Codes kann die Administration die App Ihres Kontos zurücksetzen.') ?></p>
    <?php if ($mailMoeglich): ?>
        <form method="post" action="<?= e(url('login/code/neu')) ?>" class="knopfleiste">
            <?= csrf_feld() ?>
            <button type="submit" class="knopf klein"><?= te('Stattdessen Code an {email} senden', ['email' => $email]) ?></button>
        </form>
    <?php endif; ?>
</details>
<p class="klein"><a href="<?= e(url('login')) ?>"><?= te('Abbrechen') ?></a></p>
