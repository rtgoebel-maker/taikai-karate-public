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

$this->layout('layout', ['titel' => 'Einrichtungs-Assistent', 'turnier' => $turnier, 'aktiv' => 'uebersicht']);
$tid = (int) $turnier['id'];
$weiterPfad = "turnier/$tid/assistent/$naechster";
$weiterFeld = '<input type="hidden" name="assistent" value="' . e($weiterPfad) . '">';
[$titel, $kurz] = TurnierAssistent::SCHRITTE[$schritt];
$nr = array_search($schritt, array_keys(TurnierAssistent::SCHRITTE), true) + 1;
$euroFeld = static fn (int $cent) => $cent ? str_replace('.', ',', sprintf('%.2f', $cent / 100)) : '';
/** Fußleiste: zurück, überspringen, speichern */
$fuss = static function (string $knopf) use ($vorheriger, $tid, $weiterPfad, $schritt): string {
    return '<div class="assistent-fuss">'
        . ($vorheriger !== null ? '<a class="knopf" href="' . e(url("turnier/$tid/assistent/$vorheriger")) . '">← Zurück</a>' : '<span></span>')
        . '<span class="knopfleiste">'
        . ($schritt !== 'fertig' ? '<a href="' . e(url($weiterPfad)) . '">Überspringen</a>' : '')
        . ($knopf !== '' ? '<button class="knopf primaer" type="submit">' . e($knopf) . '</button>' : '')
        . '</span></div>';
};
?>
<p class="obertitel">Einrichtungs-Assistent · <?= e($turnier['name']) ?></p>
<h1>Schritt <?= $nr ?>: <?= e($titel) ?></h1>

<ol class="assistent-schritte">
    <?php $i = 0; foreach (TurnierAssistent::SCHRITTE as $s => [$t, $k]): $i++; $z = $stand[$s]['erledigt']; ?>
        <li class="<?= $s === $schritt ? 'hier' : '' ?> <?= $z === true ? 'erledigt' : ($z === null ? 'entfaellt' : 'offen') ?>">
            <a href="<?= e(url("turnier/$tid/assistent/$s")) ?>" title="<?= e($stand[$s]['text']) ?>">
                <span class="nr"><?= $z === true && $s !== $schritt ? '✓' : $i ?></span><span class="titel"><?= e($t) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ol>
<p class="leise"><?= e($kurz) ?>. Alles lässt sich später unter Einstellungen bzw. auf den jeweiligen Seiten ändern.</p>

<?php if ($schritt === 'grunddaten'): ?>
    <form method="post" action="<?= e(url("turnier/$tid/einstellungen")) ?>" class="karte formular">
        <?= csrf_feld() ?><?= $weiterFeld ?>
        <label>Name <input name="name" value="<?= e($turnier['name']) ?>" required></label>
        <div class="zeile">
            <label>Datum <input type="date" name="datum" value="<?= e($turnier['datum']) ?>" required></label>
            <label>Anzahl Tatamis <input type="number" name="anzahl_tatamis" min="1" max="20" value="<?= e($turnier['anzahl_tatamis']) ?>" required></label>
        </div>
        <div class="zeile">
            <label>Ort <input name="ort" value="<?= e($turnier['ort']) ?>"></label>
            <label>Halle <input name="halle" value="<?= e($turnier['halle']) ?>"></label>
        </div>
        <div class="zeile">
            <label>Mindestgraduierung
                <select name="min_graduierung"><option value="">keine</option>
                    <?php foreach (Graduierung::auswahl() as $g => $text): ?><option value="<?= $g ?>"<?= sel($g, $turnier['min_graduierung']) ?>><?= e($text) ?></option><?php endforeach; ?>
                </select></label>
            <label>Meldeschluss <span class="hilfe">bis dahin können Dojos online melden und ändern</span>
                <input type="date" name="meldeschluss" value="<?= e($turnier['meldeschluss']) ?>"></label>
        </div>
        <label class="check"><input type="checkbox" name="oeffentlich" value="1"<?= chk($turnier['oeffentlich']) ?>> Öffentliche Statusseite (nur Name und Verein)</label>
        <?= $fuss('Speichern und weiter') ?>
    </form>

