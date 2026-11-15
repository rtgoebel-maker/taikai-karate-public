<?php
/**
 * Einrichtungs-Assistent: Schrittleiste und das Formular des aktuellen Schritts. Die Formulare speichern über die
 * üblichen Stellen (Einstellungen, Klassen, Zugänge, Anmeldung, Rechnungen, Coaching-Portal); das Feld „assistent“
 * führt danach zum nächsten Schritt.
 * @var array<string, mixed> $turnier
 * @var string $schritt
 * @var array<string, array{erledigt: bool|null, text: string}> $stand
 * @var string $naechster
 * @var string|null $vorheriger
 */
use Wettkampf\Betrieb\TurnierAssistent;
use Wettkampf\Domain\Graduierung;
use Wettkampf\Meldung\AnmeldungService;
use Wettkampf\Meldung\CoachService;

$this->layout('layout', ['titel' => t('Einrichtungs-Assistent'), 'turnier' => $turnier, 'aktiv' => 'uebersicht']);
$tid = (int) $turnier['id'];
$weiterPfad = "turnier/$tid/assistent/$naechster";
$weiterFeld = '<input type="hidden" name="assistent" value="' . e($weiterPfad) . '">';
[$titel, $kurz] = TurnierAssistent::SCHRITTE[$schritt];
$nr = array_search($schritt, array_keys(TurnierAssistent::SCHRITTE), true) + 1;
$euroFeld = static fn (int $cent) => $cent ? str_replace('.', ',', sprintf('%.2f', $cent / 100)) : '';
/** Fußleiste: zurück, überspringen, speichern */
$fuss = static function (string $knopf) use ($vorheriger, $tid, $weiterPfad, $schritt): string {
    return '<div class="assistent-fuss">'
        . ($vorheriger !== null ? '<a class="knopf" href="' . e(url("turnier/$tid/assistent/$vorheriger")) . '">← ' . te('Zurück') . '</a>' : '<span></span>')
        . '<span class="knopfleiste">'
        . ($schritt !== 'fertig' ? '<a' . ($knopf === '' ? ' class="knopf primaer"' : '') . ' href="' . e(url($weiterPfad)) . '">' . ($knopf === '' ? te('Weiter →') : te('Überspringen')) . '</a>' : '')
        . ($knopf !== '' ? '<button class="knopf primaer" type="submit">' . e($knopf) . '</button>' : '')
        . '</span></div>';
};
?>
<p class="obertitel"><?= te('Einrichtungs-Assistent') ?> · <?= e($turnier['name']) ?></p>
<h1><?= te('Schritt {nr}: {titel}', ['nr' => $nr, 'titel' => t($titel)]) ?></h1>

<ol class="assistent-schritte">
    <?php $i = 0; foreach (TurnierAssistent::SCHRITTE as $s => [$t, $k]): $i++; $z = $stand[$s]['erledigt']; ?>
        <li class="<?= $s === $schritt ? 'hier' : '' ?> <?= $z === true ? 'erledigt' : ($z === null ? 'entfaellt' : 'offen') ?>">
            <a href="<?= e(url("turnier/$tid/assistent/$s")) ?>" title="<?= e($stand[$s]['text']) ?>">
                <span class="nr"><?= $z === true && $s !== $schritt ? '✓' : $i ?></span><span class="titel"><?= e(t($t)) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ol>
<p class="leise"><?= e(t($kurz)) ?>. <?= te('Alles lässt sich später unter Einstellungen bzw. auf den jeweiligen Seiten ändern.') ?></p>

