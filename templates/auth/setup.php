<?php $this->layout('layout_einfach', ['titel' => t('Einrichtung')]); ?>
<p class="obertitel"><?= te('Ersteinrichtung') ?></p>
<h1><?= te('Willkommen') ?></h1>
<p><?= th('Lege das erste Konto an – es erhält die <strong>Administration</strong> (alle Turniere, Konten, Vereine). Weitere Konten und Turnierleitungen folgen später unter „Benutzer“.') ?></p>
<form method="post" action="<?= e(url('setup')) ?>" class="formular">
    <?= csrf_feld() ?>
    <?php if ($codeNoetig ?? true): ?>
        <label><?= te('Einrichtungscode') ?> <input name="code" required autocomplete="off" spellcheck="false" autofocus></label>
        <p class="hilfe"><?= th('Steht in der Datei <code>data/einrichtung.token</code> im Installationsordner auf dem Server (per FTP oder Dateimanager des Hosters öffnen). So kann niemand Fremdes eine frisch installierte Plattform übernehmen. Nach der Einrichtung wird die Datei gelöscht.') ?></p>
    <?php endif; ?>
    <div class="zeile">
        <label><?= te('Vorname') ?> <input name="vorname" required maxlength="100" autocomplete="given-name"<?= ($codeNoetig ?? true) ? '' : ' autofocus' ?>></label>
        <label><?= te('Nachname') ?> <input name="nachname" required maxlength="100" autocomplete="family-name"></label>
    </div>
    <label><?= te('E-Mail (Benutzername)') ?> <input type="email" name="email" required maxlength="190" autocomplete="username"></label>
    <label><?= te('Telefon') ?> <span class="hilfe"><?= te('optional') ?></span> <input type="tel" name="telefon" maxlength="50" autocomplete="tel"></label>
    <label><?= te('Passwort (mind. {min} Zeichen)', ['min' => \Wettkampf\Identitaet\Konten::MIN_PASSWORT]) ?> <input type="password" name="passwort" required minlength="<?= \Wettkampf\Identitaet\Konten::MIN_PASSWORT ?>" autocomplete="new-password"></label>
    <label><?= te('Passwort wiederholen') ?> <input type="password" name="passwort2" required minlength="<?= \Wettkampf\Identitaet\Konten::MIN_PASSWORT ?>" autocomplete="new-password"></label>
    <button type="submit" class="knopf primaer"><?= te('Einrichten') ?></button>
</form>
