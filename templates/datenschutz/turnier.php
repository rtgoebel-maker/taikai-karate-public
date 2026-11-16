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
 * @var array{anmeldungen: int, belege: int, dojos: int, dojoRechnungen: int|null, mails: int, pdf: bool} $exportInhalt was der Export enthält
 * @var list<array{id: int, name: string, datum: string}> $vorlagen Turniere mit gespeicherten Angaben zum Übernehmen
 */
$this->layout('layout', ['titel' => t('Datenschutz'), 'turnier' => $turnier]);
$tid = (int) $turnier['id'];
$vorbei = (string) $turnier['datum'] < date('Y-m-d');
$fertig = $e['pseudonymisiert_am'] !== null;
$zeit = static fn(?string $z) => $z === null ? '' : datum_de(date('Y-m-d', (int) strtotime($z))) . date(' H:i', (int) strtotime($z));
?>
<p class="obertitel"><a href="<?= e(url("turnier/$tid/einstellungen")) ?>"><?= te('Einstellungen') ?></a></p>
<h1><?= te('Datenschutz') ?></h1>
<p class="leise"><?= te('Der ausrichtende Verein ist Verantwortlicher im Sinne der DSGVO. Hier entstehen die Datenschutzhinweise zum Turnier, der Baustein für die Ausschreibung, der Buchhaltungsexport zum Archivieren und die Löschfrist. Die Texte sind eine Vorlage – bitte prüfen (z. B. mit dem Datenschutzbeauftragten des Vereins bzw. Verbands).') ?></p>

<?php if ($fertig): ?>
    <div class="meldung meldung-erfolg"><?= te('Dieses Turnier wurde am {zeit} pseudonymisiert. Namen, Geburtsdaten und Kontaktdaten sind gelöscht; Klassen, Ergebnisse und Vereine bleiben als Statistik.', ['zeit' => $zeit($e['pseudonymisiert_am'])]) ?></div>
<?php elseif (!$e['eingerichtet']): ?>
    <div class="meldung meldung-hinweis"><?= te('Noch nicht eingerichtet: Erst nach dem Speichern der Angaben gelten Löschfrist und Abschaltung der Statusseite automatisch.') ?></div>
<?php elseif ($vorbei && date('Y-m-d') >= $loeschenAb && !$exportErledigt): ?>
    <div class="meldung meldung-fehler"><?= te('Die Löschfrist ist abgelaufen. Bitte jetzt den Buchhaltungsexport erstellen und archivieren – danach wird automatisch pseudonymisiert.') ?></div>
<?php endif; ?>

<?php if ($vorlagen): ?>
    <form method="post" action="<?= e(url("turnier/$tid/datenschutz/uebernehmen")) ?>" class="karte formular knopfleiste"<?= $e['eingerichtet'] ? ' data-bestaetigen="' . te('Die Angaben dieses Turniers werden überschrieben. Übernehmen?') . '" data-bestaetigen-ja="' . te('Übernehmen') . '"' : '' ?>>
        <?= csrf_feld() ?>
        <label><?= te('Angaben übernehmen aus') ?>
            <select name="quelle">
                <?php foreach ($vorlagen as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['name']) ?> (<?= e(datum_de($v['datum'])) ?>)</option><?php endforeach; ?>
            </select></label>
        <button class="knopf" type="submit"><?= te('Übernehmen') ?></button>
        <span class="hilfe"><?= te('Verantwortlicher, Kontakt, Hoster, Aufsicht, Hinweise und Fristen – nicht der Stand von Export oder Pseudonymisierung.') ?></span>
    </form>
<?php endif; ?>

