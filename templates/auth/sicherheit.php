<?php
/**
 * Konto & Sicherheit: Authenticator-App, Wiederherstellungscodes, vertraute Geräte.
 * @var string|null $appSeit eingerichtet seit
 * @var int $codesOffen unbenutzte Wiederherstellungscodes
 * @var int $vertraut vertraute Geräte
 * @var bool $pflicht zweiter Faktor für alle Pflicht
 * @var bool $mailMoeglich E-Mail-Versand eingerichtet
 * @var array{qr: string, schluessel: string, ziel: string, imLogin: bool}|null $einrichten
 * @var array<string, mixed> $konto eigene Angaben
 * @var int $min Mindestlänge Passwort
 */
$this->layout('layout', ['titel' => 'Konto & Sicherheit']);
?>
<h1>Konto &amp; Sicherheit</h1>
<p class="leise">Nach dem Passwort fragt Taikai einen zweiten Faktor ab<?= $pflicht ? ' (für alle Konten vorgeschrieben)' : '' ?>:
    einen Code aus der Authenticator-App<?= $mailMoeglich ? ' oder – ohne App – per E-Mail' : '' ?>.</p>

<div class="system-raster">
    <form method="post" action="<?= e(url('konto/sicherheit/angaben')) ?>" class="karte formular">
        <h2>Meine Angaben</h2>
        <?= csrf_feld() ?>
        <div class="zeile">
            <label>Vorname <input name="vorname" required maxlength="100" autocomplete="given-name" value="<?= e((string) ($konto['vorname'] ?? '')) ?>"></label>
            <label>Nachname <input name="nachname" required maxlength="100" autocomplete="family-name" value="<?= e((string) ($konto['nachname'] ?? '')) ?>"></label>
        </div>
        <label>Telefon <span class="hilfe">optional, für Rückfragen der Wettkampfleitung</span>
            <input name="telefon" maxlength="50" autocomplete="tel" value="<?= e((string) ($konto['telefon'] ?? '')) ?>"></label>
        <label>E-Mail-Adresse <span class="hilfe">zugleich Anmeldename</span>
            <input type="email" name="email" required maxlength="190" autocomplete="email" value="<?= e((string) ($konto['email'] ?? '')) ?>"></label>
        <label>Passwort <span class="hilfe">nur nötig, wenn Sie die E-Mail-Adresse ändern</span>
            <input type="password" name="passwort" autocomplete="current-password"></label>
        <?php if (!empty($konto['verein'])): ?>
            <p class="hilfe">Dojo: <strong><?= e((string) $konto['verein']) ?></strong> – ändern kann das die Administration.</p>
        <?php endif; ?>
        <div><button class="knopf primaer" type="submit">Angaben speichern</button></div>
    </form>

    <form method="post" action="<?= e(url('konto/sicherheit/passwort')) ?>" class="karte formular">
        <h2>Passwort ändern</h2>
        <?= csrf_feld() ?>
        <label>Bisheriges Passwort <input type="password" name="passwort" required autocomplete="current-password"></label>
        <label>Neues Passwort (mind. <?= (int) $min ?> Zeichen) <input type="password" name="neu" required minlength="<?= (int) $min ?>" autocomplete="new-password"></label>
        <label>Neues Passwort wiederholen <input type="password" name="neu2" required minlength="<?= (int) $min ?>" autocomplete="new-password"></label>
        <p class="hilfe">Danach fragen auch vertraute Geräte wieder nach dem Code<?= $mailMoeglich ? '; Sie erhalten eine Bestätigung per E-Mail' : '' ?>.</p>
        <div><button class="knopf primaer" type="submit">Passwort ändern</button></div>
    </form>

    <section class="karte">
        <h2>Authenticator-App</h2>
        <?php if ($appSeit !== null): ?>
            <p><span class="tag ok">eingerichtet</span> seit <?= e(datum_de($appSeit)) ?>. Bei der Anmeldung fragen wir den Code aus der App ab.</p>
            <p>Unbenutzte Wiederherstellungscodes: <strong><?= (int) $codesOffen ?></strong><?= $codesOffen <= 3 ? ' – bitte neue erzeugen.' : '' ?></p>
            <form method="post" action="<?= e(url('konto/sicherheit/codes')) ?>" class="formular">
                <?= csrf_feld() ?>
                <label>Passwort zur Bestätigung <input type="password" name="passwort" required autocomplete="current-password"></label>
                <div class="knopfleiste">
                    <button class="knopf" type="submit">Neue Wiederherstellungscodes</button>
                    <button class="knopf gefahr" type="submit" formaction="<?= e(url('konto/sicherheit/app/entfernen')) ?>" data-bestaetigen="Authenticator-App wirklich entfernen?">App entfernen</button>
                </div>
                <p class="hilfe">Neues Handy? App entfernen und danach neu einrichten.</p>
            </form>
        <?php elseif ($einrichten !== null): ?>
            <p>Noch nicht eingerichtet.<?= $pflicht && !$mailMoeglich ? ' Ohne E-Mail-Versand ist sie für die Anmeldung nötig.' : ' Empfohlen – sie funktioniert auch ohne E-Mail und offline.' ?></p>
            <?= $this->teil('auth/app_einrichten', $einrichten) ?>
        <?php endif; ?>
    </section>

    <section class="karte">
        <h2>Vertraute Geräte</h2>
        <?php if ($vertraut > 0): ?>
            <p><?= $vertraut === 1 ? '1 Gerät wird' : $vertraut . ' Geräte werden' ?> noch ohne Code angemeldet („Diesem Gerät 30 Tage vertrauen“).</p>
            <form method="post" action="<?= e(url('konto/sicherheit/vertrauen')) ?>">
                <?= csrf_feld() ?>
                <button class="knopf" type="submit">Allen Geräten nicht mehr vertrauen</button>
            </form>
        <?php else: ?>
            <p class="leise">Keine – bei jeder Anmeldung wird ein Code abgefragt.</p>
        <?php endif; ?>
        <p class="hilfe">Eine Passwortänderung setzt vertraute Geräte ebenfalls zurück.</p>
    </section>
</div>
