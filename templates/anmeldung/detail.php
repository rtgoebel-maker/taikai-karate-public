<?php
/**
 * Eine Online-Anmeldung prüfen, freigeben oder ablehnen (Wettkampfleitung).
 * @var array<string, mixed> $turnier
 * @var array<string, mixed> $anmeldung
 * @var array<string, mixed> $angaben
 * @var array<string, mixed> $preis
 * @var bool $eigenerVerein
 * @var array<string, mixed> $stand Bearbeitungsstand (AnmeldeStand)
 * @var array<string, mixed> $zahlungsangaben nummer (Verwendungszweck), Bank …
 * @var bool $zahlungVorFreigabe
 * @var list<array{id: int, email: string, name: string, verein_id: int|null, bestaetigung_offen: bool}> $konten Zugänge zur Anmeldung
 */
use Wettkampf\Meldung\AnmeldungService;

$this->layout('layout', ['titel' => t('Anmeldung {dojo}', ['dojo' => $anmeldung['dojo']]), 'turnier' => $turnier, 'aktiv' => 'anmeldungen']);
$tid = $turnier['id'];
$aid = $anmeldung['id'];
$mitGeld = $stand['betrag'] > 0 || $stand['bezahlt'] > 0;
$euroFeld = static fn (int $cent) => str_replace('.', ',', sprintf('%.2f', $cent / 100));
$freigabeGesperrt = $zahlungVorFreigabe && $stand['offen'] > 0;
$ohneDojo = array_values(array_filter($konten, static fn (array $k) => $k['verein_id'] === null && !$k['bestaetigung_offen']));
$zeit = static fn (string $z): string => datum_de($z) . date(' H:i', (int) strtotime($z));
?>
<p><a href="<?= e(url("turnier/$tid/anmeldungen")) ?>">← <?= te('alle Anmeldungen') ?></a></p>
<div class="titelzeile">
    <h1><?= e($anmeldung['dojo']) ?></h1>
    <a class="knopf" href="<?= e(url("turnier/$tid/anmeldung/$aid/druck")) ?>" target="_blank">🖨 <?= te('Druckansicht') ?></a>
    <span class="tag <?= e($stand['art']) ?>"><?= e($stand['kurz']) ?></span>
</div>

<section class="karte">
    <h2><?= te('Bearbeitungsstand') ?></h2>
    <?= $this->teil('teile/anmeldestand', ['stand' => $stand]) ?>
    <p class="hilfe"><?= te('So sieht es auch das Dojo auf seiner Anmeldeübersicht (Bearbeitungslink bzw. „Meine Meldungen“).') ?></p>
</section>

