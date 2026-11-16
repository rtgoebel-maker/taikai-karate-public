<?php
/**
 * Startgeld-Rechnungen an die Dojos (Turnierleitung).
 * @var array<string, mixed> $turnier
 * @var array{absender: string, bank: string, steuerhinweis: string, zahlungsziel: int, praefix: string, datum: string, text: string, starterliste: bool, quittung: bool} $einstellungen
 * @var list<array{verein_id: int, verein: string, nummer: string, summe: int, teilnehmer: int, teams: int}> $uebersicht
 */
use Wettkampf\Meldung\AnmeldungService;

$this->layout('layout', ['titel' => t('Rechnungen'), 'turnier' => $turnier, 'aktiv' => 'anmeldungen']);
$tid = (int) $turnier['id'];
$e = $einstellungen;
$q = $e['quittung'];
$fehlt = $e['absender'] === '' || (!$q && $e['bank'] === '');
?>
<p class="obertitel"><a href="<?= e(url("turnier/$tid/anmeldungen")) ?>"><?= te('Online-Anmeldungen') ?></a></p>
<div class="titelzeile">
    <h1><?= $q ? te('Startgeld-Quittungen') : te('Startgeld-Rechnungen') ?> <?= info('rechnungen') ?></h1>
    <?php if ($uebersicht): ?>
        <a class="knopf primaer" href="<?= e(url("turnier/$tid/rechnungen/druck")) ?>" target="_blank"><?= $q ? te('Alle Quittungen drucken / PDF') : te('Alle Rechnungen drucken / PDF') ?></a>
    <?php endif; ?>
</div>
<?php if ($q): ?>
    <div class="meldung meldung-hinweis"><?= th('<strong>Gemeinnütziger Ausrichter:</strong> Statt Rechnungen gibt es Quittungen. Je Dojo eine Seite – bitte <strong>zweimal drucken</strong>, beide unterschreiben: eine erhält das Dojo, eine bleibt beim Kassenwart (Beleg, 8 Jahre aufbewahren). Ist die Zahlung in der Software erfasst, steht das Datum schon darauf; sonst (z. B. bar am Turniertag) von Hand ausfüllen.') ?></div>
<?php endif; ?>
<p class="leise"><?= th('Je Dojo eine Rechnung über das Startgeld – berechnet wie in der Startgeld-Übersicht des Coaching-Portals aus den gemeldeten Teilnehmern und Teams (ohne Abmeldungen und Nicht-Erschienene) und den Preisen unter <a href="{link}">Einstellungen und Startgeld</a>. Für Turnieranmeldungen ist eine Rechnung nicht vorgeschrieben – diese Seite ist für Vereine gedacht, die eine Rechnung brauchen, und für Nachmeldungen. Zu einer einzelnen Online-Anmeldung gibt es die Rechnung in der Anmeldung unter „Zahlung → Rechnung erstellen“.', ['link' => url("turnier/$tid/anmeldungen")]) ?></p>