<?php if ($schritt === 'grunddaten'): ?>
    <form method="post" action="<?= e(url("turnier/$tid/einstellungen")) ?>" class="karte formular">
        <?= csrf_feld() ?><?= $weiterFeld ?>
        <label><?= te('Name') ?> <input name="name" value="<?= e($turnier['name']) ?>" required></label>
        <div class="zeile">
            <label><?= te('Datum') ?> <input type="date" name="datum" value="<?= e($turnier['datum']) ?>" required></label>
            <label><?= te('Anzahl Tatamis') ?> <input type="number" name="anzahl_tatamis" min="1" max="20" value="<?= e($turnier['anzahl_tatamis']) ?>" required></label>
        </div>
        <div class="zeile">
            <label><?= te('Ort') ?> <input name="ort" value="<?= e($turnier['ort']) ?>"></label>
            <label><?= te('Halle') ?> <input name="halle" value="<?= e($turnier['halle']) ?>"></label>
        </div>
        <div class="zeile">
            <label><?= te('Mindestgraduierung') ?>
                <select name="min_graduierung"><option value=""><?= te('keine') ?></option>
                    <?php foreach (Graduierung::auswahl() as $g => $text): ?><option value="<?= $g ?>"<?= sel($g, $turnier['min_graduierung']) ?>><?= e($text) ?></option><?php endforeach; ?>
                </select></label>
            <label><?= te('Meldeschluss') ?> <span class="hilfe"><?= te('bis dahin können Dojos online melden und ändern') ?></span>
                <input type="date" name="meldeschluss" value="<?= e($turnier['meldeschluss']) ?>"></label>
        </div>
        <label class="check"><input type="checkbox" name="oeffentlich" value="1"<?= chk($turnier['oeffentlich']) ?>> <?= te('Öffentliche Statusseite (nur Name und Verein)') ?></label>
        <?= $fuss(t('Speichern und weiter')) ?>
    </form>

<?php elseif ($schritt === 'klassen'): ?>
    <form method="post" action="<?= e(url("turnier/$tid/klassen/vorlage")) ?>" class="karte formular">
        <?= csrf_feld() ?><?= $weiterFeld ?>
        <?php if ($klassen > 0): ?>
            <div class="meldung meldung-erfolg"><?= th('{n} Klassen sind angelegt. Anpassen (Alter, Graduierung, Modus, Mindestanzahl) unter <a href="{url}" target="_blank">Klassen ↗</a> – oder eine weitere Vorlage ergänzen.', ['n' => (int) $klassen, 'url' => url("turnier/$tid/klassen")]) ?></div>
        <?php else: ?>
            <p><?= te('Die Klassen bestimmen, wer gegen wen antritt (Alter am Turniertag, Geschlecht, Graduierung, Disziplin). Am schnellsten: eine Vorlage laden und danach anpassen. Teilnehmer werden später automatisch zugeordnet.') ?></p>
        <?php endif; ?>
        <label><?= te('Vorlage') ?>
            <select name="vorlage"><?php foreach ($vorlagen as $schluessel => $text): ?><option value="<?= e($schluessel) ?>"><?= e($text) ?></option><?php endforeach; ?></select></label>
        <p class="hilfe"><?= te('Bereits vorhandene Klassen (gleiches Kürzel) bleiben unverändert.') ?></p>
        <?= $fuss($klassen > 0 ? t('Vorlage ergänzen und weiter') : t('Vorlage laden und weiter')) ?>
    </form>
    <?php if ($klassen > 0): ?><p class="knopfleiste"><a class="knopf primaer" href="<?= e(url("turnier/$tid/assistent/$naechster")) ?>"><?= te('Weiter ohne Vorlage →') ?></a></p><?php endif; ?>

