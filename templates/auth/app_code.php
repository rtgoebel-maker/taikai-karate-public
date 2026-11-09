<?php
/**
 * Zweiter Faktor: Code aus der Authenticator-App (oder Wiederherstellungscode) eingeben.
 * @var string $email verdeckte Adresse
 * @var bool $mailMoeglich Code stattdessen per E-Mail möglich
 * @var int $tage wie lange ein vertrautes Gerät ohne Code auskommt
 */
$this->layout('layout_einfach', ['titel' => 'Anmeldecode']);
?>
<p class="obertitel">Anmelden · Schritt 2 von 2</p>
<h1>Code aus der App</h1>
<p>Bitte den 6-stelligen Code aus Ihrer Authenticator-App für <strong>Taikai – Karate</strong> eingeben.</p>
<form method="post" action="<?= e(url('login/code')) ?>" class="formular">
    <?= csrf_feld() ?>
    <label>Code
        <input name="code" required autofocus autocomplete="one-time-code" maxlength="10" placeholder="123 456" class="code-eingabe"></label>
    <label class="check"><input type="checkbox" name="vertrauen" value="1"> Diesem Gerät <?= (int) $tage ?> Tage vertrauen</label>
    <p class="hilfe">Dann fragen wir auf diesem Gerät <?= (int) $tage ?> Tage lang nicht mehr nach dem Code – das Passwort bleibt nötig.
        Nicht auf fremden oder gemeinsam genutzten Geräten wählen.</p>
    <button type="submit" class="knopf primaer">Anmelden</button>
</form>
<details class="klein">
    <summary>Handy nicht zur Hand?</summary>
    <p>Einen <strong>Wiederherstellungscode</strong> (z. B. <code>abcd-efgh</code>) oben ins Feld eingeben – jeder gilt nur einmal.
        Ohne Codes kann die Administration die App Ihres Kontos zurücksetzen.</p>
    <?php if ($mailMoeglich): ?>
        <form method="post" action="<?= e(url('login/code/neu')) ?>" class="knopfleiste">
            <?= csrf_feld() ?>
            <button type="submit" class="knopf klein">Stattdessen Code an <?= e($email) ?> senden</button>
        </form>
    <?php endif; ?>
</details>
<p class="klein"><a href="<?= e(url('login')) ?>">Abbrechen</a></p>
