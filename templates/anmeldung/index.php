<?php
/**
 * Online-Anmeldungen eines Turniers (Wettkampfleitung).
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $anmeldungen
 * @var array<string, mixed> $einstellungen
 * @var string $anmeldelink Anmeldeformular mit Zufallsschlüssel
 * @var string|null $geschlossen
 * @var int $antraegeOffen
 */
use Wettkampf\Meldung\AnmeldungService;

$this->layout('layout', ['titel' => 'Anmeldungen', 'turnier' => $turnier, 'aktiv' => 'anmeldungen']);
$tid = $turnier['id'];
$euroFeld = static fn (int $cent) => $cent ? str_replace('.', ',', sprintf('%.2f', $cent / 100)) : '';
$offen = array_filter($anmeldungen, static fn ($a) => $a['status'] === 'eingereicht');
?>
<div class="titelzeile">
    <h1>Online-Anmeldungen <?= info('anmeldung') ?></h1>
    <?php if ($offen): ?><span class="tag warn"><?= count($offen) ?> zu prüfen</span><?php endif; ?>
    <a class="knopf" href="<?= e(url("turnier/$tid/rechnungen")) ?>">Rechnungen</a>
    <a class="knopf" href="<?= e(url("turnier/$tid/korrekturen")) ?>">Korrekturanträge<?= ($antraegeOffen ?? 0) > 0 ? ' <span class="zaehler">' . (int) $antraegeOffen . '</span>' : '' ?></a>
</div>

<div class="zwei-spalten">
    <section class="karte">
        <h2>Anmeldeseite für die Dojos</h2>
        <?php if ($geschlossen): ?>
            <div class="meldung meldung-hinweis"><?= e($geschlossen) ?></div>
        <?php else: ?>
            <div class="meldung meldung-erfolg">Die Anmeldung ist geöffnet<?= $turnier['meldeschluss'] ? ' bis zum Meldeschluss am ' . e(datum_de($turnier['meldeschluss'])) : '' ?>.</div>
        <?php endif; ?>
        <label class="feld">Link zum Weitergeben an die Dojos
            <input readonly id="anmeldelink" value="<?= e($anmeldelink) ?>" data-auswaehlen data-absolut></label>
        <p class="knopfleiste"><a class="knopf" href="<?= e($anmeldelink) ?>" target="_blank" rel="noopener">Anmeldeseite öffnen ↗</a></p>
        <form method="post" action="<?= e(url("turnier/$tid/anmeldungen/link-erneuern")) ?>" class="knopfleiste" data-bestaetigen="Neuen Anmeldelink erzeugen? Der bisherige funktioniert dann nicht mehr – bereits verschickte Links müssen ersetzt werden.">
            <?= csrf_feld() ?>
            <span class="hilfe">Der Link enthält einen Zufallsschlüssel und ist nicht erratbar. Ist er an die falsche Stelle geraten:</span>
            <button class="knopf klein" type="submit">Neuen Link erzeugen</button>
        </form>
        <p class="hilfe">Jedes Dojo erhält nach dem Absenden einen persönlichen Bearbeitungslink und kann bis zur Freigabe bzw. bis zum Meldeschluss
            (<a href="<?= e(url("turnier/$tid/einstellungen")) ?>">Einstellungen</a>) ändern. Mit der Freigabe werden Verein, Teilnehmer und Teams übernommen
            und automatisch den Klassen zugeordnet.</p>
    </section>

    <form method="post" action="<?= e(url("turnier/$tid/anmeldungen/einstellungen")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2>Einstellungen und Startgeld <?= info('startgeld') ?></h2>
        <p class="hilfe">Die Anmeldeseite ist geöffnet, solange das Turnier in der <strong>Meldephase</strong> ist und der Meldeschluss nicht vorbei ist
            (Phase umschalten in der <a href="<?= e(url("turnier/$tid")) ?>">Übersicht</a>).</p>
        <div class="zeile">
            <?php foreach (AnmeldungService::POSTEN as $schluessel => $text): ?>
                <label><?= e($text) ?> (€ je <?= str_ends_with($schluessel, '_team') ? 'Team' : 'Start' ?>)
                    <input name="<?= $schluessel ?>" inputmode="decimal" value="<?= e($euroFeld($einstellungen[$schluessel])) ?>" placeholder="0,00"></label>
            <?php endforeach; ?>
        </div>
        <label>Hinweis auf der Anmeldeseite <span class="hilfe">z. B. Zahlungsfrist oder Barzahlung am Turniertag</span>
            <textarea name="hinweis" rows="3"><?= e($einstellungen['hinweis']) ?></textarea></label>
        <p class="hilfe">Nach dem Absenden sieht das Dojo sofort eine <strong>Zahlungsaufforderung</strong> mit Empfänger, Bankverbindung und Verwendungszweck –
            die Angaben dazu stehen unter <a href="<?= e(url("turnier/$tid/rechnungen")) ?>">Rechnungen → Angaben</a>. Ändert das Dojo die Anmeldung, entsteht automatisch eine neue Version.</p>
        <label class="check"><input type="checkbox" name="zahlung_vor_freigabe" value="1"<?= chk($einstellungen['zahlung_vor_freigabe']) ?>> Freigabe erst nach Zahlungseingang</label>
        <label class="check"><input type="checkbox" name="rechnung_fuer_dojos" value="1"<?= chk($einstellungen['rechnung_fuer_dojos']) ?>> Dojos können nach Zahlungseingang selbst eine Rechnung abrufen
            <span class="hilfe">Für Turnieranmeldungen nicht vorgeschrieben – die Übersicht gilt als Anmelde- und Zahlungsbestätigung. Aus: Rechnung nur auf Wunsch über die Leitung.</span></label>
        <p><button class="knopf primaer" type="submit">Speichern</button></p>
    </form>