<?php elseif ($schritt === 'tatamis'): ?>
    <form method="post" action="<?= e(url("turnier/$tid/zugaenge")) ?>" class="karte formular">
        <?= csrf_feld() ?><?= $weiterFeld ?>
        <p><?= th('Korrekturen bereits gesendeter Ergebnisse am Tablet brauchen die <strong>Leitungs-PIN</strong> (4–8 Ziffern). Tablets und Ausruf koppeln Sie im nächsten Schritt per QR-Code – Tatami-PINs sind nur für die Notlösung „Anmeldung per PIN“ nötig. Leere Felder lassen bestehende PINs unverändert.') ?></p>
        <label><?= te('Leitungs-PIN') ?> <input name="leitung_pin" inputmode="numeric" pattern="\d{4,8}" autocomplete="off" style="max-width:220px"
            placeholder="<?= !empty($turnier['leitung_pin_hash']) ? te('gesetzt – zum Ändern neu eingeben') : te('noch nicht gesetzt') ?>"></label>
        <table class="tabelle">
            <thead><tr><th><?= te('Nr.') ?></th><th><?= te('Bezeichnung') ?></th><th><?= te('PIN (Notlösung)') ?></th></tr></thead>
            <tbody>
            <?php foreach ($tatamis as $t): ?>
                <tr>
                    <td><?= e($t['nummer']) ?></td>
                    <td><input name="tatami[<?= $t['id'] ?>][bezeichnung]" value="<?= e($t['bezeichnung']) ?>"><input type="hidden" name="tatami[<?= $t['id'] ?>][stream_url]" value="<?= e($t['stream_url']) ?>"></td>
                    <td><input name="tatami[<?= $t['id'] ?>][pin]" inputmode="numeric" pattern="\d{4,8}" autocomplete="off" placeholder="<?= $t['pin_hash'] ? '••••' : te('fehlt') ?>"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <label><?= te('Aufstellung für den Ausruf') ?>
            <select name="aka_seite" style="max-width:340px">
                <option value="rechts"<?= sel('rechts', $akaSeite) ?>><?= te('AKA rechts · SHIRO links') ?></option>
                <option value="links"<?= sel('links', $akaSeite) ?>><?= te('AKA links · SHIRO rechts') ?></option>
            </select></label>
        <label class="check"><input type="checkbox" name="kampfuhr_aktiv" value="1"<?= chk($uhr['aktiv']) ?>> <?= te('Kampfuhr für Kumite am Tablet anzeigen') ?></label>
        <input type="hidden" name="kampfuhr_minuten" value="<?= e((string) $uhr['minuten']) ?>">
        <input type="hidden" name="kampfuhr_minuten_finale" value="<?= e((string) $uhr['minuten_finale']) ?>">
        <?php if ($uhr['ton']): ?><input type="hidden" name="kampfuhr_ton" value="1"><?php endif; ?>
        <p class="hilfe"><?= th('Kampfzeiten, Signalton und Livestream-Adressen unter <a href="{url}" target="_blank">Einstellungen ↗</a>.', ['url' => url("turnier/$tid/einstellungen")]) ?></p>
        <?= $fuss(t('Speichern und weiter')) ?>
    </form>

<?php elseif ($schritt === 'geraete'): ?>
    <form method="post" action="<?= e(url("turnier/$tid/geraete/pin")) ?>" class="karte formular">
        <?= csrf_feld() ?><?= $weiterFeld ?>
        <p><?= th('Am Turniertag scannt jedes Tablet (Tisch) und jedes Handy (Ausruf) den <strong>QR-Code seiner Tatami</strong>; die Wettkampfleitung gibt das Gerät unter <a href="{url}" target="_blank">Geräte koppeln ↗</a> frei. Die QR-Codes lassen sich vorab ausdrucken.', ['url' => url("turnier/$tid/geraete")]) ?></p>
        <?php $pinErlaubt = (bool) (int) $turnier['tisch_pin_erlaubt']; ?>
        <div class="meldung meldung-hinweis">
            <strong><?= te('Nur für den Notfall.') ?></strong>
            <?= te('Die Anmeldung per PIN ist für einen Offline-Betrieb gedacht (Hallen-Laptop bzw. lokal im Hallen-WLAN), wenn ein Gerät keine Kamera hat oder die Kopplung nicht klappt.') ?>
            <?= te('Online ist davon abzuraten: Wer die PIN kennt, kann von überall Ergebnisse eintragen. Empfohlen ist die Kopplung per QR-Code mit Freigabe durch die Wettkampfleitung.') ?>
        </div>
        <label class="check"><input type="checkbox" name="erlaubt" value="1"<?= $pinErlaubt ? ' checked' : '' ?> data-pin-schalter> <?= te('Anmeldung per PIN erlauben (Notlösung)') ?></label>
        <label class="check pin-risiko"><input type="checkbox" name="risiko" value="1"<?= $pinErlaubt ? ' checked' : '' ?>> <span><?= te('Ich habe verstanden: nur im Notfall, bevorzugt offline – online lieber per QR-Code koppeln.') ?></span></label>
        <?= $fuss(t('Speichern und weiter')) ?>
    </form>

