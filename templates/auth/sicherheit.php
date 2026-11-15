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
$this->layout('layout', ['titel' => t('Konto & Sicherheit')]);
?>
<h1><?= te('Konto & Sicherheit') ?></h1>
<p class="leise"><?= te('Nach dem Passwort fragt Taikai einen zweiten Faktor ab') ?><?= $pflicht ? ' ' . te('(für alle Konten vorgeschrieben)') : '' ?>:
    <?= te('einen Code aus der Authenticator-App') ?><?= $mailMoeglich ? ' ' . te('oder – ohne App – per E-Mail') : '' ?>.</p>

<div class="system-raster">
    <form method="post" action="<?= e(url('konto/sicherheit/angaben')) ?>" class="karte formular">
        <h2><?= te('Meine Angaben') ?></h2>
        <?= csrf_feld() ?>
        <div class="zeile">
            <label><?= te('Vorname') ?> <input name="vorname" required maxlength="100" autocomplete="given-name" value="<?= e((string) ($konto['vorname'] ?? '')) ?>"></label>
            <label><?= te('Nachname') ?> <input name="nachname" required maxlength="100" autocomplete="family-name" value="<?= e((string) ($konto['nachname'] ?? '')) ?>"></label>
        </div>
        <label><?= te('Telefon') ?> <span class="hilfe"><?= te('optional, für Rückfragen der Wettkampfleitung') ?></span>
            <input name="telefon" maxlength="50" autocomplete="tel" value="<?= e((string) ($konto['telefon'] ?? '')) ?>"></label>
        <label><?= te('E-Mail-Adresse') ?> <span class="hilfe"><?= te('zugleich Anmeldename') ?></span>
            <input type="email" name="email" required maxlength="190" autocomplete="email" value="<?= e((string) ($konto['email'] ?? '')) ?>"></label>
        <label><?= te('Passwort') ?> <span class="hilfe"><?= te('nur nötig, wenn Sie die E-Mail-Adresse ändern') ?></span>
            <input type="password" name="passwort" autocomplete="current-password"></label>
        <?php if (!empty($konto['verein'])): ?>
            <p class="hilfe"><?= th('Dojo: <strong>{verein}</strong> – ändern kann das die Administration.', ['verein' => (string) $konto['verein']]) ?></p>
        <?php endif; ?>
        <div><button class="knopf primaer" type="submit"><?= te('Angaben speichern') ?></button></div>
    </form>

    <form method="post" action="<?= e(url('konto/sicherheit/passwort')) ?>" class="karte formular">
        <h2><?= te('Passwort ändern') ?></h2>
        <?= csrf_feld() ?>
        <label><?= te('Bisheriges Passwort') ?> <input type="password" name="passwort" required autocomplete="current-password"></label>
        <label><?= te('Neues Passwort (mind. {min} Zeichen)', ['min' => (int) $min]) ?> <input type="password" name="neu" required minlength="<?= (int) $min ?>" autocomplete="new-password"></label>
        <label><?= te('Neues Passwort wiederholen') ?> <input type="password" name="neu2" required minlength="<?= (int) $min ?>" autocomplete="new-password"></label>
        <p class="hilfe"><?= $mailMoeglich ? te('Danach fragen auch vertraute Geräte wieder nach dem Code; Sie erhalten eine Bestätigung per E-Mail.') : te('Danach fragen auch vertraute Geräte wieder nach dem Code.') ?></p>
        <div><button class="knopf primaer" type="submit"><?= te('Passwort ändern') ?></button></div>
    </form>

    <section class="karte">
        <h2><?= te('Authenticator-App') ?></h2>
        <?php if ($appSeit !== null): ?>
            <p><?= th('<span class="tag ok">eingerichtet</span> seit {datum}. Bei der Anmeldung fragen wir den Code aus der App ab.', ['datum' => datum_de($appSeit)]) ?></p>
            <p><?= te('Unbenutzte Wiederherstellungscodes:') ?> <strong><?= (int) $codesOffen ?></strong><?= $codesOffen <= 3 ? ' – ' . te('bitte neue erzeugen.') : '' ?></p>
            <form method="post" action="<?= e(url('konto/sicherheit/codes')) ?>" class="formular">
                <?= csrf_feld() ?>
                <label><?= te('Passwort zur Bestätigung') ?> <input type="password" name="passwort" required autocomplete="current-password"></label>
                <div class="knopfleiste">
                    <button class="knopf" type="submit"><?= te('Neue Wiederherstellungscodes') ?></button>
                    <button class="knopf gefahr" type="submit" formaction="<?= e(url('konto/sicherheit/app/entfernen')) ?>" data-bestaetigen="<?= te('Authenticator-App wirklich entfernen?') ?>"><?= te('App entfernen') ?></button>
                </div>
                <p class="hilfe"><?= te('Neues Handy? App entfernen und danach neu einrichten.') ?></p>
            </form>
        <?php elseif ($einrichten !== null): ?>
            <p><?= te('Noch nicht eingerichtet.') ?> <?= $pflicht && !$mailMoeglich ? te('Ohne E-Mail-Versand ist sie für die Anmeldung nötig.') : te('Empfohlen – sie funktioniert auch ohne E-Mail und offline.') ?></p>
            <?= $this->teil('auth/app_einrichten', $einrichten) ?>
        <?php endif; ?>
    </section>

    <section class="karte">
        <h2><?= te('Vertraute Geräte') ?></h2>
        <?php if ($vertraut > 0): ?>
            <p><?= $vertraut === 1 ? te('1 Gerät wird noch ohne Code angemeldet („Diesem Gerät 30 Tage vertrauen“).') : te('{n} Geräte werden noch ohne Code angemeldet („Diesem Gerät 30 Tage vertrauen“).', ['n' => $vertraut]) ?></p>
            <form method="post" action="<?= e(url('konto/sicherheit/vertrauen')) ?>">
                <?= csrf_feld() ?>
                <button class="knopf" type="submit"><?= te('Allen Geräten nicht mehr vertrauen') ?></button>
            </form>
        <?php else: ?>
            <p class="leise"><?= te('Keine – bei jeder Anmeldung wird ein Code abgefragt.') ?></p>
        <?php endif; ?>
        <p class="hilfe"><?= te('Eine Passwortänderung setzt vertraute Geräte ebenfalls zurück.') ?></p>
    </section>
</div>