<div class="zwei-spalten">
    <section class="karte">
        <h2><?= te('Dojos') ?></h2>
        <?php if (!$uebersicht): ?>
            <p class="leise"><?= te('Noch keine Dojos mit Starts.') ?></p>
        <?php else: ?>
            <div class="tabelle-scroll">
            <table class="tabelle">
                <thead><tr><th><?= te('Dojo') ?></th><th><?= $q ? te('Quittungs-Nr.') : te('Rechnungs-Nr.') ?></th><th class="rechts"><?= te('Teiln.') ?></th><th class="rechts"><?= te('Teams') ?></th><th class="rechts"><?= te('Betrag') ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($uebersicht as $r): ?>
                    <tr>
                        <td><?= e($r['verein']) ?></td>
                        <td><code><?= e($r['nummer']) ?></code></td>
                        <td class="rechts"><?= $r['teilnehmer'] ?></td>
                        <td class="rechts"><?= $r['teams'] ?></td>
                        <td class="rechts"><?= e(AnmeldungService::euro($r['summe'])) ?></td>
                        <td class="rechts"><a href="<?= e(url("turnier/$tid/rechnungen/druck", ['verein' => $r['verein_id']])) ?>" target="_blank"><?= $q ? te('Quittung ↗') : te('Rechnung ↗') ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="4"><?= te('Summe') ?></th><th class="rechts"><?= e(AnmeldungService::euro(array_sum(array_column($uebersicht, 'summe')))) ?></th><th></th></tr></tfoot>
            </table>
            </div>
        <?php endif; ?>
    </section>

    <form method="post" action="<?= e(url("turnier/$tid/rechnungen/einstellungen")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2><?= te('Zahlungs- und Rechnungsangaben') ?></h2>
        <p class="hilfe"><?= te('Gelten auch für die Zahlungsaufforderung, die Dojos nach der Online-Anmeldung sehen (Empfänger, Bankverbindung, Zahlungsziel; Verwendungszweck mit dem Präfix).') ?></p>
        <?php if ($fehlt): ?><div class="meldung meldung-hinweis"><?= $q ? te('Bitte den Absender eintragen – er gehört auf jede Quittung.') : te('Bitte Absender und Bankverbindung eintragen – sie gehören auf jede Rechnung.') ?></div><?php endif; ?>
        <label class="check"><input type="checkbox" name="quittung" value="1"<?= chk($q) ?>>
            <span><strong><?= te('Ausrichter ist ein gemeinnütziger Verein – Quittungen statt Rechnungen') ?></strong>
                <span class="hilfe"><?= te('Startgelder (Teilnehmergebühren) für Sportveranstaltungen gemeinnütziger Vereine sind umsatzsteuerfrei (§ 4 Nr. 22 b UStG); eine Rechnung ist dann nicht vorgeschrieben (§ 14 Abs. 2 UStG) – auch nicht über 250 €. Voraussetzung: anerkannte Gemeinnützigkeit (Freistellungsbescheid). Die Quittung nennt die Posten und den Steuerhinweis und wird unterschrieben. Keine Steuerberatung – im Zweifel mit dem Kassenwart klären.') ?></span></span></label>
        <label><?= te('Absender (ausrichtender Verein mit Anschrift)') ?>
            <textarea name="absender" rows="4" placeholder="Karateverein Musterstadt e. V.&#10;Musterstraße 1&#10;12345 Musterstadt"><?= e($e['absender']) ?></textarea></label>
        <label><?= te('Bankverbindung') ?>
            <textarea name="bank" rows="3" placeholder="IBAN DE00 0000 0000 0000 0000 00&#10;BIC XXXXDEXX · Musterbank"><?= e($e['bank']) ?></textarea></label>
        <?= $this->teil('teile/girocode_pruefung', ['absender' => $e['absender'], 'bank' => $e['bank']]) ?>
        <div class="zeile">
            <label><?= $q ? te('Präfix der Quittungsnummer') : te('Präfix der Rechnungsnummer') ?> <span class="hilfe"><?= te('dahinter Turnier- und Vereinsnummer') ?></span>
                <input name="praefix" value="<?= e($e['praefix']) ?>" maxlength="20"></label>
            <label><?= te('Rechnungsdatum') ?> <span class="hilfe"><?= te('leer = Tag des Drucks') ?></span>
                <input type="date" name="datum" value="<?= e($e['datum']) ?>"></label>
            <label><?= te('Zahlungsziel (Tage)') ?>
                <input type="number" name="zahlungsziel" min="0" max="365" value="<?= (int) $e['zahlungsziel'] ?>"></label>
        </div>
        <label><?= te('Steuerhinweis') ?> <span class="hilfe"><?= te('z. B. „Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.“ – bitte mit dem Kassenwart klären') ?></span>
            <input name="steuerhinweis" value="<?= e($e['steuerhinweis']) ?>" maxlength="300"></label>
        <label><?= te('Zusätzlicher Text') ?> <span class="hilfe"><?= te('optional, z. B. Dank oder Hinweis zur Barzahlung am Turniertag') ?></span>
            <textarea name="text" rows="2"><?= e($e['text']) ?></textarea></label>
        <label class="check"><input type="checkbox" name="starterliste" value="1"<?= chk($e['starterliste']) ?>>
            <span><?= te('Starterliste als Anlage auf der Rechnung (Namen der gemeldeten Starter)') ?>
                <span class="hilfe"><?= te('Nicht nötig – auf eine Rechnung gehören Art und Menge der Leistung (z. B. 12 × Startgeld Kata). Rechnungen werden 8 Jahre aufbewahrt; Namen, oft von Kindern, gehören deshalb besser nicht darauf. Die Dojos sehen ihre Starter in der Anmeldeübersicht. Im Buchhaltungsexport fehlt die Liste immer.') ?></span></span></label>
        <p><button class="knopf primaer" type="submit"><?= te('Speichern') ?></button></p>
    </form>
</div>