<?php elseif ($schritt === 'klassen'): ?>
    <form method="post" action="<?= e(url("turnier/$tid/klassen/vorlage")) ?>" class="karte formular">
        <?= csrf_feld() ?><?= $weiterFeld ?>
        <?php if ($klassen > 0): ?>
            <div class="meldung meldung-erfolg"><?= (int) $klassen ?> Klassen sind angelegt. Anpassen (Alter, Graduierung, Modus, Mindestanzahl) unter
                <a href="<?= e(url("turnier/$tid/klassen")) ?>" target="_blank">Klassen ↗</a> – oder eine weitere Vorlage ergänzen.</div>
        <?php else: ?>
            <p>Die Klassen bestimmen, wer gegen wen antritt (Alter am Turniertag, Geschlecht, Graduierung, Disziplin). Am schnellsten: eine Vorlage laden
                und danach anpassen. Teilnehmer werden später automatisch zugeordnet.</p>
        <?php endif; ?>
        <label>Vorlage
            <select name="vorlage"><?php foreach ($vorlagen as $schluessel => $text): ?><option value="<?= e($schluessel) ?>"><?= e($text) ?></option><?php endforeach; ?></select></label>
        <p class="hilfe">Bereits vorhandene Klassen (gleiches Kürzel) bleiben unverändert.</p>
        <?= $fuss($klassen > 0 ? 'Vorlage ergänzen und weiter' : 'Vorlage laden und weiter') ?>
    </form>
    <?php if ($klassen > 0): ?><p class="knopfleiste"><a class="knopf primaer" href="<?= e(url("turnier/$tid/assistent/$naechster")) ?>">Weiter ohne Vorlage →</a></p><?php endif; ?>

<?php elseif ($schritt === 'tatamis'): ?>
    <form method="post" action="<?= e(url("turnier/$tid/zugaenge")) ?>" class="karte formular">
        <?= csrf_feld() ?><?= $weiterFeld ?>
        <p>Die Tischbesatzung meldet ein Tablet mit der <strong>PIN ihrer Tatami</strong> an. Korrekturen bereits gesendeter Ergebnisse brauchen die
            <strong>Leitungs-PIN</strong>. PINs: 4–8 Ziffern. Leere Felder lassen bestehende PINs unverändert.</p>
        <label>Leitungs-PIN <input name="leitung_pin" inputmode="numeric" pattern="\d{4,8}" autocomplete="off" style="max-width:220px"
            placeholder="<?= !empty($turnier['leitung_pin_hash']) ? 'gesetzt – zum Ändern neu eingeben' : 'noch nicht gesetzt' ?>"></label>
        <table class="tabelle">
            <thead><tr><th>Nr.</th><th>Bezeichnung</th><th>PIN</th></tr></thead>
            <tbody>
            <?php foreach ($tatamis as $t): ?>
                <tr>
                    <td><?= e($t['nummer']) ?></td>
                    <td><input name="tatami[<?= $t['id'] ?>][bezeichnung]" value="<?= e($t['bezeichnung']) ?>"><input type="hidden" name="tatami[<?= $t['id'] ?>][stream_url]" value="<?= e($t['stream_url']) ?>"></td>
                    <td><input name="tatami[<?= $t['id'] ?>][pin]" inputmode="numeric" pattern="\d{4,8}" autocomplete="off" placeholder="<?= $t['pin_hash'] ? '••••' : 'fehlt' ?>"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <label>Aufstellung für den Ausruf
            <select name="aka_seite" style="max-width:340px">
                <option value="rechts"<?= sel('rechts', $akaSeite) ?>>AKA rechts · SHIRO links</option>
                <option value="links"<?= sel('links', $akaSeite) ?>>AKA links · SHIRO rechts</option>
            </select></label>
        <label class="check"><input type="checkbox" name="kampfuhr_aktiv" value="1"<?= chk($uhr['aktiv']) ?>> Kampfuhr für Kumite am Tablet anzeigen</label>
        <input type="hidden" name="kampfuhr_minuten" value="<?= e((string) $uhr['minuten']) ?>">
        <input type="hidden" name="kampfuhr_minuten_finale" value="<?= e((string) $uhr['minuten_finale']) ?>">
        <?php if ($uhr['ton']): ?><input type="hidden" name="kampfuhr_ton" value="1"><?php endif; ?>
        <p class="hilfe">Kampfzeiten, Signalton und Livestream-Adressen unter <a href="<?= e(url("turnier/$tid/einstellungen")) ?>" target="_blank">Einstellungen ↗</a>.</p>
        <?= $fuss('Speichern und weiter') ?>
    </form>