<?php elseif ($schritt === 'anmeldung'): ?>
    <form method="post" action="<?= e(url("turnier/$tid/anmeldungen/einstellungen")) ?>" class="karte formular">
        <?= csrf_feld() ?><?= $weiterFeld ?>
        <p><?= te('Dojos melden ihre Teilnehmer selbst über einen Link, den Sie ihnen schicken. Hier legen Sie fest, was ein Start kostet.') ?>
            <?= te('Leer bzw. 0 = kein Startgeld.') ?></p>
        <div class="zeile">
            <?php foreach (AnmeldungService::POSTEN as $schluessel => $text): ?>
                <label><?= e(t($text)) ?> (<?= str_ends_with($schluessel, '_team') ? te('€ je Team') : te('€ je Start') ?>)
                    <input name="<?= $schluessel ?>" inputmode="decimal" value="<?= e($euroFeld($einstellungen[$schluessel])) ?>" placeholder="0,00"></label>
            <?php endforeach; ?>
        </div>
        <label><?= te('Hinweis auf der Anmeldeseite') ?> <span class="hilfe"><?= te('z. B. Zahlungsfrist oder Barzahlung am Turniertag') ?></span>
            <textarea name="hinweis" rows="2"><?= e($einstellungen['hinweis']) ?></textarea></label>
        <label class="check"><input type="checkbox" name="zahlung_vor_freigabe" value="1"<?= chk($einstellungen['zahlung_vor_freigabe']) ?>> <?= te('Freigabe erst nach Zahlungseingang') ?></label>
        <label class="check"><input type="checkbox" name="rechnung_fuer_dojos" value="1"<?= chk($einstellungen['rechnung_fuer_dojos']) ?>> <?= te('Dojos können nach Zahlungseingang selbst eine Rechnung abrufen') ?>
            <span class="hilfe"><?= te('nicht vorgeschrieben – die Anmeldeübersicht gilt als Anmelde- und Zahlungsbestätigung') ?></span></label>
        <?= $fuss(t('Speichern und weiter')) ?>
    </form>

