<?php
/**
 * Authenticator-App einrichten – bei der Anmeldung (Pflicht ohne E-Mail) oder unter „Konto & Sicherheit“.
 * @var string $qr SVG (vom Server erzeugt)
 * @var string $schluessel Geheimnis zum Abtippen, in Vierergruppen
 * @var string $ziel Formular-Ziel
 * @var bool $imLogin Teil der Anmeldung (schlichtes Layout)
 */
if ($imLogin) {
    $this->layout('layout_einfach', ['titel' => 'Authenticator-App einrichten']);
}
?>
<?php if ($imLogin): ?>
    <p class="obertitel">Anmelden · Schritt 2 von 2</p>
    <h1>Authenticator-App einrichten</h1>
    <p>Für die Anmeldung ist ein zweiter Faktor vorgeschrieben. Einmal einrichten – danach fragen wir nach dem Passwort einen Code aus der App ab.</p>
<?php endif; ?>
<ol class="einrichten-schritte">
    <li>Eine Authenticator-App installieren, falls noch keine vorhanden ist – z. B. <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong>,
        <strong>Aegis</strong> (Android) oder <strong>2FAS</strong>. Sie funktioniert auch ohne Internet.</li>
    <li>In der App „Konto hinzufügen“ bzw. „+“ wählen und diesen QR-Code scannen:
        <div class="app-qr"><?= $qr /* vom Server erzeugtes SVG */ ?></div>
        <span class="hilfe">Scannen nicht möglich? Schlüssel von Hand eingeben (zeitbasiert): <code class="schluessel"><?= e($schluessel) ?></code></span></li>
    <li>Den 6-stelligen Code aus der App eingeben:</li>
</ol>
<form method="post" action="<?= e($ziel) ?>" class="formular">
    <?= csrf_feld() ?>
    <label>Code aus der App
        <input name="code" required inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="123 456" class="code-eingabe"></label>
    <button type="submit" class="knopf primaer">Bestätigen<?= $imLogin ? ' und anmelden' : '' ?></button>
</form>
<?php if ($imLogin): ?>
    <p class="klein"><a href="<?= e(url('login')) ?>">Abbrechen</a></p>
<?php endif; ?>
