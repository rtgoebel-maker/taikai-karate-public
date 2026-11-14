<?php
/**
 * Datenschutz je Turnier (Turnierleitung): Angaben für die Datenschutzhinweise, Baustein für die Ausschreibung,
 * Buchhaltungsexport und Löschfrist/Pseudonymisierung.
 * @var array<string, mixed> $turnier
 * @var array<string, mixed> $e DatenschutzEinstellungen::lesen()
 * @var string $link öffentliche Adresse der Datenschutzhinweise
 * @var string $ausschreibung
 * @var string $loeschenAb
 * @var string|null $liveAusAb
 * @var bool $exportErledigt
 * @var bool $pdf
 */
$this->layout('layout', ['titel' => 'Datenschutz', 'turnier' => $turnier]);
$tid = (int) $turnier['id'];
$vorbei = (string) $turnier['datum'] < date('Y-m-d');
$fertig = $e['pseudonymisiert_am'] !== null;
$zeit = static fn(?string $z) => $z === null ? '' : date('d.m.Y H:i', (int) strtotime($z));
?>
<p class="obertitel"><a href="<?= e(url("turnier/$tid/einstellungen")) ?>">Einstellungen</a></p>
<h1>Datenschutz</h1>
<p class="leise">Der ausrichtende Verein ist Verantwortlicher im Sinne der DSGVO. Hier entstehen die Datenschutzhinweise zum Turnier, der
    Baustein für die Ausschreibung, der Buchhaltungsexport zum Archivieren und die Löschfrist. Die Texte sind eine Vorlage – bitte prüfen
    (z. B. mit dem Datenschutzbeauftragten des Vereins bzw. Verbands).</p>

<?php if ($fertig): ?>
    <div class="meldung meldung-erfolg">Dieses Turnier wurde am <?= e($zeit($e['pseudonymisiert_am'])) ?> pseudonymisiert. Namen, Geburtsdaten und Kontaktdaten sind gelöscht; Klassen, Ergebnisse und Vereine bleiben als Statistik.</div>
<?php elseif (!$e['eingerichtet']): ?>
    <div class="meldung meldung-hinweis">Noch nicht eingerichtet: Erst nach dem Speichern der Angaben gelten Löschfrist und Abschaltung der Statusseite automatisch.</div>
<?php elseif ($vorbei && date('Y-m-d') >= $loeschenAb && !$exportErledigt): ?>
    <div class="meldung meldung-fehler">Die Löschfrist ist abgelaufen. Bitte jetzt den Buchhaltungsexport erstellen und archivieren – danach wird automatisch pseudonymisiert.</div>
<?php endif; ?>