<?php elseif ($schritt === 'zahlung'): ?>
    <form method="post" action="<?= e(url("turnier/$tid/rechnungen/einstellungen")) ?>" class="karte formular">
        <?= csrf_feld() ?><?= $weiterFeld ?>
        <?php if ($stand['zahlung']['erledigt'] === null): ?>
            <div class="meldung meldung-hinweis"><?= te('Für dieses Turnier ist kein Startgeld festgelegt – dieser Schritt kann übersprungen werden.') ?></div>
        <?php endif; ?>
        <p><?= th('Nach dem Absenden sieht jedes Dojo sofort eine <strong>Zahlungsaufforderung</strong> mit diesen Angaben und einem eigenen Verwendungszweck.') ?></p>
        <label><?= te('Empfänger (ausrichtender Verein mit Anschrift)') ?>
            <textarea name="absender" rows="3" placeholder="Karateverein Musterstadt e. V.&#10;Musterstraße 1&#10;12345 Musterstadt"><?= e($zahlung['absender']) ?></textarea></label>
        <label><?= te('Bankverbindung') ?>
            <textarea name="bank" rows="3" placeholder="IBAN DE00 0000 0000 0000 0000 00&#10;BIC XXXXDEXX · Musterbank"><?= e($zahlung['bank']) ?></textarea></label>
        <?= $this->teil('teile/girocode_pruefung', ['absender' => $zahlung['absender'], 'bank' => $zahlung['bank']]) ?>
        <div class="zeile">
            <label><?= te('Zahlungsziel (Tage)') ?> <input type="number" name="zahlungsziel" min="0" max="365" value="<?= (int) $zahlung['zahlungsziel'] ?>"></label>
            <label><?= te('Präfix für Verwendungszweck und Rechnungsnummer') ?> <input name="praefix" value="<?= e($zahlung['praefix']) ?>" maxlength="20"></label>
        </div>
        <input type="hidden" name="datum" value="<?= e($zahlung['datum']) ?>">
        <input type="hidden" name="text" value="<?= e($zahlung['text']) ?>">
        <label><?= te('Steuerhinweis') ?> <span class="hilfe"><?= te('optional, z. B. Kleinunternehmerregelung – bitte mit dem Kassenwart klären') ?></span>
            <input name="steuerhinweis" value="<?= e($zahlung['steuerhinweis']) ?>" maxlength="300"></label>
        <?= $fuss(t('Speichern und weiter')) ?>
    </form>

<?php elseif ($schritt === 'portal'): ?>
    <form method="post" action="<?= e(url("turnier/$tid/coach-einstellungen")) ?>" class="karte formular">
        <?= csrf_feld() ?><?= $weiterFeld ?>
        <p><?= te('Coaches sehen im Portal nur ihre eigenen Starter: wann sie dran sind, Ergebnisse, Korrekturanträge, Startgeld. Ausgeschaltet bleibt die Online-Anmeldung trotzdem nutzbar.') ?></p>
        <label class="check"><input type="checkbox" name="aktiv" value="1"<?= chk($coach['aktiv']) ?>> <?= te('Coaching-Portal für dieses Turnier freigeben') ?></label>
        <div class="zeile">
            <?php foreach (CoachService::BEREICHE as $b => $text): ?>
                <label class="check"><input type="checkbox" name="bereiche[]" value="<?= e($b) ?>"<?= chk($coach['bereiche'][$b]) ?>> <?= e(t($text)) ?></label>
            <?php endforeach; ?>
        </div>
        <?= $fuss(t('Speichern und weiter')) ?>
    </form>

<?php elseif ($schritt === 'datenschutz'): ?>
    <section class="karte formular">
        <p><?= te('Der ausrichtende Verein ist Verantwortlicher im Sinne der DSGVO. Auf der Seite Datenschutz entstehen die Datenschutzhinweise für Anmeldung und Statusseite, der Baustein für die Ausschreibung sowie Löschfrist und Pseudonymisierung nach dem Turnier.') ?></p>
        <?php if ($stand['datenschutz']['erledigt']): ?>
            <div class="meldung meldung-erfolg"><?= te('Die Datenschutz-Angaben sind eingerichtet.') ?></div>
        <?php endif; ?>
        <?php if ($vorlagen): ?>
            <form method="post" action="<?= e(url("turnier/$tid/datenschutz/uebernehmen")) ?>" class="knopfleiste">
                <?= csrf_feld() ?><?= $weiterFeld ?>
                <label><?= te('Angaben übernehmen aus') ?>
                    <select name="quelle"><?php foreach ($vorlagen as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['name']) ?> (<?= e(datum_de($v['datum'])) ?>)</option><?php endforeach; ?></select></label>
                <button class="knopf primaer" type="submit"><?= te('Übernehmen und weiter') ?></button>
            </form>
        <?php endif; ?>
        <p><a class="knopf<?= $vorlagen ? '' : ' primaer' ?>" href="<?= e(url("turnier/$tid/datenschutz")) ?>" target="_blank"><?= te('Datenschutz-Angaben öffnen ↗') ?></a></p>
        <?= $fuss('') ?>
    </section>

