<?php $this->layout('layout_einfach', ['titel' => 'Einrichtung']); ?>
<p class="obertitel">Ersteinrichtung</p>
<h1>Willkommen</h1>
<p>Lege das erste Konto an – es erhält die <strong>Administration</strong> (alle Turniere, Konten, Vereine). Weitere Konten und Turnierleitungen folgen später unter „Benutzer“.</p>
<form method="post" action="<?= e(url('setup')) ?>" class="formular">
    <?= csrf_feld() ?>
    <?php if ($codeNoetig ?? true): ?>
        <label>Einrichtungscode <input name="code" required autocomplete="off" spellcheck="false" autofocus></label>
        <p class="hilfe">Steht in der Datei <code>data/einrichtung.token</code> im Installationsordner auf dem Server
            (per FTP oder Dateimanager des Hosters öffnen). So kann niemand Fremdes eine frisch installierte Plattform übernehmen.
            Nach der Einrichtung wird die Datei gelöscht.</p>
    <?php endif; ?>
    <div class="zeile">
        <label>Vorname <input name="vorname" required maxlength="100" autocomplete="given-name"<?= ($codeNoetig ?? true) ? '' : ' autofocus' ?>></label>
        <label>Nachname <input name="nachname" required maxlength="100" autocomplete="family-name"></label>
    </div>
    <label>E-Mail (Benutzername) <input type="email" name="email" required maxlength="190" autocomplete="username"></label>
    <label>Telefon <span class="hilfe">optional</span> <input type="tel" name="telefon" maxlength="50" autocomplete="tel"></label>
    <label>Passwort (mind. <?= \Wettkampf\Identitaet\Konten::MIN_PASSWORT ?> Zeichen) <input type="password" name="passwort" required minlength="<?= \Wettkampf\Identitaet\Konten::MIN_PASSWORT ?>" autocomplete="new-password"></label>
    <label>Passwort wiederholen <input type="password" name="passwort2" required minlength="<?= \Wettkampf\Identitaet\Konten::MIN_PASSWORT ?>" autocomplete="new-password"></label>
    <button type="submit" class="knopf primaer">Einrichten</button>
</form>
