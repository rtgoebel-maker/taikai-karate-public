<?php
/**
 * Zweiter Faktor: Code aus der E-Mail eingeben.
 * @var string $email verdeckte Adresse, an die der Code ging
 * @var int $minuten Gültigkeit
 * @var int $tage wie lange ein vertrautes Gerät ohne Code auskommt
 */
$this->layout('layout_einfach', ['titel' => t('Anmeldecode')]);
?>
<p class="obertitel"><?= te('Anmelden · Schritt 2 von 2') ?></p>
<h1><?= te('Code aus der E-Mail') ?></h1>
<p><?= th('Wir haben einen 6-stelligen Code an <strong>{email}</strong> geschickt. Er gilt {minuten} Minuten.', ['email' => $email, 'minuten' => (int) $minuten]) ?></p>
<form method="post" action="<?= e(url('login/code')) ?>" class="formular">
    <?= csrf_feld() ?>
    <label><?= te('Anmeldecode') ?>
        <input name="code" required autofocus inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="123 456" class="code-eingabe"></label>
    <label class="check"><input type="checkbox" name="vertrauen" value="1"> <?= te('Diesem Gerät {tage} Tage vertrauen', ['tage' => (int) $tage]) ?></label>
    <p class="hilfe"><?= te('Dann fragen wir auf diesem Gerät {tage} Tage lang nicht mehr nach dem Code – das Passwort bleibt nötig. Nicht auf fremden oder gemeinsam genutzten Geräten wählen.', ['tage' => (int) $tage]) ?></p>
    <button type="submit" class="knopf primaer"><?= te('Anmelden') ?></button>
</form>
<form method="post" action="<?= e(url('login/code/neu')) ?>" class="knopfleiste">
    <?= csrf_feld() ?>
    <span class="klein leise"><?= te('Keine E-Mail bekommen? Auch im Spam-Ordner nachsehen.') ?></span>
    <button type="submit" class="knopf klein"><?= te('Neuen Code senden') ?></button>
</form>
<p class="klein"><a href="<?= e(url('login')) ?>"><?= te('Abbrechen') ?></a></p>