<?php elseif ($schritt === 'anmeldung'): ?>
    <form method="post" action="<?= e(url("turnier/$tid/anmeldungen/einstellungen")) ?>" class="karte formular">
        <?= csrf_feld() ?><?= $weiterFeld ?>
        <p>Dojos melden ihre Teilnehmer selbst über einen Link, den Sie ihnen schicken. Hier legen Sie fest, was ein Start kostet.
            Leer bzw. 0 = kein Startgeld.</p>
        <div class="zeile">
            <?php foreach (AnmeldungService::POSTEN as $schluessel => $text): ?>
                <label><?= e($text) ?> (€ je <?= str_ends_with($schluessel, '_team') ? 'Team' : 'Start' ?>)
                    <input name="<?= $schluessel ?>" inputmode="decimal" value="<?= e($euroFeld($einstellungen[$schluessel])) ?>" placeholder="0,00"></label>
            <?php endforeach; ?>
        </div>
        <label>Hinweis auf der Anmeldeseite <span class="hilfe">z. B. Zahlungsfrist oder Barzahlung am Turniertag</span>
            <textarea name="hinweis" rows="2"><?= e($einstellungen['hinweis']) ?></textarea></label>
        <label class="check"><input type="checkbox" name="zahlung_vor_freigabe" value="1"<?= chk($einstellungen['zahlung_vor_freigabe']) ?>> Freigabe erst nach Zahlungseingang</label>
        <label class="check"><input type="checkbox" name="rechnung_fuer_dojos" value="1"<?= chk($einstellungen['rechnung_fuer_dojos']) ?>> Dojos können nach Zahlungseingang selbst eine Rechnung abrufen
            <span class="hilfe">nicht vorgeschrieben – die Anmeldeübersicht gilt als Anmelde- und Zahlungsbestätigung</span></label>
        <?= $fuss('Speichern und weiter') ?>
    </form>

<?php elseif ($schritt === 'zahlung'): ?>
    <form method="post" action="<?= e(url("turnier/$tid/rechnungen/einstellungen")) ?>" class="karte formular">
        <?= csrf_feld() ?><?= $weiterFeld ?>
        <?php if ($stand['zahlung']['erledigt'] === null): ?>
            <div class="meldung meldung-hinweis">Für dieses Turnier ist kein Startgeld festgelegt – dieser Schritt kann übersprungen werden.</div>
        <?php endif; ?>
        <p>Nach dem Absenden sieht jedes Dojo sofort eine <strong>Zahlungsaufforderung</strong> mit diesen Angaben und einem eigenen Verwendungszweck.</p>
        <label>Empfänger (ausrichtender Verein mit Anschrift)
            <textarea name="absender" rows="3" placeholder="Karateverein Musterstadt e. V.&#10;Musterstraße 1&#10;12345 Musterstadt"><?= e($zahlung['absender']) ?></textarea></label>
        <label>Bankverbindung
            <textarea name="bank" rows="3" placeholder="IBAN DE00 0000 0000 0000 0000 00&#10;BIC XXXXDEXX · Musterbank"><?= e($zahlung['bank']) ?></textarea></label>
        <?= $this->teil('teile/girocode_pruefung', ['absender' => $zahlung['absender'], 'bank' => $zahlung['bank']]) ?>
        <div class="zeile">
            <label>Zahlungsziel (Tage) <input type="number" name="zahlungsziel" min="0" max="365" value="<?= (int) $zahlung['zahlungsziel'] ?>"></label>
            <label>Präfix für Verwendungszweck und Rechnungsnummer <input name="praefix" value="<?= e($zahlung['praefix']) ?>" maxlength="20"></label>
        </div>
        <input type="hidden" name="datum" value="<?= e($zahlung['datum']) ?>">
        <input type="hidden" name="text" value="<?= e($zahlung['text']) ?>">
        <label>Steuerhinweis <span class="hilfe">optional, z. B. Kleinunternehmerregelung – bitte mit dem Kassenwart klären</span>
            <input name="steuerhinweis" value="<?= e($zahlung['steuerhinweis']) ?>" maxlength="300"></label>
        <?= $fuss('Speichern und weiter') ?>
    </form>