<div class="zwei-spalten">
    <form method="post" action="<?= e(url("turnier/$tid/datenschutz")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2>Angaben für die Datenschutzhinweise</h2>
        <label>Verantwortlicher (Verein mit Anschrift) <span class="hilfe">Pflicht</span>
            <textarea name="verantwortlicher" rows="4" maxlength="600" placeholder="TV Musterstadt 1903 e. V. – Abteilung Karate&#10;Musterstraße 1&#10;12345 Musterstadt"><?= e($e['verantwortlicher']) ?></textarea></label>
        <label>Vertreten durch <span class="hilfe">vertretungsberechtigter Vorstand</span>
            <input name="vertreten" maxlength="300" value="<?= e($e['vertreten']) ?>" placeholder="Erika Muster (1. Vorsitzende), Max Muster (2. Vorsitzender)"></label>
        <label>Kontakt für Datenschutzfragen <span class="hilfe">Pflicht, z. B. E-Mail der Turnierleitung</span>
            <input name="kontakt" maxlength="200" value="<?= e($e['kontakt']) ?>" placeholder="turnier@tv-musterstadt.de"></label>
        <label>Datenschutzbeauftragte/r <span class="hilfe">nur falls bestellt</span>
            <input name="dsb" maxlength="300" value="<?= e($e['dsb']) ?>"></label>
        <label>Hoster mit Auftragsverarbeitungsvertrag <span class="hilfe">leer lassen, wenn nur der Hallen-Laptop genutzt wird</span>
            <textarea name="hoster" rows="2" maxlength="600" placeholder="1blu AG, Stromstraße 1–5, 10555 Berlin"><?= e($e['hoster']) ?></textarea></label>
        <label class="check"><input type="checkbox" name="cloudflare" value="1"<?= chk($e['cloudflare']) ?>> Die Seiten laufen über Cloudflare (Abschnitt zu Cloudflare und möglicher Übermittlung in die USA)</label>
        <label>Zuständige Aufsichtsbehörde <span class="hilfe">Pflicht – Landesbehörde am Sitz des Vereins</span>
            <textarea name="aufsicht" rows="2" maxlength="600" placeholder="Bayerisches Landesamt für Datenschutzaufsicht (BayLDA), Promenade 18, 91522 Ansbach, www.lda.bayern.de"><?= e($e['aufsicht']) ?></textarea></label>
        <label>Siegerlisten veröffentlichen <span class="hilfe">optional, wo – z. B. „auf www.tv-musterstadt.de und in der Lokalpresse“</span>
            <input name="veroeffentlichung" maxlength="300" value="<?= e($e['veroeffentlichung']) ?>"></label>
        <label class="check"><input type="checkbox" name="fotos" value="1"<?= chk($e['fotos']) ?>> Hinweis zu Foto- und Videoaufnahmen am Turniertag aufnehmen</label>
        <label>Weitere Hinweise <span class="hilfe">optional, erscheint als eigener Abschnitt</span>
            <textarea name="zusatz" rows="3" maxlength="3000"><?= e($e['zusatz']) ?></textarea></label>

        <h3>Fristen</h3>
        <div class="zeile">
            <label>Statusseite abschalten nach (Tagen) <span class="hilfe">empfohlen 1–7, 0 = nicht automatisch</span>
                <input type="number" name="live_tage" min="0" max="365" value="<?= (int) $e['live_tage'] ?>"></label>
            <label>Pseudonymisieren nach (Wochen) <span class="hilfe">empfohlen 4–8 (Nachbereitung, Abrechnung)</span>
                <input type="number" name="loeschfrist_wochen" min="1" max="52" value="<?= (int) $e['loeschfrist_wochen'] ?>"></label>
        </div>
        <label class="check"><input type="checkbox" name="platzierte_behalten" value="1"<?= chk($e['platzierte_behalten']) ?>> Plätze 1–3 mit Name und Verein behalten (Chronik des Vereins, berechtigtes Interesse – steht dann in den Hinweisen)</label>
        <h3>Einladungen</h3>
        <label class="check"><input type="checkbox" name="einladungen" value="1"<?= chk($e['einladungen']) ?>> Im Anmeldeformular freiwillig anbieten: „<?= e(\Wettkampf\Meldung\Einladungen::TEXT) ?>“
            <span class="hilfe">Einwilligung nach Art. 6 Abs. 1 lit. a DSGVO, nie vorausgewählt, keine Bedingung für die Anmeldung. Die Adressen bleiben auch nach der Pseudonymisierung
                (bis zum Widerruf, höchstens <?= \Wettkampf\Meldung\Einladungen::GUELTIG_MONATE ?> Monate ohne erneute Einwilligung). Versand unter System → Einladungen.</span></label>
        <p><button class="knopf primaer" type="submit">Speichern</button>
            <a class="knopf" href="<?= e(url("live/$tid/datenschutz")) ?>" target="_blank">Datenschutzhinweise ansehen ↗</a></p>
    </form>

    <div>
        <section class="karte">
            <h2>Datenschutzhinweise und Ausschreibung</h2>
            <p>Die Hinweise nach Art. 13/14 DSGVO stehen öffentlich unter</p>
            <p><code><?= e($link) ?></code></p>
            <p class="hilfe">Das Anmeldeformular verlinkt sie; das meldende Dojo bestätigt dort, dass die Starter bzw. Erziehungsberechtigten informiert wurden
                (Zeitpunkt und Textfassung werden je Anmeldung gespeichert). Ändert sich der Anmeldelink, ändert sich auch diese Adresse.</p>
            <label>Baustein für die Ausschreibung (markieren und kopieren)
                <textarea rows="12" readonly><?= e($ausschreibung) ?></textarea></label>
        </section>

        <section class="karte">
            <h2>Buchhaltungsexport</h2>
            <p>ZIP zum Archivieren außerhalb der Software: Zahlungsliste (CSV), Rechnungen bzw. Zahlungsbelege als PDF (nur Posten, ohne Starterliste), versendete E-Mails und Prüfsummen.
                Die Software ist kein Archiv – nach der Löschfrist werden die Personenangaben dort pseudonymisiert.</p>
            <?php if (!$pdf): ?><div class="meldung meldung-hinweis">PDF-Belege sind auf diesem Server nicht verfügbar (PHP-Erweiterung dom fehlt) – der Export enthält dann nur Listen und Mails.</div><?php endif; ?>
            <p class="klein"><?= $e['exportiert_am'] !== null ? 'Zuletzt exportiert: ' . e($zeit($e['exportiert_am'])) : 'Noch nicht exportiert.' ?></p>
            <?php if (!$fertig): ?>
                <form method="post" action="<?= e(url("turnier/$tid/datenschutz/buchhaltung")) ?>">
                    <?= csrf_feld() ?>
                    <button class="knopf primaer" type="submit">Buchhaltungsexport herunterladen</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="karte">
            <h2>Löschfrist und Pseudonymisierung</h2>
            <ul>
                <li>Statusseite: <?= $e['live_abgeschaltet_am'] !== null ? 'abgeschaltet am ' . e($zeit($e['live_abgeschaltet_am']))
                    : ($liveAusAb !== null ? 'wird am ' . e(datum_de($liveAusAb)) . ' abgeschaltet' : 'wird nicht automatisch abgeschaltet') ?><?= !$e['eingerichtet'] ? ' (nach dem Speichern)' : '' ?></li>
                <li>Pseudonymisierung: <?= $fertig ? 'erledigt am ' . e($zeit($e['pseudonymisiert_am'])) : 'ab ' . e(datum_de($loeschenAb)) . ($e['eingerichtet'] ? ' automatisch' : ' (nach dem Speichern automatisch)') ?>
                    <?= !$fertig && !$exportErledigt ? '– sobald der Buchhaltungsexport erstellt ist' : '' ?></li>
            </ul>
            <p class="hilfe">Danach heißen die Starter „Starter 1, 2, …“; gelöscht werden Geburtsdaten, Ausweisnummern, Kontaktdaten und Inhalte der Anmeldungen,
                E-Mails, Nachrichten, Korrekturanträge, Gerätenamen und die Personenangaben im Änderungsverlauf. Erhalten bleiben Klassen, Anzahl der Starter,
                Begegnungen, Ergebnisse, Geschlecht, Graduierung und Vereine. Automatische Sicherungen dieser Installation werden dabei ersetzt;
                selbst heruntergeladene Sicherungen bitte ebenfalls löschen.</p>
            <?php if (!$fertig && $vorbei): ?>
                <form method="post" action="<?= e(url("turnier/$tid/datenschutz/pseudonymisieren")) ?>" class="formular" data-bestaetigen="Turnier jetzt unwiderruflich pseudonymisieren?" data-bestaetigen-ja="Pseudonymisieren">
                    <?= csrf_feld() ?>
                    <label>Jetzt pseudonymisieren – zur Bestätigung den Turniernamen eingeben <input name="bestaetigung" autocomplete="off" required></label>
                    <?php if (!$exportErledigt): ?>
                        <label class="check"><input type="checkbox" name="ohne_export" value="1"> Ohne Buchhaltungsexport (die Belege lassen sich danach nicht mehr erzeugen)</label>
                    <?php endif; ?>
                    <div><button class="knopf gefahr" type="submit">Unwiderruflich pseudonymisieren</button></div>
                </form>
            <?php elseif (!$fertig): ?>
                <p class="klein">Pseudonymisieren geht erst nach dem Turniertag.</p>
            <?php endif; ?>
        </section>
    </div>
</div>
