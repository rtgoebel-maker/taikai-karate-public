<?php
/**
 * QR-Code gescannt: Gerät für Tatami und Rolle koppeln.
 * @var array<string, mixed> $tatami
 * @var string $rolle tisch | ausruf
 * @var string $token
 */
use Wettkampf\Durchfuehrung\GeraeteKopplung;

$this->layout('layout_einfach', ['titel' => t('Gerät koppeln')]);
$rollenName = t(GeraeteKopplung::ROLLEN[$rolle]); // „Eingabe“ bzw. „Ausruf“ (Katalog lang/en/oberflaeche.php)
?>
<p class="obertitel"><?= e($tatami['turnier']) ?></p>
<h1><?= e($tatami['bezeichnung']) ?> · <?= e($rollenName) ?></h1>
<p><?= th('Dieses Gerät wird für <strong>{zweck}</strong> gekoppelt.', ['zweck' => $rolle === 'ausruf' ? t('den Ausruf (Starter aufrufen)') : t('die Ergebniseingabe am Tisch')]) ?>
    <?= te('Danach gibt die Wettkampfleitung es frei – erst dann geht es los.') ?></p>
<form method="post" action="<?= e(url('tisch/koppeln/' . (int) $tatami['id'] . '/' . $rolle . '/' . $token)) ?>" class="formular">
    <?= csrf_feld() ?>
    <label><?= te('Name des Geräts') ?> <span class="hilfe"><?= te('damit die Leitung es erkennt, z. B. „Tablet Tatami 1“ oder Ihr Vorname') ?></span>
        <input name="name" maxlength="60" required autocomplete="off" value="<?= e($rollenName . ' ' . $tatami['bezeichnung']) ?>"></label>
    <button class="knopf primaer" type="submit" style="font-size:1.2rem;padding:.9rem"><?= te('Kopplung anfragen') ?></button>
</form>
<p class="klein leise"><?= te('Das Gerät bleibt bis zum Turnierende angemeldet. Nur auf Geräten verwenden, die am Tisch bleiben.') ?></p>
