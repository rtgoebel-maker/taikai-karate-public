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

$this->layout('layout', ['titel' => t('Anmeldungen'), 'turnier' => $turnier, 'aktiv' => 'anmeldungen']);
$tid = $turnier['id'];
$euroFeld = static fn (int $cent) => $cent ? str_replace('.', ',', sprintf('%.2f', $cent / 100)) : '';
$offen = array_filter($anmeldungen, static fn ($a) => $a['status'] === 'eingereicht');
?>
<div class="titelzeile">
    <h1><?= te('Online-Anmeldungen') ?> <?= info('anmeldung') ?></h1>
    <?php if ($offen): ?><span class="tag warn"><?= te('{n} zu prüfen', ['n' => count($offen)]) ?></span><?php endif; ?>
    <a class="knopf" href="<?= e(url("turnier/$tid/rechnungen")) ?>"><?= te('Rechnungen') ?></a>
    <a class="knopf" href="<?= e(url("turnier/$tid/korrekturen")) ?>"><?= te('Korrekturanträge') ?><?= ($antraegeOffen ?? 0) > 0 ? ' <span class="zaehler">' . (int) $antraegeOffen . '</span>' : '' ?></a>
</div>

<div class="zwei-spalten">
    <section class="karte">
        <h2><?= te('Anmeldeseite für die Dojos') ?></h2>
        <?php if ($geschlossen): ?>
            <div class="meldung meldung-hinweis"><?= e($geschlossen) ?></div>
        <?php else: ?>
            <div class="meldung meldung-erfolg"><?= $turnier['meldeschluss'] ? te('Die Anmeldung ist geöffnet bis zum Meldeschluss am {datum}.', ['datum' => datum_de($turnier['meldeschluss'])]) : te('Die Anmeldung ist geöffnet.') ?></div>
        <?php endif; ?>
        <label class="feld"><?= te('Link zum Weitergeben an die Dojos') ?>
            <input readonly id="anmeldelink" value="<?= e($anmeldelink) ?>" data-auswaehlen data-absolut></label>
        <p class="knopfleiste"><a class="knopf" href="<?= e($anmeldelink) ?>" target="_blank" rel="noopener"><?= te('Anmeldeseite öffnen ↗') ?></a></p>
        <form method="post" action="<?= e(url("turnier/$tid/anmeldungen/link-erneuern")) ?>" class="knopfleiste" data-bestaetigen="<?= te('Neuen Anmeldelink erzeugen? Der bisherige funktioniert dann nicht mehr – bereits verschickte Links müssen ersetzt werden.') ?>">
            <?= csrf_feld() ?>
            <span class="hilfe"><?= te('Der Link enthält einen Zufallsschlüssel und ist nicht erratbar. Ist er an die falsche Stelle geraten:') ?></span>
            <button class="knopf klein" type="submit"><?= te('Neuen Link erzeugen') ?></button>
        </form>
        <p class="hilfe"><?= th('Jedes Dojo erhält nach dem Absenden einen persönlichen Bearbeitungslink und kann bis zur Freigabe bzw. bis zum Meldeschluss (<a href="{link}">Einstellungen</a>) ändern. Mit der Freigabe werden Verein, Teilnehmer und Teams übernommen und automatisch den Klassen zugeordnet.', ['link' => url("turnier/$tid/einstellungen")]) ?></p>
    </section>

    <form method="post" action="<?= e(url("turnier/$tid/anmeldungen/einstellungen")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2><?= te('Einstellungen und Startgeld') ?> <?= info('startgeld') ?></h2>
        <p class="hilfe"><?= th('Die Anmeldeseite ist geöffnet, solange das Turnier in der <strong>Meldephase</strong> ist und der Meldeschluss nicht vorbei ist (Phase umschalten in der <a href="{link}">Übersicht</a>).', ['link' => url("turnier/$tid")]) ?></p>
        <div class="zeile">
            <?php foreach (AnmeldungService::POSTEN as $schluessel => $text): ?>
                <label><?= e(\Wettkampf\Kern\Sprache::t($text)) ?> (<?= str_ends_with($schluessel, '_team') ? te('€ je Team') : te('€ je Start') ?>)
                    <input name="<?= $schluessel ?>" inputmode="decimal" value="<?= e($euroFeld($einstellungen[$schluessel])) ?>" placeholder="0,00"></label>
            <?php endforeach; ?>
        </div>
        <label><?= te('Hinweis auf der Anmeldeseite') ?> <span class="hilfe"><?= te('z. B. Zahlungsfrist oder Barzahlung am Turniertag') ?></span>
            <textarea name="hinweis" rows="3"><?= e($einstellungen['hinweis']) ?></textarea></label>
        <p class="hilfe"><?= th('Nach dem Absenden sieht das Dojo sofort eine <strong>Zahlungsaufforderung</strong> mit Empfänger, Bankverbindung und Verwendungszweck – die Angaben dazu stehen unter <a href="{link}">Rechnungen → Angaben</a>. Ändert das Dojo die Anmeldung, entsteht automatisch eine neue Version.', ['link' => url("turnier/$tid/rechnungen")]) ?></p>
        <label class="check"><input type="checkbox" name="zahlung_vor_freigabe" value="1"<?= chk($einstellungen['zahlung_vor_freigabe']) ?>> <?= te('Freigabe erst nach Zahlungseingang') ?></label>
        <label class="check"><input type="checkbox" name="rechnung_fuer_dojos" value="1"<?= chk($einstellungen['rechnung_fuer_dojos']) ?>> <?= te('Dojos können nach Zahlungseingang selbst eine Rechnung abrufen') ?>
            <span class="hilfe"><?= te('Für Turnieranmeldungen nicht vorgeschrieben – die Übersicht gilt als Anmelde- und Zahlungsbestätigung. Aus: Rechnung nur auf Wunsch über die Leitung.') ?></span></label>
        <p><button class="knopf primaer" type="submit"><?= te('Speichern') ?></button></p>
    </form>
