<?php
/**
 * Startgeld-Rechnungen an die Dojos (Turnierleitung).
 * @var array<string, mixed> $turnier
 * @var array{absender: string, bank: string, steuerhinweis: string, zahlungsziel: int, praefix: string, datum: string, text: string} $einstellungen
 * @var list<array{verein_id: int, verein: string, nummer: string, summe: int, teilnehmer: int, teams: int}> $uebersicht
 */
use Wettkampf\Meldung\AnmeldungService;

$this->layout('layout', ['titel' => 'Rechnungen', 'turnier' => $turnier, 'aktiv' => 'anmeldungen']);
$tid = (int) $turnier['id'];
$e = $einstellungen;
$fehlt = $e['absender'] === '' || $e['bank'] === '';
?>
<p class="obertitel"><a href="<?= e(url("turnier/$tid/anmeldungen")) ?>">Online-Anmeldungen</a></p>
<div class="titelzeile">
    <h1>Startgeld-Rechnungen <?= info('rechnungen') ?></h1>
    <?php if ($uebersicht): ?>
        <a class="knopf primaer" href="<?= e(url("turnier/$tid/rechnungen/druck")) ?>" target="_blank">Alle Rechnungen drucken / PDF</a>
    <?php endif; ?>
</div>
<p class="leise">Je Dojo eine Rechnung über das Startgeld – berechnet wie in der Startgeld-Übersicht des Coaching-Portals aus den gemeldeten
    Teilnehmern und Teams (ohne Abmeldungen und Nicht-Erschienene) und den Preisen unter
    <a href="<?= e(url("turnier/$tid/anmeldungen")) ?>">Einstellungen und Startgeld</a>.
    Für Turnieranmeldungen ist eine Rechnung nicht vorgeschrieben – diese Seite ist für Vereine gedacht, die eine Rechnung brauchen, und für
    Nachmeldungen. Zu einer einzelnen Online-Anmeldung gibt es die Rechnung in der Anmeldung unter „Zahlung → Rechnung erstellen“.</p>

<div class="zwei-spalten">
    <section class="karte">
        <h2>Dojos</h2>
        <?php if (!$uebersicht): ?>
            <p class="leise">Noch keine Dojos mit Starts.</p>
        <?php else: ?>
            <div class="tabelle-scroll">
            <table class="tabelle">
                <thead><tr><th>Dojo</th><th>Rechnungs-Nr.</th><th class="rechts">Teiln.</th><th class="rechts">Teams</th><th class="rechts">Betrag</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($uebersicht as $r): ?>
                    <tr>
                        <td><?= e($r['verein']) ?></td>
                        <td><code><?= e($r['nummer']) ?></code></td>
                        <td class="rechts"><?= $r['teilnehmer'] ?></td>
                        <td class="rechts"><?= $r['teams'] ?></td>
                        <td class="rechts"><?= e(AnmeldungService::euro($r['summe'])) ?></td>
                        <td class="rechts"><a href="<?= e(url("turnier/$tid/rechnungen/druck", ['verein' => $r['verein_id']])) ?>" target="_blank">Rechnung ↗</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="4">Summe</th><th class="rechts"><?= e(AnmeldungService::euro(array_sum(array_column($uebersicht, 'summe')))) ?></th><th></th></tr></tfoot>
            </table>
            </div>
        <?php endif; ?>
    </section>

    <form method="post" action="<?= e(url("turnier/$tid/rechnungen/einstellungen")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2>Zahlungs- und Rechnungsangaben</h2>
        <p class="hilfe">Gelten auch für die Zahlungsaufforderung, die Dojos nach der Online-Anmeldung sehen (Empfänger, Bankverbindung, Zahlungsziel; Verwendungszweck mit dem Präfix).</p>
        <?php if ($fehlt): ?><div class="meldung meldung-hinweis">Bitte Absender und Bankverbindung eintragen – sie gehören auf jede Rechnung.</div><?php endif; ?>
        <label>Absender (ausrichtender Verein mit Anschrift)
            <textarea name="absender" rows="4" placeholder="Karateverein Musterstadt e. V.&#10;Musterstraße 1&#10;12345 Musterstadt"><?= e($e['absender']) ?></textarea></label>
        <label>Bankverbindung
            <textarea name="bank" rows="3" placeholder="IBAN DE00 0000 0000 0000 0000 00&#10;BIC XXXXDEXX · Musterbank"><?= e($e['bank']) ?></textarea></label>
        <?= $this->teil('teile/girocode_pruefung', ['absender' => $e['absender'], 'bank' => $e['bank']]) ?>
        <div class="zeile">
            <label>Präfix der Rechnungsnummer <span class="hilfe">dahinter Turnier- und Vereinsnummer</span>
                <input name="praefix" value="<?= e($e['praefix']) ?>" maxlength="20"></label>
            <label>Rechnungsdatum <span class="hilfe">leer = Tag des Drucks</span>
                <input type="date" name="datum" value="<?= e($e['datum']) ?>"></label>
            <label>Zahlungsziel (Tage)
                <input type="number" name="zahlungsziel" min="0" max="365" value="<?= (int) $e['zahlungsziel'] ?>"></label>
        </div>
        <label>Steuerhinweis <span class="hilfe">z. B. „Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.“ – bitte mit dem Kassenwart klären</span>
            <input name="steuerhinweis" value="<?= e($e['steuerhinweis']) ?>" maxlength="300"></label>
        <label>Zusätzlicher Text <span class="hilfe">optional, z. B. Dank oder Hinweis zur Barzahlung am Turniertag</span>
            <textarea name="text" rows="2"><?= e($e['text']) ?></textarea></label>
        <p><button class="knopf primaer" type="submit">Speichern</button></p>
    </form>
</div>
