<?php
/**
 * Hallen-Laptop (Schritte 9d–9h): online übergeben bzw. Stand der Übergabe; am Laptop Übertragung, Rückgabe, Löschen.
 * @var array<string, mixed> $turnier
 * @var array<string, mixed>|null $laufend   online: laufende Übergabe
 * @var array<string, mixed>|null $auftrag   am Laptop: Verbindung zu Online (sync_auftrag)
 * @var string|null $code                    Übergabe-Code (nur direkt nach dem Übergeben, in dieser Sitzung)
 * @var string $onlineUrl                    Adresse dieser Installation
 * @var bool $selbst                         dieser Laptop führt das Turnier
 * @var array<string, mixed>|null $syncStand Übertragungsstand (Laptop)
 * @var string $version                     Programmversion dieser Installation
 */
$this->layout('layout', ['titel' => 'Hallen-Laptop', 'turnier' => $turnier, 'aktiv' => 'einstellungen']);
$tid = $turnier['id'];
$zeit = static fn(?string $z) => $z ? date('d.m.Y H:i', (int) strtotime($z)) : '–';
?>
<p><a href="<?= e(url("turnier/$tid/einstellungen")) ?>">← Einstellungen</a></p>
<h1>Hallen-Laptop (Offline-Betrieb)</h1>
<p class="hilfe">Ist in der Halle kein verlässliches Internet, führt ein Laptop das Turnier: Die Tablets arbeiten im Hallen-WLAN mit dem Laptop,
    online ist das Turnier währenddessen <strong>nur lesend</strong> und zeigt den zuletzt übertragenen Stand. Es schreibt immer nur eine Stelle.</p>

<?php if ($auftrag && $selbst): ?>
    <div class="karte">
        <h2>Dieser Laptop führt das Turnier</h2>
        <p>Übergabe Nr. <?= (int) $auftrag['uebergabe_nr'] ?>, eingespielt am <?= e($zeit($auftrag['eingespielt_am'])) ?> · Online: <code><?= e($auftrag['online_url']) ?></code> · Programmversion <code><?= e($version) ?></code></p>
        <?php if (!empty($syncStand)): ?><p><?= $this->teil('sync/stand', ['stand' => $syncStand]) ?></p><?php endif; ?>
    </div>
    <form method="post" action="<?= e(url("turnier/$tid/halle/zurueckgeben")) ?>" class="karte formular"
          data-bestaetigen="Turnier an Online zurückgeben? Dafür ist jetzt Internet nötig. Danach wird online weitergearbeitet und dieser Laptop ist nur noch lesend.">
        <?= csrf_feld() ?>
        <h2>An Online zurückgeben</h2>
        <p>Nach dem Turnier (oder wenn Online wieder führen soll): überträgt den letzten Stand, Logos und Unterschriften der Urkunden sowie
            Geburtsdaten der hier nachgemeldeten Teilnehmer. Danach führt Online; dieser Laptop zeigt das Turnier nur noch an.</p>
        <p><button class="knopf primaer" type="submit">An Online zurückgeben</button></p>
    </form>
<?php elseif ($auftrag): ?>
    <div class="karte">
        <?php if ($auftrag['zurueckgegeben_am']): ?>
            <h2>An Online zurückgegeben</h2>
            <p>Am <?= e($zeit($auftrag['zurueckgegeben_am'])) ?>. Weitergearbeitet wird online unter <code><?= e($auftrag['online_url']) ?></code>.</p>
        <?php else: ?>
            <h2>Führung entzogen</h2>
            <p>Online hat das Turnier am <?= e($zeit($auftrag['entzogen_am'] ?? $turnier['fuehrung_seit'])) ?> zurückgeholt bzw. neu übergeben.
                Ergebnisse, die dieser Laptop danach erfasst hätte, sind online nicht angekommen – bitte mit dem Notfall-Satz abgleichen.</p>
        <?php endif; ?>
        <p>Das Turnier ist hier nur noch lesbar. Es enthält Geburtsdaten – bitte bei Bedarf eine Sicherung herunterladen und es dann hier löschen.</p>
        <p class="knopfleiste">
            <a class="knopf" href="<?= e(url("turnier/$tid/export")) ?>">Sicherung herunterladen</a>
        </p>
        <form method="post" action="<?= e(url("turnier/$tid/halle/loeschen")) ?>" data-bestaetigen="Turnier auf diesem Laptop endgültig löschen? Online bleibt es erhalten.">
            <?= csrf_feld() ?>
            <button class="knopf gefahr" type="submit">Turnier auf diesem Laptop löschen</button>
        </form>
    </div>