<div class="zwei-spalten">
    <section class="karte">
        <h2><?= te('Kontakt') ?></h2>
        <p><?= e($anmeldung['ansprechpartner']) ?><br>
            <a href="mailto:<?= e($anmeldung['email']) ?>"><?= e($anmeldung['email']) ?></a>
            <?= $anmeldung['telefon'] ? '<br>' . e($anmeldung['telefon']) : '' ?></p>
        <?php if ($anmeldung['bemerkung']): ?><p><strong><?= te('Bemerkung:') ?></strong><br><?= nl2br(e($anmeldung['bemerkung'])) ?></p><?php endif; ?>
        <p class="klein"><?= te('Eingereicht {zeit}', ['zeit' => $zeit($anmeldung['eingereicht_am'])]) ?>
            <?= $anmeldung['geaendert_am'] ? te(' · geändert {zeit}', ['zeit' => $zeit($anmeldung['geaendert_am'])]) : '' ?>
            <?= $anmeldung['freigegeben_am'] ? te(' · freigegeben {zeit} von {wer}', ['zeit' => $zeit($anmeldung['freigegeben_am']), 'wer' => $anmeldung['freigegeben_von']]) : '' ?></p>
        <?php if ($konten): ?>
            <p class="klein"><strong><?= te('Zugang:') ?></strong>
                <?php foreach ($konten as $k): ?><?= e($k['email']) ?> (<?= $k['bestaetigung_offen'] ? te('E-Mail noch nicht bestätigt') : ($k['verein_id'] !== null ? te('mit Dojo') : te('noch ohne Dojo')) ?>) <?php endforeach; ?></p>
        <?php endif; ?>
        <label class="feld"><?= te('Bearbeitungslink des Dojos') ?>
            <input readonly id="bearbeitungslink" value="<?= e(url("anmeldung/$tid/{$anmeldung['code']}")) ?>" data-auswaehlen data-absolut></label>
    </section>

    <section class="karte">
        <h2><?= te('Entscheidung') ?> <?= info('freigabe') ?></h2>
        <?php if ($anmeldung['status'] === 'eingereicht'): ?>
            <?php if (!empty($eigenerVerein)): ?>
                <div class="meldung meldung-hinweis"><?= th('<strong>Eigener Verein:</strong> Das ist die Meldung Ihres eigenen Dojos. Die Freigabe ist erlaubt, wird aber im <a href="{link}">Verlauf</a> und bei „freigegeben von“ gekennzeichnet – wenn möglich, eine andere Turnierleitung freigeben lassen.', ['link' => url("turnier/$tid/verlauf", ["objekt" => "anmeldung"])]) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= e(url("turnier/$tid/anmeldung/$aid/freigeben")) ?>"
                  data-bestaetigen="<?= te('Anmeldung freigeben? Verein, {teilnehmer} Teilnehmer und {teams} Teams werden übernommen und den Klassen zugeordnet. Danach kann das Dojo nichts mehr ändern.', ['teilnehmer' => count($angaben['teilnehmer']), 'teams' => count($angaben['teams'])]) ?>">
                <?= csrf_feld() ?>
                <?php if ($ohneDojo): ?>
                    <label class="check"><input type="checkbox" name="konto_zuordnen" value="1">
                        <?= te('Zugang {email} dem Dojo „{dojo}“ zuordnen', ['email' => implode(', ', array_column($ohneDojo, 'email')), 'dojo' => $anmeldung['dojo']]) ?>
                        <span class="hilfe"><?= te('Der Zugang sieht danach Starter, Ergebnisse und Coaching-Portal dieses Dojos – nur ankreuzen, wenn die Person sicher zum Dojo gehört (Vorsicht bei bereits bestehenden Dojos).') ?></span></label>
                <?php endif; ?>
                <button class="knopf primaer" type="submit"<?= $freigabeGesperrt ? ' disabled' : '' ?>><?= te('Freigeben und Teilnehmer übernehmen') ?></button>
            </form>
            <?php if ($freigabeGesperrt): ?>
                <p class="hilfe"><?= th('Freigabe erst nach Zahlungseingang (Einstellung unter <a href="{link}">Anmeldungen</a>) – noch {betrag} offen.', ['link' => url("turnier/$tid/anmeldungen"), 'betrag' => AnmeldungService::euro($stand['offen'])]) ?></p>
            <?php endif; ?>
            <p class="hilfe"><?= te('Bereits vorhandene Teilnehmer (gleicher Name und Geburtsdatum) werden aktualisiert statt doppelt angelegt.') ?></p>
            <form method="post" action="<?= e(url("turnier/$tid/anmeldung/$aid/ablehnen")) ?>" class="formular" data-bestaetigen="<?= te('Anmeldung ablehnen?') ?>">
                <?= csrf_feld() ?>
                <label><?= te('Grund (sieht das Dojo)') ?> <input name="grund" maxlength="500" placeholder="<?= te('z. B. doppelt angemeldet') ?>"></label>
                <p><button class="knopf gefahr" type="submit"><?= te('Ablehnen') ?></button></p>
            </form>
        <?php elseif ($anmeldung['status'] === 'abgelehnt'): ?>
            <p><?= $anmeldung['grund'] ? te('Abgelehnt: {grund}.', ['grund' => $anmeldung['grund']]) : te('Abgelehnt.') ?></p>
            <form method="post" action="<?= e(url("turnier/$tid/anmeldung/$aid/oeffnen")) ?>"><?= csrf_feld() ?>
                <button class="knopf" type="submit"><?= te('Wieder öffnen') ?></button></form>
        <?php else: ?>
            <p><?= th('Freigegeben – die Teilnehmer stehen unter <a href="{teilnehmer}">Teilnehmer</a>, die Teams unter <a href="{teams}">Teams</a>. Änderungen bitte dort vornehmen.', ['teilnehmer' => url("turnier/$tid/teilnehmer"), 'teams' => url("turnier/$tid/teams")]) ?></p>
        <?php endif; ?>
    </section>
