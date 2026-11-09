<?php
/**
 * Ergebnis der SMTP-Verbindungsdiagnose (System → E-Mail-Versand → „Verbindung prüfen“).
 * @var array{schritte: list<array{titel: string, ok: bool|null, text: string}>, protokoll: list<string>, ports: array<int, bool|null>} $ergebnis
 * @var array{host: string, port: int, verschluesselung: string, benutzer: string, absender: string} $einstellung
 */
$this->layout('layout', ['titel' => 'E-Mail-Diagnose', 'aktiv' => 'system']);
$alleOk = $ergebnis['schritte'] !== [] && !in_array(false, array_column($ergebnis['schritte'], 'ok'), true);
?>
<p class="obertitel"><a href="<?= e(url('system')) ?>#mail">Systemeinstellungen · E-Mail-Versand</a></p>
<h1>Verbindungsdiagnose</h1>
<p class="leise">Geprüft: <code><?= e($einstellung['host']) ?>:<?= (int) $einstellung['port'] ?></code>, Verschlüsselung
    <strong><?= e(['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', '' => 'keine'][$einstellung['verschluesselung']] ?? $einstellung['verschluesselung']) ?></strong>,
    Benutzer <code><?= e($einstellung['benutzer'] !== '' ? $einstellung['benutzer'] : '–') ?></code>. Es wurde keine Mail verschickt.</p>

<div class="meldung <?= $alleOk ? 'meldung-erfolg' : 'meldung-fehler' ?>">
    <?= $alleOk ? 'Alle Schritte erfolgreich – mit „Testmail an mich senden“ den Versand bis ins Postfach prüfen.' : 'Mindestens ein Schritt ist fehlgeschlagen – der Hinweis beim roten Schritt nennt die wahrscheinliche Ursache.' ?>
</div>

<section class="karte">
    <h2>Schritte</h2>
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
    <h2>Erreichbarkeit der Mail-Ports</h2>
    <p class="hilfe">Gegenprobe vom Server aus (nur Verbindungsaufbau). Sind alle gesperrt, blockiert meist der Webhoster ausgehende Verbindungen zu fremden Mailservern.</p>
    <table class="tabelle">
        <thead><tr><th>Port</th><th>Üblich für</th><th>Ergebnis</th></tr></thead>
        <tbody>
        <?php foreach ($ergebnis['ports'] as $port => $offen): ?>
            <tr>
                <td><code><?= (int) $port ?></code><?= $port === $einstellung['port'] ? ' <span class="klein">(eingestellt)</span>' : '' ?></td>
                <td class="klein"><?= e([465 => 'SSL/TLS', 587 => 'STARTTLS (Standard)', 25 => 'Server zu Server, oft gesperrt', 2525 => 'Ausweichport mancher Anbieter'][$port] ?? '') ?></td>
                <td><?= $offen ? '<span class="tag ok">erreichbar</span>' : '<span class="tag fehler">keine Verbindung</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<?php if ($ergebnis['protokoll']): ?>
<section class="karte">
    <h2>SMTP-Protokoll</h2>
    <p class="hilfe">Gespräch mit dem Mailserver – Zugangsdaten sind ausgeblendet. Bei Rückfragen an den Mail-Anbieter mitschicken.</p>
    <pre class="protokoll"><?= e(implode("\n", $ergebnis['protokoll'])) ?></pre>
</section>
<?php endif; ?>

<section class="karte">
    <h2>Häufige Ursachen</h2>
    <ul class="klein">
        <li><strong>Keine Verbindung zu keinem Port:</strong> Viele Webhoster sperren ausgehende Verbindungen zu fremden Mailservern. Dann das Postfach beim eigenen Hoster anlegen (z. B. Plesk → E-Mail) und dessen Mailserver eintragen – oft der Servername aus Plesk bzw. <code>localhost</code> mit Port 587 – oder die Freigabe beim Hoster erfragen.</li>
        <li><strong>Port erreichbar, aber TLS scheitert:</strong> Port und Verschlüsselung passen nicht zusammen (465 = SSL/TLS, 587 = STARTTLS) oder das Zertifikat passt nicht zum Servernamen – den Namen verwenden, den der Mail-Anbieter angibt.</li>
        <li><strong>Anmeldung scheitert (535):</strong> Benutzername (oft die volle Adresse oder eine Kennung wie „e123456_0-…“) bzw. Passwort prüfen; Passwort neu eintragen.</li>
        <li><strong>Anmeldung klappt, Testmail scheitert:</strong> Die Absenderadresse muss meist zum Postfach gehören (gleiche Domain bzw. eingerichteter Alias).</li>
    </ul>
    <p class="knopfleiste"><a class="knopf" href="<?= e(url('system')) ?>#mail">← Zurück zum E-Mail-Versand</a></p>
</section>
