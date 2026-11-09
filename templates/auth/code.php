<?php
/**
 * Zweiter Faktor: Code aus der E-Mail eingeben.
 * @var string $email verdeckte Adresse, an die der Code ging
 * @var int $minuten Gültigkeit
 * @var int $tage wie lange ein vertrautes Gerät ohne Code auskommt
 */
$this->layout('layout_einfach', ['titel' => 'Anmeldecode']);
?>
<p class="obertitel">Anmelden · Schritt 2 von 2</p>
<h1>Code aus der E-Mail</h1>
<p>Wir haben einen 6-stelligen Code an <strong><?= e($email) ?></strong> geschickt. Er gilt <?= (int) $minuten ?> Minuten.</p>
<form method="post" action="<?= e(url('login/code')) ?>" class="formular">
    <?= csrf_feld() ?>
    <label>Anmeldecode
        <input name="code" required autofocus inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="123 456" class="code-eingabe"></label>
    <label class="check"><input type="checkbox" name="vertrauen" value="1"> Diesem Gerät <?= (int) $tage ?> Tage vertrauen</label>
    <p class="hilfe">Dann fragen wir auf diesem Gerät <?= (int) $tage ?> Tage lang nicht mehr nach dem Code – das Passwort bleibt nötig.
        Nicht auf fremden oder gemeinsam genutzten Geräten wählen.</p>
    <button type="submit" class="knopf primaer">Anmelden</button>
</form>
<form method="post" action="<?= e(url('login/code/neu')) ?>" class="knopfleiste">
    <?= csrf_feld() ?>
    <span class="klein leise">Keine E-Mail bekommen? Auch im Spam-Ordner nachsehen.</span>
    <button type="submit" class="knopf klein">Neuen Code senden</button>
</form>
<p class="klein"><a href="<?= e(url('login')) ?>">Abbrechen</a></p>
