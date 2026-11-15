<?php
/**
 * @var string $token
 * @var int $min Mindestlänge des Passworts
 */
$this->layout('layout_einfach', ['titel' => t('Neues Passwort')]);
?>
<p class="obertitel"><?= te('Konto') ?></p>
<h1><?= te('Neues Passwort setzen') ?></h1>
<form method="post" action="<?= e(url('passwort-neu/' . $token)) ?>" class="formular">
    <?= csrf_feld() ?>
    <label><?= te('Neues Passwort') ?> <span class="hilfe"><?= te('mindestens {min} Zeichen', ['min' => (int) $min]) ?></span>
        <input type="password" name="passwort" required minlength="<?= (int) $min ?>" maxlength="72" autocomplete="new-password" autofocus></label>
    <label><?= te('Passwort wiederholen') ?>
        <input type="password" name="passwort2" required minlength="<?= (int) $min ?>" maxlength="72" autocomplete="new-password"></label>
    <button type="submit" class="knopf primaer"><?= te('Passwort speichern') ?></button>
</form>
