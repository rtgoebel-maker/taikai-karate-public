<?php
/**
 * @var bool $sqlite
 * @var string $grenze
 * @var list<array{name: string, zeit: int, groesse: int}>|null $auto automatische Stände (null = nicht aktiv)
 * @var int $autoAbstand
 */
$this->layout('layout', ['titel' => t('Sicherung')]);
?>
<h1><?= te('Sicherung & Umzug') ?> <?= info('sicherung') ?></h1>
<div class="zwei-spalten">
    <form method="post" action="<?= e(url('import-turnier')) ?>" enctype="multipart/form-data" class="karte formular">
        <?= csrf_feld() ?>
        <h2><?= te('Turnier importieren') ?></h2>
        <p class="hilfe"><?= te('Liest eine mit „Turnier exportieren“ erstellte JSON-Datei ein – z. B. zum Umzug von der lokalen Installation auf den Webserver. Vereine und Athleten werden wiedererkannt, das Turnier wird neu angelegt (bei gleicher Herkunft als Kopie).') ?></p>
        <label><?= te('Sicherungsdatei') ?> <input type="file" name="datei" accept=".json,application/json" required></label>
        <p class="klein"><?= th('Dieser Server nimmt Dateien bis <strong>{grenze}</strong> an. Größere Sicherungen (viele Teilnehmer, große Logos): in der PHP-Konfiguration <code>upload_max_filesize</code> und <code>post_max_size</code> erhöhen.', ['grenze' => $grenze]) ?></p>
        <div><button class="knopf primaer" type="submit"><?= te('Importieren') ?></button></div>
    </form>
<?php if (empty($demo)): ?>
    <div class="karte">
        <h2><?= te('Vollsicherung') ?></h2>
        <?php if ($sqlite): ?>
            <p><?= th('Lädt eine Kopie der gesamten Datenbank (alle Turniere, Benutzer, Protokoll) herunter. Zum Wiederherstellen die Datei als <code>data/wettkampf.sqlite</code> ablegen.') ?></p>
            <a class="knopf" href="<?= e(url('sicherung/datenbank')) ?>"><?= te('Datenbank herunterladen') ?></a>
        <?php else: ?>
            <p><?= te('Für MySQL/MariaDB bitte die Sicherung des Hosters verwenden oder einzelne Turniere exportieren.') ?></p>
        <?php endif; ?>
        <p class="klein"><?= te('Einzelne Turniere exportierst du unter Turnier → Einstellungen.') ?></p>
    </div>
<?php endif; ?>
</div>

<?php if (empty($demo)): ?>
<section class="karte">
    <h2><?= te('Automatische Sicherung') ?></h2>
    <?php if ($auto === null): ?>
        <p class="leise"><?= te('Nicht aktiv – nur mit SQLite (lokal, Hallen-Laptop). Online sichert der Hoster; zusätzlich Turniere exportieren.') ?></p>
    <?php else: ?>
        <p><?= th('Während des Betriebs legt die Software höchstens alle {n} Minute(n) – und nur, wenn sich etwas geändert hat – eine Kopie der Datenbank in <code>data/sicherungen/</code> ab. Die ältesten Stände werden automatisch gelöscht.', ['n' => (int) round($autoAbstand / 60) ?: 1]) ?>
            <?= te('Fällt der Laptop aus, lässt sich daraus ein einzelnes Turnier oder die ganze Datenbank wiederherstellen.') ?></p>
        <form method="post" action="<?= e(url('sicherung/auto')) ?>" class="knopfleiste">
            <?= csrf_feld() ?>
            <button class="knopf" type="submit"><?= te('Jetzt sichern') ?></button>
        </form>
        <?php if (!$auto): ?>
            <p class="leise"><?= te('Noch keine automatische Sicherung vorhanden.') ?></p>
        <?php else: ?>
            <div class="tabelle-scroll">
            <table class="tabelle">
                <thead><tr><th><?= te('Stand') ?></th><th class="rechts"><?= te('Größe') ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($auto as $s): ?>
                    <tr>
                        <td><?= e(date('d.m.Y H:i:s', $s['zeit'])) ?></td>
                        <td class="rechts"><?= e(sprache() === 'en' ? number_format($s['groesse'] / 1048576, 1, '.', ',') : number_format($s['groesse'] / 1048576, 1, ',', '.')) ?> MB</td>
                        <td class="rechts"><a href="<?= e(url('sicherung/auto/' . $s['name'])) ?>"><?= te('Turnier wiederherstellen') ?></a>
                            · <a href="<?= e(url('sicherung/auto/' . $s['name'] . '/download')) ?>"><?= te('herunterladen') ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
        <p class="klein"><?= th('Ganze Datenbank zurückspielen: Server beenden, die heruntergeladene Datei als <code>data/wettkampf.sqlite</code> ablegen, <code>wettkampf.sqlite-wal</code> und <code>-shm</code> löschen, Server neu starten. Einstellbar in <code>config/config.php</code> unter <code>auto_sicherung</code>.') ?></p>
    <?php endif; ?>
</section>
<?php endif; ?>