<?php elseif ($schritt === 'portal'): ?>
    <form method="post" action="<?= e(url("turnier/$tid/coach-einstellungen")) ?>" class="karte formular">
        <?= csrf_feld() ?><?= $weiterFeld ?>
        <p>Coaches sehen im Portal nur ihre eigenen Starter: wann sie dran sind, Ergebnisse, Korrekturanträge, Startgeld. Ausgeschaltet bleibt die
            Online-Anmeldung trotzdem nutzbar.</p>
        <label class="check"><input type="checkbox" name="aktiv" value="1"<?= chk($coach['aktiv']) ?>> Coaching-Portal für dieses Turnier freigeben</label>
        <div class="zeile">
            <?php foreach (CoachService::BEREICHE as $b => $text): ?>
                <label class="check"><input type="checkbox" name="bereiche[]" value="<?= e($b) ?>"<?= chk($coach['bereiche'][$b]) ?>> <?= e($text) ?></label>
            <?php endforeach; ?>
        </div>
        <?= $fuss('Speichern und weiter') ?>
    </form>

<?php else: ?>
    <section class="karte">
        <h2>Stand der Einrichtung</h2>
        <table class="tabelle">
            <tbody>
            <?php foreach (TurnierAssistent::SCHRITTE as $s => [$t]): if ($s === 'fertig') { continue; } $z = $stand[$s]['erledigt']; ?>
                <tr>
                    <td><?= $z === true ? '<span class="tag ok">✓ erledigt</span>' : ($z === null ? '<span class="tag">entfällt</span>' : '<span class="tag warn">offen</span>') ?></td>
                    <td><strong><?= e($t) ?></strong><br><span class="klein"><?= e($stand[$s]['text']) ?></span></td>
                    <td class="rechts"><a href="<?= e(url("turnier/$tid/assistent/$s")) ?>"><?= $z === false ? 'jetzt erledigen' : 'ändern' ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <section class="karte">
        <h2>Wie geht es weiter?</h2>
        <ol>
            <li><strong>Meldephase starten</strong> – erst dann ist die Online-Anmeldung geöffnet. Den Link für die Dojos gibt es danach unter
                <a href="<?= e(url("turnier/$tid/anmeldungen")) ?>">Anmeldungen</a>.</li>
            <li>Optional: <a href="<?= e(url("turnier/$tid/leitung")) ?>">weitere Turnierleitungen ernennen</a>,
                <a href="<?= e(url("turnier/$tid/druck")) ?>">Logos und Unterschriften für Urkunden</a> hochladen,
                Teilnehmer per <a href="<?= e(url("turnier/$tid/import")) ?>">Excel/CSV importieren</a>.</li>
            <li>Nach dem Meldeschluss: Anmeldungen freigeben, Klassen prüfen, auslosen, Tatami-Plan – die
                <a href="<?= e(url("turnier/$tid")) ?>">Übersicht</a> zeigt jeweils, was noch offen ist.</li>
            <li>Vor dem Turniertag: <a href="<?= e(url('hilfe#ablauf')) ?>">Handbuch</a> und Handouts für Tischbesatzung und Ausruf, Notfall-Satz drucken.</li>
        </ol>
        <?php if ($stand['fertig']['erledigt'] === false): ?>
            <form method="post" action="<?= e(url("turnier/$tid/phase")) ?>" class="knopfleiste">
                <?= csrf_feld() ?>
                <input type="hidden" name="phase" value="meldephase">
                <a class="knopf" href="<?= e(url("turnier/$tid")) ?>">Später – zur Übersicht</a>
                <button class="knopf primaer" type="submit">Meldephase jetzt starten</button>
            </form>
        <?php else: ?>
            <p class="knopfleiste"><a class="knopf primaer" href="<?= e(url("turnier/$tid")) ?>">Zur Übersicht</a></p>
        <?php endif; ?>
    </section>
<?php endif; ?>