</div>

<h2><?= te('Eingegangene Anmeldungen') ?></h2>
<?php if (!$anmeldungen): ?>
    <div class="karte leer"><?= te('Noch keine Anmeldungen eingegangen.') ?></div>
<?php else: ?>
    <div class="tabelle-scroll">
    <table class="tabelle">
        <thead><tr><th><?= te('Dojo') ?></th><th><?= te('Ansprechperson') ?></th><th class="rechts"><?= te('Teilnehmer') ?></th><th class="rechts"><?= te('Teams') ?></th><th class="rechts"><?= te('Startgeld') ?></th><th class="rechts"><?= te('offen') ?></th><th><?= te('Eingereicht') ?></th><th><?= te('Stand') ?></th></tr></thead>
        <tbody>
        <?php foreach ($anmeldungen as $a): ?>
            <tr>
                <td><a href="<?= e(url("turnier/$tid/anmeldung/{$a['id']}")) ?>"><?= e($a['dojo']) ?></a><?= !empty($a['eigener_verein']) ? ' <span class="tag warn" title="' . te('Meldung Ihres eigenen Dojos – Freigabe wird gekennzeichnet') . '">' . te('eigener Verein') . '</span>' : '' ?></td>
                <td><?= e($a['ansprechpartner']) ?><br><a class="klein" href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></td>
                <td class="rechts"><?= $a['teilnehmer'] ?></td>
                <td class="rechts"><?= $a['teams'] ?></td>
                <td class="rechts"><?= e(AnmeldungService::euro((int) $a['preis_cent'])) ?></td>
                <td class="rechts"><?= $a['status'] === 'abgelehnt' ? '' : ($a['stand']['offen'] > 0 ? e(AnmeldungService::euro($a['stand']['offen'])) : ($a['stand']['bezahlt'] > 0 ? '✓ ' . te('bezahlt') : '–')) ?></td>
                <td><?= e(datum_de($a['eingereicht_am']) . date(' H:i', (int) strtotime($a['eingereicht_am']))) ?><?= $a['geaendert_am'] ? '<br><span class="klein">' . te('geändert {zeit}', ['zeit' => date('d.m. H:i', (int) strtotime($a['geaendert_am']))]) . '</span>' : '' ?></td>
                <td><span class="tag <?= e($a['stand']['art']) ?>"><?= e($a['stand']['kurz']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="4"><?= te('Summe Startgeld (ohne abgelehnte)') ?></th>
            <th class="rechts"><?= e(AnmeldungService::euro(array_sum(array_map(static fn ($a) => $a['status'] === 'abgelehnt' ? 0 : (int) $a['preis_cent'], $anmeldungen)))) ?></th><th class="rechts"><?= e(AnmeldungService::euro(array_sum(array_map(static fn ($a) => $a['status'] === 'abgelehnt' ? 0 : $a['stand']['offen'], $anmeldungen)))) ?></th><th colspan="2"></th></tr></tfoot>
    </table>
    </div>
<?php endif; ?>
