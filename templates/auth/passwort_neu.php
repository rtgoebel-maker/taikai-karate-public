<?php
/**
 * @var string $token
 * @var int $min Mindestlänge des Passworts
 */
$this->layout('layout_einfach', ['titel' => 'Neues Passwort']);
?>
<p class="obertitel">Konto</p>
<h1>Neues Passwort setzen</h1>
<form method="post" action="<?= e(url('passwort-neu/' . $token)) ?>" class="formular">
    <?= csrf_feld() ?>
    <label>Neues Passwort <span class="hilfe">mindestens <?= (int) $min ?> Zeichen</span>
        <input type="password" name="passwort" required minlength="<?= (int) $min ?>" maxlength="72" autocomplete="new-password" autofocus></label>
    <label>Passwort wiederholen
        <input type="password" name="passwort2" required minlength="<?= (int) $min ?>" maxlength="72" autocomplete="new-password"></label>
    <button type="submit" class="knopf primaer">Passwort speichern</button>
</form>
