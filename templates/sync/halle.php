<?php
/**
 * Hallen-Laptop: eine Anlaufstelle für Übergabe (Online) und Übernahme/Rückgabe (Laptop).
 * @var list<array{id: int, name: string, datum: string, status: string, art: string, aktion: string, auftrag: bool}> $uebersicht
 * @var list<array<string, mixed>> $turniere übernommene Turniere (Laptop) mit Übertragungsstand
 * @var string $instanzId
 * @var string $version Programmversion dieses Rechners
 */
$this->layout('layout', ['titel' => 'Hallen-Laptop', 'aktiv' => 'halle']);
$stand = [];
foreach ($turniere as $t) {
    if (!empty($t['stand']) && hash_equals($instanzId, (string) $t['fuehrung_instanz'])) {
        $stand[(int) $t['id']] = $t['stand'];
    }
}
?>
<h1>Hallen-Laptop</h1>
<p class="leise">Ohne verlässliches Internet in der Halle führt ein Laptop das Turnier: Tablets und Ausruf arbeiten im Hallen-WLAN mit dem Laptop,
    online bleibt das Turnier sichtbar, aber nur lesend. Es schreibt immer nur eine Stelle.</p>

<ol class="halle-ablauf">
    <li><span class="wo">Online</span><strong>Übergeben</strong><span>Turnier unten mit „An die Halle übergeben …“ (oder Turnier → Einstellungen). Es entsteht ein Übergabe-Code – notieren.</span></li>
    <li><span class="wo">Laptop</span><strong>Übernehmen</strong><span>Hier unten „Turnier von Online übernehmen“: per Datei (ohne Internet) oder online abholen, mit dem Code.</span></li>
    <li><span class="wo">Laptop</span><strong>Zurückgeben</strong><span>Nach dem Turnier „An Online zurückgeben …“ – danach führt Online wieder.</span></li>
</ol>

<section class="karte">
    <h2>Turniere dieser Installation</h2>
    <?php if (!$uebersicht): ?>
        <p class="leise">Keine aktuellen Turniere.</p>
    <?php else: ?>
        <div class="tabelle-scroll">
        <table class="tabelle">
            <thead><tr><th>Turnier</th><th>Hallen-Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($uebersicht as $i => $t): ?>
                <?php if ($i === 8): ?>
            </tbody>
            <tbody class="halle-weitere" id="halle-weitere" hidden>
                <?php endif; ?>
                <tr>
                    <td><a href="<?= e(url("turnier/{$t['id']}")) ?>"><?= e($t['name']) ?></a><br><span class="klein leise"><?= e(datum_de($t['datum'])) ?></span></td>
                    <td><span class="tag <?= e($t['art']) ?>"><?= e($t['status']) ?></span>
                        <?php if (isset($stand[$t['id']])): ?><br><?= $this->teil('sync/stand', ['stand' => $stand[$t['id']]]) ?><?php endif; ?></td>
                    <td class="rechts"><a class="knopf klein" href="<?= e(url("turnier/{$t['id']}/halle")) ?>"><?= e($t['aktion']) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if (count($uebersicht) > 8): ?>
            <p><button type="button" class="knopf klein" data-einblenden="halle-weitere"><?= count($uebersicht) - 8 === 1 ? 'Ein weiteres Turnier anzeigen' : 'Weitere ' . (count($uebersicht) - 8) . ' Turniere anzeigen' ?></button></p>
        <?php endif; ?>
    <?php endif; ?>
    <p class="hilfe">Übergeben kann auch die Turnierleitung eines Turniers (ohne Administrationsrechte) unter Turnier → Einstellungen → Hallen-Laptop.</p>
</section>

<h2 class="halle-abschnitt">Turnier von Online übernehmen <span class="klein leise">– auf dem Hallen-Laptop</span></h2>
<p class="klein">Programmversion dieses Rechners: <code><?= e($version) ?></code> – vor dem Turnier aktualisieren (Linux: <code>./update-halle.sh</code>, sonst neues Paket; siehe docs/HALLE-LINUX.md). Laptop und Online müssen zueinander passen.</p>
<div class="zwei-spalten">
    <form method="post" action="<?= e(url('halle/datei')) ?>" enctype="multipart/form-data" class="karte formular">
        <?= csrf_feld() ?>
        <h2>Per Datei</h2>
        <p class="hilfe">Übergabe-Datei (online heruntergeladen, z. B. per USB-Stick) und Übergabe-Code. Ohne Internet möglich.</p>
        <label>Übergabe-Datei <input type="file" name="datei" accept=".json,application/json" required></label>
        <label>Übergabe-Code <input name="code" placeholder="XXXX-XXXX-XX" autocomplete="off" required></label>
        <p><button class="knopf primaer" type="submit">Einspielen</button></p>
    </form>
    <form method="post" action="<?= e(url('halle/abholen')) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2>Online abholen</h2>
        <p class="hilfe">Braucht jetzt Internet. Login der Turnierleitung des Online-Servers; die Zugangsdaten werden nicht gespeichert.</p>
        <label>Online-Adresse <input name="online_url" type="url" placeholder="https://turnier.example.de" required></label>
        <label>E-Mail <input name="email" type="email" autocomplete="off" required></label>
        <label>Passwort <input name="passwort" type="password" autocomplete="off" required></label>
        <label>Übergabe-Code <input name="code" placeholder="XXXX-XXXX-XX" autocomplete="off" required></label>
        <p><button class="knopf primaer" type="submit">Abholen und einspielen</button></p>
    </form>
</div>
