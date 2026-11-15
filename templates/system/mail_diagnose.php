<?php
/**
 * Ergebnis der SMTP-Verbindungsdiagnose (System → E-Mail-Versand → „Verbindung prüfen“).
 * @var array{schritte: list<array{titel: string, ok: bool|null, text: string}>, protokoll: list<string>, ports: array<int, bool|null>} $ergebnis
 * @var array{host: string, port: int, verschluesselung: string, benutzer: string, absender: string} $einstellung
 */
$this->layout('layout', ['titel' => t('E-Mail-Diagnose'), 'aktiv' => 'system']);
$alleOk = $ergebnis['schritte'] !== [] && !in_array(false, array_column($ergebnis['schritte'], 'ok'), true);
?>
<p class="obertitel"><a href="<?= e(url('system')) ?>#mail"><?= te('Systemeinstellungen') ?> · <?= te('E-Mail-Versand') ?></a></p>
<h1><?= te('Verbindungsdiagnose') ?></h1>
<p class="leise"><?= th('Geprüft: <code>{server}</code>, Verschlüsselung <strong>{verschluesselung}</strong>, Benutzer <code>{benutzer}</code>. Es wurde keine Mail verschickt.', [
    'server' => $einstellung['host'] . ':' . (int) $einstellung['port'],
    'verschluesselung' => ['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', '' => t('keine')][$einstellung['verschluesselung']] ?? $einstellung['verschluesselung'],
    'benutzer' => $einstellung['benutzer'] !== '' ? $einstellung['benutzer'] : '–',
]) ?></p>

<div class="meldung <?= $alleOk ? 'meldung-erfolg' : 'meldung-fehler' ?>">
    <?= $alleOk ? te('Alle Schritte erfolgreich – mit „Testmail an mich senden“ den Versand bis ins Postfach prüfen.') : te('Mindestens ein Schritt ist fehlgeschlagen – der Hinweis beim roten Schritt nennt die wahrscheinliche Ursache.') ?>
</div>

<section class="karte">
    <h2><?= te('Schritte') ?></h2>
    <ol class="diagnose">
        <?php foreach ($ergebnis['schritte'] as $s): ?>
            <li class="<?= $s['ok'] === true ? 'ok' : ($s['ok'] === false ? 'fehler' : 'hinweis') ?>">
                <span class="zeichen" aria-hidden="true"><?= $s['ok'] === true ? '✓' : ($s['ok'] === false ? '✗' : '!') ?></span>
                <div><strong><?= e($s['titel']) ?></strong><br><span class="klein"><?= e($s['text']) ?></span></div>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<?php if ($ergebnis['ports']): ?>
<section class="karte">
    <h2><?= te('Erreichbarkeit der Mail-Ports') ?></h2>
    <p class="hilfe"><?= te('Gegenprobe vom Server aus (nur Verbindungsaufbau). Sind alle gesperrt, blockiert meist der Webhoster ausgehende Verbindungen zu fremden Mailservern.') ?></p>
    <table class="tabelle">
        <thead><tr><th><?= te('Port') ?></th><th><?= te('Üblich für') ?></th><th><?= te('Ergebnis') ?></th></tr></thead>
        <tbody>
        <?php foreach ($ergebnis['ports'] as $port => $offen): ?>
            <tr>
                <td><code><?= (int) $port ?></code><?= $port === $einstellung['port'] ? ' <span class="klein">' . te('(eingestellt)') . '</span>' : '' ?></td>
                <td class="klein"><?= e([465 => 'SSL/TLS', 587 => t('STARTTLS (Standard)'), 25 => t('Server zu Server, oft gesperrt'), 2525 => t('Ausweichport mancher Anbieter')][$port] ?? '') ?></td>
                <td><?= $offen ? '<span class="tag ok">' . te('erreichbar') . '</span>' : '<span class="tag fehler">' . te('keine Verbindung') . '</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<?php if ($ergebnis['protokoll']): ?>
<section class="karte">
    <h2><?= te('SMTP-Protokoll') ?></h2>
    <p class="hilfe"><?= te('Gespräch mit dem Mailserver – Zugangsdaten sind ausgeblendet. Bei Rückfragen an den Mail-Anbieter mitschicken.') ?></p>
    <pre class="protokoll"><?= e(implode("\n", $ergebnis['protokoll'])) ?></pre>
</section>
<?php endif; ?>

<section class="karte">
    <h2><?= te('Häufige Ursachen') ?></h2>
    <ul class="klein">
        <li><?= th('<strong>Keine Verbindung zu keinem Port:</strong> Viele Webhoster sperren ausgehende Verbindungen zu fremden Mailservern. Dann das Postfach beim eigenen Hoster anlegen (z. B. Plesk → E-Mail) und dessen Mailserver eintragen – oft der Servername aus Plesk bzw. <code>localhost</code> mit Port 587 – oder die Freigabe beim Hoster erfragen.') ?></li>
        <li><?= th('<strong>Port erreichbar, aber TLS scheitert:</strong> Port und Verschlüsselung passen nicht zusammen (465 = SSL/TLS, 587 = STARTTLS) oder das Zertifikat passt nicht zum Servernamen – den Namen verwenden, den der Mail-Anbieter angibt.') ?></li>
        <li><?= th('<strong>Anmeldung scheitert (535):</strong> Benutzername (oft die volle Adresse oder eine Kennung wie „e123456_0-…“) bzw. Passwort prüfen; Passwort neu eintragen.') ?></li>
        <li><?= th('<strong>Anmeldung klappt, Testmail scheitert:</strong> Die Absenderadresse muss meist zum Postfach gehören (gleiche Domain bzw. eingerichteter Alias).') ?></li>
    </ul>
    <p class="knopfleiste"><a class="knopf" href="<?= e(url('system')) ?>#mail">← <?= te('Zurück zum E-Mail-Versand') ?></a></p>
</section>