</div>

<h2>Eingegangene Anmeldungen</h2>
<?php if (!$anmeldungen): ?>
    <div class="karte leer">Noch keine Anmeldungen eingegangen.</div>
<?php else: ?>
    <div class="tabelle-scroll">
    <table class="tabelle">
        <thead><tr><th>Dojo</th><th>Ansprechperson</th><th class="rechts">Teilnehmer</th><th class="rechts">Teams</th><th class="rechts">Startgeld</th><th class="rechts">offen</th><th>Eingereicht</th><th>Stand</th></tr></thead>
        <tbody>
        <?php foreach ($anmeldungen as $a): ?>
            <tr>
                <td><a href="<?= e(url("turnier/$tid/anmeldung/{$a['id']}")) ?>"><?= e($a['dojo']) ?></a><?= !empty($a['eigener_verein']) ? ' <span class="tag warn" title="Meldung Ihres eigenen Dojos – Freigabe wird gekennzeichnet">eigener Verein</span>' : '' ?></td>
                <td><?= e($a['ansprechpartner']) ?><br><a class="klein" href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></td>
                <td class="rechts"><?= $a['teilnehmer'] ?></td>
                <td class="rechts"><?= $a['teams'] ?></td>
                <td class="rechts"><?= e(AnmeldungService::euro((int) $a['preis_cent'])) ?></td>
                <td class="rechts"><?= $a['status'] === 'abgelehnt' ? '' : ($a['stand']['offen'] > 0 ? e(AnmeldungService::euro($a['stand']['offen'])) : ($a['stand']['bezahlt'] > 0 ? '✓ bezahlt' : '–')) ?></td>
                <td><?= e(date('d.m.Y H:i', strtotime($a['eingereicht_am']))) ?><?= $a['geaendert_am'] ? '<br><span class="klein">geändert ' . e(date('d.m. H:i', strtotime($a['geaendert_am']))) . '</span>' : '' ?></td>
                <td><span class="tag <?= e($a['stand']['art']) ?>"><?= e($a['stand']['kurz']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="4">Summe Startgeld (ohne abgelehnte)</th>
            <th class="rechts"><?= e(AnmeldungService::euro(array_sum(array_map(static fn ($a) => $a['status'] === 'abgelehnt' ? 0 : (int) $a['preis_cent'], $anmeldungen)))) ?></th><th class="rechts"><?= e(AnmeldungService::euro(array_sum(array_map(static fn ($a) => $a['status'] === 'abgelehnt' ? 0 : $a['stand']['offen'], $anmeldungen)))) ?></th><th colspan="2"></th></tr></tfoot>
    </table>
    </div>
<?php endif; ?>
