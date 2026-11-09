<?php
/**
 * @var bool $sqlite
 * @var string $grenze
 * @var list<array{name: string, zeit: int, groesse: int}>|null $auto automatische Stände (null = nicht aktiv)
 * @var int $autoAbstand
 */
$this->layout('layout', ['titel' => 'Sicherung']);
?>
<h1>Sicherung &amp; Umzug <?= info('sicherung') ?></h1>
<div class="zwei-spalten">
    <form method="post" action="<?= e(url('import-turnier')) ?>" enctype="multipart/form-data" class="karte formular">
        <?= csrf_feld() ?>
        <h2>Turnier importieren</h2>
        <p class="hilfe">Liest eine mit „Turnier exportieren“ erstellte JSON-Datei ein – z. B. zum Umzug von der lokalen Installation auf den Webserver. Vereine und Athleten werden wiedererkannt, das Turnier wird neu angelegt (bei gleicher Herkunft als Kopie).</p>
        <label>Sicherungsdatei <input type="file" name="datei" accept=".json,application/json" required></label>
        <p class="klein">Dieser Server nimmt Dateien bis <strong><?= e($grenze) ?></strong> an. Größere Sicherungen (viele Teilnehmer, große Logos): in der PHP-Konfiguration <code>upload_max_filesize</code> und <code>post_max_size</code> erhöhen.</p>
        <div><button class="knopf primaer" type="submit">Importieren</button></div>
    </form>
    <div class="karte">
        <h2>Vollsicherung</h2>
        <?php if ($sqlite): ?>
            <p>Lädt eine Kopie der gesamten Datenbank (alle Turniere, Benutzer, Protokoll) herunter. Zum Wiederherstellen die Datei als <code>data/wettkampf.sqlite</code> ablegen.</p>
            <a class="knopf" href="<?= e(url('sicherung/datenbank')) ?>">Datenbank herunterladen</a>
        <?php else: ?>
            <p>Für MySQL/MariaDB bitte die Sicherung des Hosters verwenden oder einzelne Turniere exportieren.</p>
        <?php endif; ?>
        <p class="klein">Einzelne Turniere exportierst du unter Turnier → Einstellungen.</p>
    </div>
</div>

<section class="karte">
    <h2>Automatische Sicherung</h2>
    <?php if ($auto === null): ?>
        <p class="leise">Nicht aktiv – nur mit SQLite (lokal, Hallen-Laptop). Online sichert der Hoster; zusätzlich Turniere exportieren.</p>
    <?php else: ?>
        <p>Während des Betriebs legt die Software höchstens alle <?= (int) round($autoAbstand / 60) ?: 1 ?> Minute(n) – und nur, wenn sich etwas geändert hat –
            eine Kopie der Datenbank in <code>data/sicherungen/</code> ab. Die ältesten Stände werden automatisch gelöscht.
            Fällt der Laptop aus, lässt sich daraus ein einzelnes Turnier oder die ganze Datenbank wiederherstellen.</p>
        <form method="post" action="<?= e(url('sicherung/auto')) ?>" class="knopfleiste">
            <?= csrf_feld() ?>
            <button class="knopf" type="submit">Jetzt sichern</button>
        </form>
        <?php if (!$auto): ?>
            <p class="leise">Noch keine automatische Sicherung vorhanden.</p>
        <?php else: ?>
            <div class="tabelle-scroll">
            <table class="tabelle">
                <thead><tr><th>Stand</th><th class="rechts">Größe</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($auto as $s): ?>
                    <tr>
                        <td><?= e(date('d.m.Y H:i:s', $s['zeit'])) ?></td>
                        <td class="rechts"><?= e(number_format($s['groesse'] / 1048576, 1, ',', '.')) ?> MB</td>
                        <td class="rechts"><a href="<?= e(url('sicherung/auto/' . $s['name'])) ?>">Turnier wiederherstellen</a>
                            · <a href="<?= e(url('sicherung/auto/' . $s['name'] . '/download')) ?>">herunterladen</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
        <p class="klein">Ganze Datenbank zurückspielen: Server beenden, die heruntergeladene Datei als <code>data/wettkampf.sqlite</code> ablegen,
            <code>wettkampf.sqlite-wal</code> und <code>-shm</code> löschen, Server neu starten. Einstellbar in <code>config/config.php</code> unter <code>auto_sicherung</code>.</p>
    <?php endif; ?>
</section>