<div class="zwei-spalten">
    <form method="post" action="<?= e(url("turnier/$tid/datenschutz")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2><?= te('Angaben für die Datenschutzhinweise') ?></h2>
        <label><?= te('Verantwortlicher (Verein mit Anschrift)') ?> <span class="hilfe"><?= te('Pflicht') ?></span>
            <textarea name="verantwortlicher" rows="4" maxlength="600" placeholder="TV Musterstadt 1903 e. V. – Abteilung Karate&#10;Musterstraße 1&#10;12345 Musterstadt"><?= e($e['verantwortlicher']) ?></textarea></label>
        <label><?= te('Vertreten durch') ?> <span class="hilfe"><?= te('vertretungsberechtigter Vorstand') ?></span>
            <input name="vertreten" maxlength="300" value="<?= e($e['vertreten']) ?>" placeholder="Erika Muster (1. Vorsitzende), Max Muster (2. Vorsitzender)"></label>
        <label><?= te('Kontakt für Datenschutzfragen') ?> <span class="hilfe"><?= te('Pflicht, z. B. E-Mail der Turnierleitung') ?></span>
            <input name="kontakt" maxlength="200" value="<?= e($e['kontakt']) ?>" placeholder="turnier@tv-musterstadt.de"></label>
        <label><?= te('Datenschutzbeauftragte/r') ?> <span class="hilfe"><?= te('nur falls bestellt') ?></span>
            <input name="dsb" maxlength="300" value="<?= e($e['dsb']) ?>"></label>
        <label><?= te('Hoster mit Auftragsverarbeitungsvertrag') ?> <span class="hilfe"><?= te('leer lassen, wenn nur der Hallen-Laptop genutzt wird') ?></span>
            <textarea name="hoster" rows="2" maxlength="600" placeholder="1blu AG, Stromstraße 1–5, 10555 Berlin"><?= e($e['hoster']) ?></textarea></label>
        <label class="check"><input type="checkbox" name="cloudflare" value="1"<?= chk($e['cloudflare']) ?>> <?= te('Die Seiten laufen über Cloudflare (Abschnitt zu Cloudflare und möglicher Übermittlung in die USA)') ?></label>
        <label><?= te('Zuständige Aufsichtsbehörde') ?> <span class="hilfe"><?= te('Pflicht – Landesbehörde am Sitz des Vereins') ?></span>
            <textarea name="aufsicht" rows="2" maxlength="600" placeholder="Bayerisches Landesamt für Datenschutzaufsicht (BayLDA), Promenade 18, 91522 Ansbach, www.lda.bayern.de"><?= e($e['aufsicht']) ?></textarea></label>
        <label><?= te('Siegerlisten veröffentlichen') ?> <span class="hilfe"><?= te('optional, wo – z. B. „auf www.tv-musterstadt.de und in der Lokalpresse“') ?></span>
            <input name="veroeffentlichung" maxlength="300" value="<?= e($e['veroeffentlichung']) ?>"></label>
        <label class="check"><input type="checkbox" name="fotos" value="1"<?= chk($e['fotos']) ?>> <?= te('Hinweis zu Foto- und Videoaufnahmen am Turniertag aufnehmen') ?></label>
        <label><?= te('Weitere Hinweise') ?> <span class="hilfe"><?= te('optional, erscheint als eigener Abschnitt') ?></span>
            <textarea name="zusatz" rows="3" maxlength="3000"><?= e($e['zusatz']) ?></textarea></label>

        <h3><?= te('Fristen') ?></h3>
        <div class="zeile">
            <label><?= te('Statusseite abschalten nach (Tagen)') ?> <span class="hilfe"><?= te('empfohlen 1–7, 0 = nicht automatisch') ?></span>
                <input type="number" name="live_tage" min="0" max="365" value="<?= (int) $e['live_tage'] ?>"></label>
            <label><?= te('Pseudonymisieren nach (Wochen)') ?> <span class="hilfe"><?= te('empfohlen 4–8 (Nachbereitung, Abrechnung)') ?></span>
                <input type="number" name="loeschfrist_wochen" min="1" max="52" value="<?= (int) $e['loeschfrist_wochen'] ?>"></label>
        </div>
        <label class="check"><input type="checkbox" name="platzierte_behalten" value="1"<?= chk($e['platzierte_behalten']) ?>> <?= te('Plätze 1–3 mit Name und Verein behalten (Chronik des Vereins, berechtigtes Interesse – steht dann in den Hinweisen)') ?></label>
        <h3><?= te('Einladungen') ?></h3>
        <label class="check"><input type="checkbox" name="einladungen" value="1"<?= chk($e['einladungen']) ?>> <?= te('Im Anmeldeformular freiwillig anbieten: „{text}“', ['text' => \Wettkampf\Kern\Sprache::t(\Wettkampf\Meldung\Einladungen::TEXT)]) ?>
            <span class="hilfe"><?= te('Einwilligung nach Art. 6 Abs. 1 lit. a DSGVO, nie vorausgewählt, keine Bedingung für die Anmeldung. Die Adressen bleiben auch nach der Pseudonymisierung (bis zum Widerruf, höchstens {monate} Monate ohne erneute Einwilligung). Versand unter System → Einladungen.', ['monate' => \Wettkampf\Meldung\Einladungen::GUELTIG_MONATE]) ?></span></label>
        <p><button class="knopf primaer" type="submit"><?= te('Speichern') ?></button>
            <a class="knopf" href="<?= e(url("live/$tid/datenschutz")) ?>" target="_blank"><?= te('Datenschutzhinweise ansehen') ?> ↗</a></p>
    </form>

    <div>
        <section class="karte">
            <h2><?= te('Datenschutzhinweise und Ausschreibung') ?></h2>
            <p><?= te('Die Hinweise nach Art. 13/14 DSGVO stehen öffentlich unter') ?></p>
            <p><code><?= e($link) ?></code></p>
            <p class="hilfe"><?= te('Das Anmeldeformular verlinkt sie; das meldende Dojo bestätigt dort, dass die Starter bzw. Erziehungsberechtigten informiert wurden (Zeitpunkt und Textfassung werden je Anmeldung gespeichert). Ändert sich der Anmeldelink, ändert sich auch diese Adresse.') ?></p>
            <label><?= te('Baustein für die Ausschreibung (markieren und kopieren)') ?>
                <textarea rows="12" readonly><?= e($ausschreibung) ?></textarea></label>
        </section>

        <section class="karte">
            <h2><?= te('Buchhaltungsexport') ?></h2>
            <p><?= te('ZIP zum Archivieren außerhalb der Software: Zahlungsliste (CSV), Rechnungen bzw. Zahlungsbelege als PDF (nur Posten, ohne Starterliste), versendete E-Mails und Prüfsummen. Die Software ist kein Archiv – nach der Löschfrist werden die Personenangaben dort pseudonymisiert.') ?></p>
            <?php if (!$pdf): ?><div class="meldung meldung-hinweis"><?= te('PDF-Belege sind auf diesem Server nicht verfügbar (PHP-Erweiterung dom fehlt) – der Export enthält dann nur Listen und Mails.') ?></div><?php endif; ?>
            <p class="klein"><strong><?= te('Enthält:') ?></strong>
                <?= te('{n} Belege zu Online-Anmeldungen', ['n' => $pdf ? $exportInhalt['belege'] : 0]) ?> ·
                <?= te('{n} Rechnungen je Dojo', ['n' => $pdf ? (int) $exportInhalt['dojoRechnungen'] : 0]) ?> ·
                <?= te('{n} E-Mails', ['n' => $exportInhalt['mails']]) ?></p>
            <?php if ($exportInhalt['dojoRechnungen'] === null && $exportInhalt['dojos'] > 0): ?>
                <div class="meldung meldung-hinweis"><?= th('Die <strong>Rechnungen je Dojo ({n})</strong> fehlen im Export, solange unter <a href="{url}">Rechnungen</a> kein Absender eingetragen ist.', ['n' => $exportInhalt['dojos'], 'url' => url("turnier/$tid/rechnungen")]) ?></div>
            <?php endif; ?>
            <?php if ($exportInhalt['anmeldungen'] === 0): ?>
                <p class="hilfe"><?= te('Keine Online-Anmeldungen in diesem Turnier – Belege dazu gibt es deshalb nicht.') ?></p>
            <?php endif; ?>
            <p class="klein"><?= $e['exportiert_am'] !== null ? te('Zuletzt exportiert: {zeit}', ['zeit' => $zeit($e['exportiert_am'])]) : te('Noch nicht exportiert.') ?></p>
            <?php if (!$fertig): ?>
                <form method="post" action="<?= e(url("turnier/$tid/datenschutz/buchhaltung")) ?>">
                    <?= csrf_feld() ?>
                    <button class="knopf primaer" type="submit"><?= te('Buchhaltungsexport herunterladen') ?></button>
                </form>
            <?php endif; ?>
        </section>

        <section class="karte">
            <h2><?= te('Löschfrist und Pseudonymisierung') ?></h2>
            <ul>
                <li><?= te('Statusseite: {stand}', ['stand' => ($e['live_abgeschaltet_am'] !== null ? t('abgeschaltet am {zeit}', ['zeit' => $zeit($e['live_abgeschaltet_am'])])
                    : ($liveAusAb !== null ? t('wird am {datum} abgeschaltet', ['datum' => datum_de($liveAusAb)]) : t('wird nicht automatisch abgeschaltet')))
                    . (!$e['eingerichtet'] ? ' (' . t('nach dem Speichern') . ')' : '')]) ?></li>
                <li><?= te('Pseudonymisierung: {stand}', ['stand' => $fertig ? t('erledigt am {zeit}', ['zeit' => $zeit($e['pseudonymisiert_am'])])
                    : ($e['eingerichtet'] ? t('ab {datum} automatisch', ['datum' => datum_de($loeschenAb)]) : t('ab {datum} (nach dem Speichern automatisch)', ['datum' => datum_de($loeschenAb)]))]) ?>
                    <?= !$fertig && !$exportErledigt ? '– ' . te('sobald der Buchhaltungsexport erstellt ist') : '' ?></li>
            </ul>
            <p class="hilfe"><?= te('Danach heißen die Starter „Starter 1, 2, …“; gelöscht werden Geburtsdaten, Ausweisnummern, Kontaktdaten und Inhalte der Anmeldungen, E-Mails, Nachrichten, Korrekturanträge, Gerätenamen und die Personenangaben im Änderungsverlauf. Erhalten bleiben Klassen, Anzahl der Starter, Begegnungen, Ergebnisse, Geschlecht, Graduierung und Vereine. Automatische Sicherungen dieser Installation werden dabei ersetzt; selbst heruntergeladene Sicherungen bitte ebenfalls löschen.') ?></p>
            <?php if (!$fertig && $vorbei): ?>
                <form method="post" action="<?= e(url("turnier/$tid/datenschutz/pseudonymisieren")) ?>" class="formular" data-bestaetigen="<?= te('Turnier jetzt unwiderruflich pseudonymisieren?') ?>" data-bestaetigen-ja="<?= te('Pseudonymisieren') ?>">
                    <?= csrf_feld() ?>
                    <label><?= te('Jetzt pseudonymisieren – zur Bestätigung den Turniernamen eingeben') ?> <input name="bestaetigung" autocomplete="off" required></label>
                    <?php if (!$exportErledigt): ?>
                        <label class="check"><input type="checkbox" name="ohne_export" value="1"> <?= te('Ohne Buchhaltungsexport (die Belege lassen sich danach nicht mehr erzeugen)') ?></label>
                    <?php endif; ?>
                    <div><button class="knopf gefahr" type="submit"><?= te('Unwiderruflich pseudonymisieren') ?></button></div>
                </form>
            <?php elseif (!$fertig): ?>
                <p class="klein"><?= te('Pseudonymisieren geht erst nach dem Turniertag.') ?></p>
            <?php endif; ?>
        </section>
    </div>
</div>
