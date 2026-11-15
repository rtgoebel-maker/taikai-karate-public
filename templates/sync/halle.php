<?php
/**
 * Hallen-Laptop: eine Anlaufstelle für Übergabe (Online) und Übernahme/Rückgabe (Laptop).
 * @var list<array{id: int, name: string, datum: string, status: string, art: string, aktion: string, auftrag: bool}> $uebersicht
 * @var list<array<string, mixed>> $turniere übernommene Turniere (Laptop) mit Übertragungsstand
 * @var string $instanzId
 * @var string $version Programmversion dieses Rechners
 */
$this->layout('layout', ['titel' => t('Hallen-Laptop'), 'aktiv' => 'halle']);
$stand = [];
foreach ($turniere as $t) {
    if (!empty($t['stand']) && hash_equals($instanzId, (string) $t['fuehrung_instanz'])) {
        $stand[(int) $t['id']] = $t['stand'];
    }
}
?>
<h1><?= te('Hallen-Laptop') ?></h1>
<p class="leise"><?= te('Ohne verlässliches Internet in der Halle führt ein Laptop das Turnier: Tablets und Ausruf arbeiten im Hallen-WLAN mit dem Laptop, online bleibt das Turnier sichtbar, aber nur lesend. Es schreibt immer nur eine Stelle.') ?></p>

<ol class="halle-ablauf">
    <li><span class="wo"><?= te('Online') ?></span><strong><?= te('Übergeben') ?></strong><span><?= te('Turnier unten mit „An die Halle übergeben …“ (oder Turnier → Einstellungen). Es entsteht ein Übergabe-Code – notieren.') ?></span></li>
    <li><span class="wo"><?= te('Laptop') ?></span><strong><?= te('Übernehmen') ?></strong><span><?= te('Hier unten „Turnier von Online übernehmen“: per Datei (ohne Internet) oder online abholen, mit dem Code.') ?></span></li>
    <li><span class="wo"><?= te('Laptop') ?></span><strong><?= te('Zurückgeben') ?></strong><span><?= te('Nach dem Turnier „An Online zurückgeben …“ – danach führt Online wieder.') ?></span></li>
</ol>

<section class="karte">
    <h2><?= te('Turniere dieser Installation') ?></h2>
    <?php if (!$uebersicht): ?>
        <p class="leise"><?= te('Keine aktuellen Turniere.') ?></p>
    <?php else: ?>
        <div class="tabelle-scroll">
        <table class="tabelle">
            <thead><tr><th><?= te('Turnier') ?></th><th><?= te('Hallen-Status') ?></th><th></th></tr></thead>
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
            <p><button type="button" class="knopf klein" data-einblenden="halle-weitere"><?= count($uebersicht) - 8 === 1 ? te('Ein weiteres Turnier anzeigen') : te('Weitere {n} Turniere anzeigen', ['n' => count($uebersicht) - 8]) ?></button></p>
        <?php endif; ?>
    <?php endif; ?>
    <p class="hilfe"><?= te('Übergeben kann auch die Turnierleitung eines Turniers (ohne Administrationsrechte) unter Turnier → Einstellungen → Hallen-Laptop.') ?></p>
</section>

<h2 class="halle-abschnitt"><?= te('Turnier von Online übernehmen') ?> <span class="klein leise">– <?= te('auf dem Hallen-Laptop') ?></span></h2>
<p class="klein"><?= th('Programmversion dieses Rechners: <code>{version}</code> – vor dem Turnier aktualisieren (Linux: <code>./update-halle.sh</code>, sonst neues Paket; siehe docs/HALLE-LINUX.md). Laptop und Online müssen zueinander passen.', ['version' => $version]) ?></p>
<div class="zwei-spalten">
    <form method="post" action="<?= e(url('halle/datei')) ?>" enctype="multipart/form-data" class="karte formular">
        <?= csrf_feld() ?>
        <h2><?= te('Per Datei') ?></h2>
        <p class="hilfe"><?= te('Übergabe-Datei (online heruntergeladen, z. B. per USB-Stick) und Übergabe-Code. Ohne Internet möglich.') ?></p>
        <label><?= te('Übergabe-Datei') ?> <input type="file" name="datei" accept=".json,application/json" required></label>
        <label><?= te('Übergabe-Code') ?> <input name="code" placeholder="XXXX-XXXX-XX" autocomplete="off" required></label>
        <p><button class="knopf primaer" type="submit"><?= te('Einspielen') ?></button></p>
    </form>
    <form method="post" action="<?= e(url('halle/abholen')) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2><?= te('Online abholen') ?></h2>
        <p class="hilfe"><?= te('Braucht jetzt Internet. Login der Turnierleitung des Online-Servers; die Zugangsdaten werden nicht gespeichert.') ?></p>
        <label><?= te('Online-Adresse') ?> <input name="online_url" type="url" placeholder="https://turnier.example.de" required></label>
        <label><?= te('E-Mail') ?> <input name="email" type="email" autocomplete="off" required></label>
        <label><?= te('Passwort') ?> <input name="passwort" type="password" autocomplete="off" required></label>
        <label><?= te('Übergabe-Code') ?> <input name="code" placeholder="XXXX-XXXX-XX" autocomplete="off" required></label>
        <p><button class="knopf primaer" type="submit"><?= te('Abholen und einspielen') ?></button></p>
    </form>
</div>
