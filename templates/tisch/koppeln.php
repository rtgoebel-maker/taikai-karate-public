<?php
/**
 * QR-Code gescannt: Gerät für Tatami und Rolle koppeln.
 * @var array<string, mixed> $tatami
 * @var string $rolle tisch | ausruf
 * @var string $token
 */
use Wettkampf\Durchfuehrung\GeraeteKopplung;

$this->layout('layout_einfach', ['titel' => 'Gerät koppeln']);
$rollenName = GeraeteKopplung::ROLLEN[$rolle];
?>
<p class="obertitel"><?= e($tatami['turnier']) ?></p>
<h1><?= e($tatami['bezeichnung']) ?> · <?= e($rollenName) ?></h1>
<p>Dieses Gerät wird für <strong><?= $rolle === 'ausruf' ? 'den Ausruf (Starter aufrufen)' : 'die Ergebniseingabe am Tisch' ?></strong> gekoppelt.
    Danach gibt die Wettkampfleitung es frei – erst dann geht es los.</p>
<form method="post" action="<?= e(url('tisch/koppeln/' . (int) $tatami['id'] . '/' . $rolle . '/' . $token)) ?>" class="formular">
    <?= csrf_feld() ?>
    <label>Name des Geräts <span class="hilfe">damit die Leitung es erkennt, z. B. „Tablet Tatami 1“ oder Ihr Vorname</span>
        <input name="name" maxlength="60" required autocomplete="off" value="<?= e($rollenName . ' ' . $tatami['bezeichnung']) ?>"></label>
    <button class="knopf primaer" type="submit" style="font-size:1.2rem;padding:.9rem">Kopplung anfragen</button>
</form>
<p class="klein leise">Das Gerät bleibt bis zum Turnierende angemeldet. Nur auf Geräten verwenden, die am Tisch bleiben.</p>
