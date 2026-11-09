<?php
/**
 * System → E-Mail-Versand: Postfach für den SMTP-Versand (eigenes Formular, Speichern mit Passwortbestätigung).
 * @var array<string, string|int|bool> $mail gespeicherte Werte ohne Passwort (MailEinstellungen::anzeige)
 * @var bool $mailFestgelegt Versand in config.php festgelegt
 * @var bool $adresseFestgelegt öffentliche Adresse in config.php festgelegt
 * @var array{oeffentliche_url: string|null, transport: string|null, host: string, aktiv: bool} $mailWirksam
 * @var array<string, mixed> $warteschlange offen, gescheitert, gesendet, fehler
 * @var list<array<string, mixed>> $mailListe letzte Mails ohne Inhalt
 */
use Wettkampf\Kommunikation\MailEinstellungen;

$sperre = $mailFestgelegt ? ' disabled' : '';
?>
<section class="karte formular" id="mail">
    <h2>E-Mail-Versand</h2>
    <p class="hilfe">Für Bestätigungslinks der Dojo-Zugänge, „Passwort vergessen“ und Mails zum Bearbeitungsstand der Online-Anmeldung.
        Dazu ein Postfach anlegen (z. B. in Plesk unter „E-Mail“) und hier dessen Zugangsdaten eintragen. Auf dem Hallen-Laptop aus lassen – Online verschickt.</p>

    <p><?php if ($mailWirksam['aktiv']): ?>
        <span class="tag ok">aktiv</span> Versand über <?= e($mailWirksam['transport'] === 'datei' ? 'Ablage in data/mail/ (Test)' : $mailWirksam['host']) ?>,
        Links auf <?= e((string) $mailWirksam['oeffentliche_url']) ?>
    <?php else: ?>
        <span class="tag">aus</span> Ohne Versand gibt es keine Dojo-Zugänge zum Selbstanlegen und kein „Passwort vergessen“; alles andere funktioniert.
    <?php endif; ?>
    <?php if ((int) ($warteschlange['offen'] ?? 0) > 0 || (int) ($warteschlange['gescheitert'] ?? 0) > 0): ?>
        <br><span class="klein">Warteschlange: <?= (int) $warteschlange['offen'] ?> wartend, <?= (int) $warteschlange['gescheitert'] ?> endgültig gescheitert
            <?= !empty($warteschlange['fehler']) ? '· letzter Fehler: ' . e(mb_substr((string) $warteschlange['fehler'], 0, 160)) : '' ?></span>
    <?php endif; ?></p>

    <?php if ($mailFestgelegt): ?>
        <div class="meldung meldung-hinweis">Der Versand ist in <code>config/config.php</code> festgelegt (Betreiber) und hier nur sichtbar.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('system/mail')) ?>">
        <?= csrf_feld() ?>
        <label>Öffentliche Adresse dieser Installation <span class="hilfe">daraus entstehen die Links in den Mails, z. B. https://turnier.example.de</span>
            <span class="zeile-knopf">
                <input name="url" value="<?= e($adresseFestgelegt ? (string) $mailWirksam['oeffentliche_url'] : (string) $mail['url']) ?>" maxlength="300" placeholder="https://…" inputmode="url"<?= $adresseFestgelegt ? ' disabled' : '' ?>>
                <?php if (!$adresseFestgelegt): ?><button type="button" class="knopf" data-adresse-uebernehmen="<?= e(url('')) ?>">Adresse dieser Seite übernehmen</button><?php endif; ?>
            </span></label>
        <div class="zeile">
            <label>Versand
                <select name="transport"<?= $sperre ?>>
                    <?php foreach (MailEinstellungen::TRANSPORTE as $wert => $text): ?><option value="<?= e($wert) ?>"<?= sel($wert, $mail['transport']) ?>><?= e($text) ?></option><?php endforeach; ?>
                </select></label>
            <label>SMTP-Server <input name="host" value="<?= e((string) $mail['host']) ?>" maxlength="200" placeholder="smtp.example.de" autocomplete="off"<?= $sperre ?>></label>
        </div>
        <div class="zeile">
            <label>Port
                <select name="port"<?= $sperre ?>>
                    <?php foreach (MailEinstellungen::PORTS as $wert => $text): ?><option value="<?= $wert ?>"<?= sel((string) $wert, (string) $mail['port']) ?>><?= e($text) ?></option><?php endforeach; ?>
                </select></label>
            <label>Verschlüsselung
                <select name="verschluesselung"<?= $sperre ?>>
                    <?php foreach (MailEinstellungen::VERSCHLUESSELUNG as $wert => $text): ?><option value="<?= e($wert) ?>"<?= sel($wert, $mail['verschluesselung']) ?>><?= e($text) ?></option><?php endforeach; ?>
                </select></label>
        </div>
        <div class="zeile">
            <label>Benutzername <span class="hilfe">meist die Adresse des Postfachs</span>
                <input name="benutzer" value="<?= e((string) $mail['benutzer']) ?>" maxlength="200" autocomplete="off"<?= $sperre ?>></label>
            <label>Passwort des Postfachs
                <span class="hilfe"><?= $mail['passwort_unlesbar'] ? 'gespeichertes Passwort nicht mehr lesbar (Schlüssel geändert) – bitte neu eingeben' : ($mail['passwort_gesetzt'] ? 'gespeichert (verschlüsselt) – leer lassen = unverändert' : 'noch keines gespeichert') ?></span>
                <input type="password" name="passwort" maxlength="500" autocomplete="new-password"<?= $sperre ?>></label>
        </div>
        <div class="zeile">
            <label>Absenderadresse <input type="email" name="absender" value="<?= e((string) $mail['absender']) ?>" maxlength="190"<?= $sperre ?>></label>
            <label>Absendername <input name="absender_name" value="<?= e((string) $mail['absender_name']) ?>" maxlength="100"<?= $sperre ?>></label>
        </div>
        <div class="zeile">
            <label>Antworten an <span class="hilfe">optional, z. B. Adresse der Wettkampfleitung</span>
                <input type="email" name="antwort_an" value="<?= e((string) $mail['antwort_an']) ?>" maxlength="190"<?= $sperre ?>></label>
        </div>
        <?php if (!$mailFestgelegt && $mail['passwort_gesetzt']): ?>
            <label class="check"><input type="checkbox" name="passwort_loeschen" value="1"> gespeichertes Passwort löschen</label>
        <?php endif; ?>
        <?php if (!($mailFestgelegt && $adresseFestgelegt)): ?>
            <label>Ihr Passwort zur Bestätigung <span class="hilfe">Schutz, falls jemand eine offene Sitzung nutzt</span>
                <input type="password" name="bestaetigung" required autocomplete="current-password" style="max-width:340px"></label>
            <p class="hilfe">Das Postfach-Passwort wird verschlüsselt gespeichert (Schlüssel in <code>data/</code>, nicht in der Datenbank) und nie wieder angezeigt.
                Jede Änderung steht im Verlauf.</p>
            <p class="knopfleiste"><button class="knopf primaer" type="submit">E-Mail-Versand speichern</button></p>
        <?php endif; ?>
    </form>
    <div class="knopfleiste mail-werkzeuge">
        <form method="post" action="<?= e(url('system/mail/test')) ?>">
            <?= csrf_feld() ?>
            <button class="knopf" type="submit"<?= $mailWirksam['transport'] === null ? ' disabled' : '' ?>>Testmail an mich senden</button>
        </form>
        <form method="post" action="<?= e(url('system/mail/diagnose')) ?>">
            <?= csrf_feld() ?>
            <button class="knopf" type="submit"<?= $mailWirksam['transport'] !== 'smtp' ? ' disabled' : '' ?> title="Prüft DNS, Ports, TLS und Anmeldung Schritt für Schritt – ohne Mail zu verschicken">🔍 Verbindung prüfen</button>
        </form>
    </div>

    <?php if ($mailListe): ?>
    <details class="mail-warteschlange"<?= (int) ($warteschlange['offen'] ?? 0) + (int) ($warteschlange['gescheitert'] ?? 0) > 0 ? ' open' : '' ?>>
        <summary>Letzte Mails und Warteschlange</summary>
        <div class="tabelle-scroll">
        <table class="tabelle">
            <thead><tr><th>Erstellt</th><th>An</th><th>Betreff</th><th>Stand</th></tr></thead>
            <tbody>
            <?php foreach ($mailListe as $m): ?>
                <tr>
                    <td class="klein"><?= e(date('d.m. H:i', (int) strtotime((string) $m['erstellt_am']))) ?></td>
                    <td class="klein"><?= e((string) $m['empfaenger']) ?></td>
                    <td class="klein"><?= e((string) $m['betreff']) ?></td>
                    <td class="klein"><?php if ($m['gesendet_am']): ?><span class="tag ok">verschickt</span>
                        <?php elseif ((int) $m['versuche'] >= 5): ?><span class="tag fehler">gescheitert</span>
                        <?php else: ?><span class="tag warn">wartet<?= (int) $m['versuche'] > 0 ? ' · ' . (int) $m['versuche'] . ' Versuche' : '' ?></span><?php endif; ?>
                        <?= $m['fehler'] ? '<br>' . e(mb_substr((string) $m['fehler'], 0, 140)) : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ((int) ($warteschlange['offen'] ?? 0) + (int) ($warteschlange['gescheitert'] ?? 0) > 0): ?>
            <form method="post" action="<?= e(url('system/mail/warteschlange')) ?>" class="knopfleiste">
                <?= csrf_feld() ?>
                <button class="knopf" type="submit" name="aktion" value="wiederholen">Nicht verschickte jetzt erneut versuchen</button>
                <button class="knopf" type="submit" name="aktion" value="verwerfen" data-bestaetigen="Alle nicht verschickten Mails verwerfen?">Nicht verschickte verwerfen</button>
            </form>
        <?php endif; ?>
    </details>
    <?php endif; ?>
</section>