<?php elseif ($schritt === 'urkunden'): ?>
    <section class="karte formular">
        <p><?= te('Urkunden druckt die Software für jede bestätigte Klasse. Untertitel, Unterschriften (auch als Bild), bis zu drei Logos und ein eigenes Hintergrundbild stellen Sie auf einer eigenen Seite ein – mit Vorschau einer Musterurkunde.') ?></p>
        <p class="klein leise"><?= e($stand['urkunden']['text']) ?></p>
        <p><a class="knopf primaer" href="<?= e(url("turnier/$tid/druck/urkunden/gestalten")) ?>" target="_blank"><?= te('Urkunden gestalten ↗') ?></a></p>
        <?= $fuss('') ?>
    </section>

<?php else: ?>
    <section class="karte">
        <h2><?= te('Stand der Einrichtung') ?></h2>
        <table class="tabelle">
            <tbody>
            <?php foreach (TurnierAssistent::SCHRITTE as $s => [$t]): if ($s === 'fertig') { continue; } $z = $stand[$s]['erledigt']; ?>
                <tr>
                    <td><?= $z === true ? '<span class="tag ok">✓ ' . te('erledigt') . '</span>' : ($z === null ? '<span class="tag">' . te('entfällt') . '</span>' : '<span class="tag warn">' . te('offen') . '</span>') ?></td>
                    <td><strong><?= e(t($t)) ?></strong><br><span class="klein"><?= e($stand[$s]['text']) ?></span></td>
                    <td class="rechts"><a href="<?= e(url("turnier/$tid/assistent/$s")) ?>"><?= $z === false ? te('jetzt erledigen') : te('ändern') ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <section class="karte">
        <h2><?= te('Wie geht es weiter?') ?></h2>
        <ol>
            <li><?= th('<strong>Meldephase starten</strong> – erst dann ist die Online-Anmeldung geöffnet. Den Link für die Dojos gibt es danach unter <a href="{url}">Anmeldungen</a>.', ['url' => url("turnier/$tid/anmeldungen")]) ?></li>
            <li><?= th('Optional: <a href="{leitung}">weitere Turnierleitungen ernennen</a>, Teilnehmer per <a href="{import}">Excel/CSV importieren</a>.', ['leitung' => url("turnier/$tid/leitung"), 'import' => url("turnier/$tid/import")]) ?></li>
            <li><?= th('Nach dem Meldeschluss: Anmeldungen freigeben, Klassen prüfen, auslosen, Tatami-Plan – die <a href="{url}">Übersicht</a> zeigt jeweils, was noch offen ist.', ['url' => url("turnier/$tid")]) ?></li>
            <li><?= th('Vor dem Turniertag: <a href="{url}">Handbuch</a> und Handouts für Tischbesatzung und Ausruf, Notfall-Satz drucken.', ['url' => url('hilfe#ablauf')]) ?></li>
        </ol>
        <?php if ($stand['fertig']['erledigt'] === false): ?>
            <form method="post" action="<?= e(url("turnier/$tid/phase")) ?>" class="knopfleiste">
                <?= csrf_feld() ?>
                <input type="hidden" name="phase" value="meldephase">
                <a class="knopf" href="<?= e(url("turnier/$tid")) ?>"><?= te('Später – zur Übersicht') ?></a>
                <button class="knopf primaer" type="submit"><?= te('Meldephase jetzt starten') ?></button>
            </form>
        <?php else: ?>
            <p class="knopfleiste"><a class="knopf primaer" href="<?= e(url("turnier/$tid")) ?>"><?= te('Zur Übersicht') ?></a></p>
        <?php endif; ?>
    </section>
<?php endif; ?>