<?php elseif ($laufend): ?>
    <div class="karte">
        <h2>Übergeben – Nr. <?= (int) $laufend['nr'] ?></h2>
        <p>Übergeben am <?= e($zeit($laufend['erstellt_am'])) ?> von <?= e($laufend['erstellt_von']) ?>.
            Am Laptop abgeholt: <?= $laufend['abgeholt_am'] ? e($zeit($laufend['abgeholt_am'])) : 'noch nicht (bzw. per Datei eingespielt)' ?>.</p>
        <p><strong>Stand der Halle:</strong>
            <?= $laufend['letzter_empfang']
                ? 'zuletzt empfangen ' . e($zeit($laufend['letzter_empfang'])) . ' Uhr (Revision ' . (int) $laufend['uebernommene_revision'] . ')'
                : 'noch nichts empfangen – der Laptop sendet, sobald er Internet hat (Fenster „Wettkampf Sync“).' ?></p>
        <p class="klein">Programmversion online <code><?= e($version) ?></code><?php if ($laufend['halle_version']): ?>, Laptop <code><?= e($laufend['halle_version']) ?></code>
            <?php if ($laufend['halle_version'] !== $version): ?><span class="tag warn">abweichend</span> – funktioniert, solange die Gegenseite nicht ablehnt; Laptop nach der Rückgabe aktualisieren.<?php endif; ?><?php endif; ?></p>
        <?php if ($code): ?>
            <div class="meldung meldung-hinweis">
                <p><strong>Übergabe-Code:</strong> <span class="uebergabe-code"><?= e($code) ?></span></p>
                <p class="klein">Bitte notieren – er wird nur jetzt angezeigt. Am Laptop unter <strong>Halle</strong> eingeben: entweder mit der
                    Übergabe-Datei oder zum Abholen zusammen mit der Online-Adresse <code><?= e($onlineUrl) ?></code> und Ihrem Login.</p>
                <p><a class="knopf primaer" href="<?= e(url("turnier/$tid/halle/datei")) ?>">Übergabe-Datei herunterladen</a></p>
                <p class="klein">Die Datei enthält Geburtsdaten, PINs und den Schlüssel für die Übertragung – wie eine Sicherung vertraulich behandeln
                    und nach dem Einspielen löschen.</p>
            </div>
        <?php endif; ?>
        <p class="hilfe">Regulär gibt der Laptop das Turnier am Ende zurück. „Zurücknehmen“ nur im Notfall (Laptop defekt, Übergabe nie gestartet):
            Was der Laptop bis dahin nicht übertragen hat, fehlt dann online.</p>
        <form method="post" action="<?= e(url("turnier/$tid/halle/zuruecknehmen")) ?>"
              data-bestaetigen="Übergabe zurücknehmen? Online führt dann wieder. Arbeitet der Laptop noch, werden seine Ergebnisse online nicht mehr angenommen – die müssten nachgetragen werden.">
            <?= csrf_feld() ?>
            <button class="knopf gefahr" type="submit">Übergabe zurücknehmen</button>
        </form>
    </div>
<?php else: ?>
    <form method="post" action="<?= e(url("turnier/$tid/halle/uebergeben")) ?>" class="karte formular"
          data-bestaetigen="Turnier an einen Hallen-Laptop übergeben? Online ist das Turnier danach nur lesend, bis es zurückgegeben oder die Übergabe zurückgenommen wird.">
        <?= csrf_feld() ?>
        <h2>An die Halle übergeben</h2>
        <p>Am Vorabend oder morgens mit Internet: Nach dem Übergeben erscheint ein <strong>Übergabe-Code</strong>. Damit wird das Turnier am Laptop
            eingespielt – per <strong>Datei</strong> (auch über einen USB-Stick) oder direkt <strong>online abgeholt</strong>.</p>
        <p><button class="knopf primaer" type="submit">Turnier an die Halle übergeben</button></p>
    </form>
<?php endif; ?>