</div>

<?php if ($mitGeld && $anmeldung['status'] !== 'abgelehnt'): ?>
<section class="karte">
    <h2><?= te('Zahlung') ?></h2>
    <table class="tabelle">
        <tr><th><?= te('Zahlungsaufforderung') ?></th><td><?= e(AnmeldungService::euro($stand['betrag'])) ?><?= (int) $anmeldung['aufforderung_version'] > 1 ? te(' · Version {version}', ['version' => (int) $anmeldung['aufforderung_version']]) : '' ?>
            <?= $anmeldung['aufforderung_am'] ? '<span class="klein">' . te('vom {zeit}', ['zeit' => $zeit((string) $anmeldung['aufforderung_am'])]) . '</span>' : '' ?></td></tr>
        <tr><th><?= te('Verwendungszweck') ?></th><td><code><?= e($zahlungsangaben['nummer']) ?></code></td></tr>
        <tr><th><?= te('Erhalten') ?></th><td><?= $stand['bezahlt'] > 0 ? e(AnmeldungService::euro($stand['bezahlt'])) . ($anmeldung['bezahlt_am'] ? te(' am {datum}', ['datum' => datum_de($anmeldung['bezahlt_am'])]) : '') . ($anmeldung['zahlung_von'] ? ' <span class="klein">' . te('(erfasst von {wer})', ['wer' => $anmeldung['zahlung_von']]) . '</span>' : '') : '–' ?>
            <?= $anmeldung['zahlung_notiz'] ? '<br><span class="klein">' . e($anmeldung['zahlung_notiz']) . '</span>' : '' ?></td></tr>
        <tr><th><?= te('Offen') ?></th><td><strong><?= e(AnmeldungService::euro($stand['offen'])) ?></strong><?= $stand['bezahlt'] > $stand['betrag'] ? te(' · Guthaben {betrag}', ['betrag' => AnmeldungService::euro($stand['bezahlt'] - $stand['betrag'])]) : '' ?></td></tr>
    </table>
    <form method="post" action="<?= e(url("turnier/$tid/anmeldung/$aid/zahlung")) ?>" class="formular">
        <?= csrf_feld() ?>
        <div class="zeile">
            <label><?= te('Insgesamt erhalten (€)') ?> <input name="betrag" inputmode="decimal" required value="<?= e($euroFeld($stand['bezahlt'] > 0 ? $stand['bezahlt'] : $stand['betrag'])) ?>"></label>
            <label><?= te('Eingang am') ?> <input type="date" name="datum" value="<?= e($anmeldung['bezahlt_am'] ?: date('Y-m-d')) ?>"></label>
            <label><?= te('Notiz') ?> <span class="hilfe"><?= te('optional, z. B. „bar am Turniertag“') ?></span> <input name="notiz" maxlength="200" value="<?= e($anmeldung['zahlung_notiz'] ?? '') ?>"></label>
        </div>
        <p class="knopfleiste">
            <button class="knopf primaer" type="submit"><?= $stand['bezahlt'] > 0 ? te('Zahlung aktualisieren') : te('Zahlungseingang erfassen') ?></button>
            <a class="knopf" href="<?= e(url("turnier/$tid/anmeldung/$aid/rechnung")) ?>" target="_blank" title="<?= te('Für Anmeldungen nicht vorgeschrieben – auf Wunsch, z. B. für die Vereinsbuchhaltung') ?>"><?= te('Rechnung erstellen ↗') ?></a>
        </p>
    </form>
    <?php if ($stand['bezahlt'] > 0): ?>
        <form method="post" action="<?= e(url("turnier/$tid/anmeldung/$aid/zahlung/zuruecknehmen")) ?>" data-bestaetigen="<?= te('Zahlungseingang zurücknehmen?') ?>">
            <?= csrf_feld() ?><button class="knopf klein" type="submit"><?= te('Zahlung zurücknehmen') ?></button></form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="karte"><?= $this->teil('anmeldung/zusammenfassung', ['turnier' => $turnier, 'angaben' => $angaben, 'preis' => $preis]) ?></section>
