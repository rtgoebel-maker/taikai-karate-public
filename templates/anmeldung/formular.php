<?php
/**
 * Öffentliche Anmeldung eines Dojos: neu, per Bearbeitungscode ändern oder
 * (nach Freigabe/Meldeschluss) schreibgeschützt ansehen.
 * @var string $datenschutzLink Datenschutzhinweise des Turniers
 * @var bool $einladungAngebot freiwillige Einwilligung in Einladungen anbieten
 * @var array<string, mixed> $turnier
 * @var array<string, mixed>|null $anmeldung
 * @var string $formularZiel Absenden an (Anmeldelink bzw. Bearbeitungslink)
 * @var array<string, mixed> $einstellungen
 * @var string|null $geschlossen
 * @var bool $bearbeitbar
 * @var array<string, mixed> $werte
 * @var list<string> $fehler
 * @var array<string, mixed>|null $angaben
 * @var array<string, mixed>|null $preis
 * @var array<string, mixed> $preisFormular
 * @var array<string, mixed>|null $stand Bearbeitungsstand (bestehende Anmeldung)
 * @var string $botschutz versteckte Felder (Kern\BotSchutz)
 * @var string|null $kontoBis Dojo-Zugang anlegbar (Ablaufdatum Y-m-d) bzw. null (kein Mailversand, schon angemeldet, Änderung)
 * @var int $kontoMonate
 * @var string|null $angemeldetAls E-Mail des angemeldeten Kontos
 * @var string|null $bindungHinweis angemeldeter Zugang gilt nur für ein anderes Turnier
 */
use Wettkampf\Kern\Sprache;
use Wettkampf\Meldung\AnmeldungService;
use Wettkampf\Domain\Graduierung;

$this->layout('anmeldung/layout', ['turnier' => $turnier]);
$tid = $turnier['id'];
$teilnehmer = (array) ($werte['teilnehmer'] ?? []);
if ($teilnehmer === []) {
    $teilnehmer = [1 => [], 2 => [], 3 => []];
}
$teams = array_values((array) ($werte['teams'] ?? []));
$w = static fn (array $zeile, string $feld) => (string) ($zeile[$feld] ?? '');
$namen = [];
foreach ($teilnehmer as $k => $t) {
    $namen[$k] = trim($w($t, 'vorname') . ' ' . $w($t, 'nachname')) ?: t('Teilnehmer {nr}', ['nr' => $k]);
}
$kumiteArten = ['' => '–', 'kumite' => te('ja'), 'kumite:kihon_ippon' => 'Kihon-Ippon', 'kumite:jiyu_ippon' => 'Jiyu-Ippon', 'kumite:jiyu' => 'Jiyu-Kumite'];
$preisDaten = array_intersect_key($einstellungen, AnmeldungService::POSTEN);
$status = [
    'eingereicht' => [t('Eingereicht – wird von der Wettkampfleitung geprüft'), 'hinweis'],
    'freigegeben' => [t('Freigegeben – die Starter sind für das Turnier gemeldet'), 'erfolg'],
    'abgelehnt' => [t('Abgelehnt'), 'fehler'],
];
$zeit = static fn (string $z): string => datum_de($z) . date(' H:i', (int) strtotime($z));
?>
<div class="titelzeile"><h1><?= te('Anmeldung') ?><?= $anmeldung ? ' · ' . e($anmeldung['dojo']) : '' ?></h1></div>
<p class="leise">
    <?= $turnier['ort']
        ? te('{name} am {datum} in {ort}', ['name' => $turnier['name'], 'datum' => datum_de($turnier['datum']), 'ort' => $turnier['ort']])
        : te('{name} am {datum}', ['name' => $turnier['name'], 'datum' => datum_de($turnier['datum'])]) ?>
    <?= $turnier['meldeschluss'] ? th(' · Meldeschluss <strong>{datum}</strong>', ['datum' => datum_de($turnier['meldeschluss'])]) : '' ?>
</p>

<section class="karte startgeld">
    <h2><?= te('Startgeld') ?></h2>
    <div class="startgeld-liste">
        <?php foreach (AnmeldungService::POSTEN as $schluessel => $text): ?>
            <div class="startgeld-posten">
                <span class="startgeld-betrag"><?= $einstellungen[$schluessel] > 0 ? e(AnmeldungService::euro($einstellungen[$schluessel])) : te('frei') ?></span>
                <span><?= e(Sprache::t($text)) ?><br><span class="klein"><?= str_ends_with($schluessel, '_team') ? te('je Team') : te('je Starter und Disziplin') ?></span></span>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="hilfe"><?= te('Jede gemeldete Einzeldisziplin ist ein Start: Wer Kata und Kumite läuft, zahlt zweimal das Einzel-Startgeld. Teams zahlen das Team-Startgeld einmal je Team – zusätzlich zu den Einzelstarts ihrer Mitglieder. Der Gesamtpreis wird unten bei jeder Eingabe sofort berechnet.') ?></p>
</section>

<?php if ($anmeldung): [$statusText, $statusArt] = $status[$anmeldung['status']]; ?>
    <section class="karte">
        <h2><?= te('Bearbeitungsstand') ?></h2>
        <?= $this->teil('teile/anmeldestand', ['stand' => $stand]) ?>
        <?php if ($stand['offen'] > 0 && $anmeldung['status'] !== 'abgelehnt'): ?>
            <p><?= th('<strong>Noch zu überweisen: {betrag}</strong> – Bankverbindung und Verwendungszweck stehen in der <a href="{link}">Übersicht mit Zahlungsaufforderung</a>.', ['betrag' => AnmeldungService::euro($stand['offen']), 'link' => url("anmeldung/$tid/{$anmeldung['code']}/uebersicht")]) ?></p>
        <?php endif; ?>
    </section>
    <div class="meldung meldung-<?= $statusArt ?>">
        <strong><?= te('Status: {status}', ['status' => $statusText]) ?></strong>
        <?= $anmeldung['grund'] ? '<br>' . te('Grund: {grund}', ['grund' => $anmeldung['grund']]) : '' ?>
        <?php if ($anmeldung['status'] === 'eingereicht'): ?>
            <br><span class="klein"><?= te('Eingereicht am {zeit}', ['zeit' => $zeit($anmeldung['eingereicht_am'])]) ?><?= $anmeldung['geaendert_am'] ? te(', zuletzt geändert {zeit}', ['zeit' => $zeit($anmeldung['geaendert_am'])]) : '' ?>.
            <?= $turnier['meldeschluss'] ? te('Änderungen sind bis zur Freigabe und bis zum Meldeschluss möglich.') : te('Änderungen sind bis zur Freigabe möglich.') ?></span>
        <?php endif; ?>
    </div>
    <div class="karte bearbeitungslink">
        <label class="feld"><?= te('Ihr persönlicher Bearbeitungslink – bitte speichern (nicht weitergeben):') ?>
            <input readonly id="bearbeitungslink" value="<?= e(url("anmeldung/$tid/{$anmeldung['code']}")) ?>" data-auswaehlen>
        </label>
        <p class="knopfleiste"><a class="knopf" href="<?= e(url("anmeldung/$tid/{$anmeldung['code']}/uebersicht")) ?>">🖨 <?= te('Übersicht mit Zahlungsaufforderung') ?></a></p>
    </div>
<?php endif; ?>

<?php if (!$bearbeitbar): ?>
    <?php if ($geschlossen && (!$anmeldung || $anmeldung['status'] === 'eingereicht')): ?>
        <div class="meldung meldung-hinweis"><?= e($geschlossen) ?> <?= te('Bitte bei Fragen an die Wettkampfleitung wenden.') ?></div>
    <?php endif; ?>
    <?php if ($anmeldung): ?>
        <div class="karte"><?= $this->teil('anmeldung/zusammenfassung', ['turnier' => $turnier, 'angaben' => $angaben, 'preis' => $preis]) ?></div>
    <?php endif; ?>
    <?php return; ?>
<?php endif; ?>

<?php if (!empty($bindungHinweis)): ?>
    <div class="meldung meldung-hinweis"><?= e($bindungHinweis) ?>
        <form method="post" action="<?= e(url('logout')) ?>" class="inline"><?= csrf_feld() ?><button class="knopf klein" type="submit"><?= te('Abmelden') ?></button></form></div>
<?php endif; ?>
<?php if ($fehler): ?>
    <div class="meldung meldung-fehler">
        <strong><?= te('Bitte korrigieren:') ?></strong>
        <ul><?php foreach ($fehler as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($formularZiel) ?>" class="anmeldeformular" id="anmeldeformular"
      data-preise="<?= e(json_encode($preisDaten)) ?>">
    <?= csrf_feld() ?>
    <?= $botschutz ?>
    <div class="unsichtbar" aria-hidden="true"><label><?= te('Webseite') ?> <input name="webseite" tabindex="-1" autocomplete="off"></label></div>

    <section class="karte formular">
        <h2><?= te('1. Dojo und Ansprechperson') ?></h2>
        <div class="zeile">
            <?php if (!empty($festesDojo)): ?>
                <label><?= te('Dojo / Verein') ?> <input value="<?= e($festesDojo) ?>" readonly aria-readonly="true"><span class="hilfe"><?= te('aus dem Konto – Meldungen erscheinen unter „Meine Meldungen“') ?></span></label>
            <?php else: ?>
            <label><?= te('Dojo / Verein') ?> <input name="dojo" value="<?= e($w($werte, 'dojo')) ?>" required maxlength="200"></label>
            <?php endif; ?>
            <label><?= te('Ansprechperson') ?> <input name="ansprechpartner" value="<?= e($w($werte, 'ansprechpartner')) ?>" required maxlength="200"></label>
        </div>
        <div class="zeile">
            <label><?= te('E-Mail') ?> <input type="email" name="email" value="<?= e($w($werte, 'email')) ?>" required maxlength="200"></label>
            <label><?= te('Telefon') ?> <input name="telefon" value="<?= e($w($werte, 'telefon')) ?>" maxlength="50" placeholder="<?= te('optional') ?>"></label>
        </div>
    </section>

    <section class="karte">
        <h2><?= te('2. Teilnehmer und Einzeldisziplinen') ?></h2>
        <p class="hilfe"><?= te('Je Starter eine Zeile. Die Klasse wird automatisch aus Geschlecht, Alter am Turniertag und Graduierung bestimmt. Bei Kumite kann die Kampfform vorgegeben werden („ja“ = Kampfform nach Klasse). Wer nur im Team startet, braucht keine Einzeldisziplin.') ?></p>
        <div class="tabelle-scroll">
        <table class="tabelle eingabe-tabelle" id="teilnehmer">
            <thead><tr><th>#</th><th><?= te('Vorname') ?></th><th><?= te('Nachname') ?></th><th><?= te('Geschlecht') ?></th><th><?= te('Geburtsdatum') ?></th><th><?= te('Graduierung') ?></th><th>Kata<br><span class="klein"><?= e(AnmeldungService::euro($einstellungen['preis_kata'])) ?></span></th><th>Kumite<br><span class="klein"><?= e(AnmeldungService::euro($einstellungen['preis_kumite'])) ?></span></th><th class="rechts"><?= te('Startgeld') ?></th><th></th></tr></thead>
            <tbody>
            <?php $nr = 0; foreach ($teilnehmer as $k => $t): $nr++; $k = (int) $k; ?>
                <tr data-schluessel="<?= $k ?>">
                    <td class="nr"><?= $nr ?></td>
                    <td><input name="teilnehmer[<?= $k ?>][vorname]" value="<?= e($w($t, 'vorname')) ?>" aria-label="<?= te('Vorname') ?>" maxlength="100"></td>
                    <td><input name="teilnehmer[<?= $k ?>][nachname]" value="<?= e($w($t, 'nachname')) ?>" aria-label="<?= te('Nachname') ?>" maxlength="100"></td>
                    <td><select name="teilnehmer[<?= $k ?>][geschlecht]" aria-label="<?= te('Geschlecht') ?>">
                        <option value="">–</option>
                        <?php foreach (['w', 'm', 'd'] as $g): ?>
                            <option value="<?= $g ?>"<?= sel($g, $w($t, 'geschlecht')) ?>><?= e(geschlecht_text($g)) ?></option>
                        <?php endforeach; ?>
                    </select></td>
                    <td><input type="date" name="teilnehmer[<?= $k ?>][geburtsdatum]" value="<?= e($w($t, 'geburtsdatum')) ?>" aria-label="<?= te('Geburtsdatum') ?>"></td>
                    <td><select name="teilnehmer[<?= $k ?>][graduierung]" aria-label="<?= te('Graduierung') ?>">
                        <option value="">–</option>
                        <?php foreach (Graduierung::auswahl() as $g => $text): ?>
                            <option value="<?= $g ?>"<?= sel((string) $g, $w($t, 'graduierung')) ?>><?= e($text) ?></option>
                        <?php endforeach; ?>
                    </select></td>
                    <td class="mitte"><input type="checkbox" name="teilnehmer[<?= $k ?>][kata]" value="1"<?= chk(!empty($t['kata'])) ?> aria-label="Kata"></td>
                    <td><select name="teilnehmer[<?= $k ?>][kumite]" aria-label="Kumite">
                        <?php foreach ($kumiteArten as $wert => $text): ?>
                            <option value="<?= $wert ?>"<?= sel($wert, $w($t, 'kumite')) ?>><?= $text ?></option>
                        <?php endforeach; ?>
                    </select></td>
                    <td class="rechts betrag" data-betrag><?= e(AnmeldungService::euro((!empty($t['kata']) ? $einstellungen['preis_kata'] : 0) + ($w($t, 'kumite') !== '' ? $einstellungen['preis_kumite'] : 0))) ?></td>
                    <td><button type="button" class="knopf klein" data-aktion="zeile-weg" title="<?= te('Zeile entfernen') ?>">✕</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p><button type="button" class="knopf" data-aktion="teilnehmer-neu"><?= te('+ Teilnehmer hinzufügen') ?></button></p>
    </section>

    <section class="karte">
        <h2><?= te('3. Teams') ?> <span class="hilfe"><?= te('optional') ?></span></h2>
        <p class="hilfe"><?= te('Ein Team besteht aus 3 Startern (plus optional einem Ersatz) aus der Teilnehmerliste oben. Das Team startet in der Altersklasse des ältesten Mitglieds. Startgeld je Team: Kata-Team {kata}, Kumite-Team {kumite}.', ['kata' => AnmeldungService::euro($einstellungen['preis_kata_team']), 'kumite' => AnmeldungService::euro($einstellungen['preis_kumite_team'])]) ?></p>
        <div id="teams">
            <?php foreach ($teams as $i => $team): ?>
                <?= $this->teil('anmeldung/team', ['i' => $i, 'team' => $team, 'namen' => $namen, 'einstellungen' => $einstellungen]) ?>
            <?php endforeach; ?>
        </div>
        <p><button type="button" class="knopf" data-aktion="team-neu"><?= te('+ Team hinzufügen') ?></button></p>
        <template id="team-vorlage"><?= $this->teil('anmeldung/team', ['i' => '__I__', 'team' => [], 'namen' => [], 'einstellungen' => $einstellungen]) ?></template>
    </section>

    <?php if ($kontoBis !== null): ?>
    <section class="karte formular">
        <h2><?= te('4. Zugang für Ihr Dojo') ?> <span class="klein"><?= te('(optional)') ?></span></h2>
        <p class="hilfe"><?= te('Mit einem Zugang sehen Sie den Bearbeitungsstand dieser Anmeldung jederzeit unter „Meine Meldungen“, ohne den Bearbeitungslink aufzuheben. Anmelden mit der E-Mail-Adresse oben. Sie erhalten eine E-Mail mit einem Bestätigungslink – erst danach ist der Zugang nutzbar.') ?></p>
        <label class="check"><input type="checkbox" name="konto_anlegen" value="1"<?= chk(!empty($werte['konto_anlegen'])) ?>> <?= te('Zugang für unser Dojo anlegen') ?></label>
        <div id="konto-felder" class="konto-felder">
            <div class="zeile">
                <label><?= te('Vorname') ?> <input name="konto_vorname" value="<?= e($w($werte, 'konto_vorname')) ?>" maxlength="100" autocomplete="given-name"></label>
                <label><?= te('Nachname') ?> <input name="konto_nachname" value="<?= e($w($werte, 'konto_nachname')) ?>" maxlength="100" autocomplete="family-name"></label>
            </div>
            <div class="zeile">
                <label><?= te('Passwort') ?> <span class="hilfe"><?= te('mindestens 12 Zeichen') ?></span> <input type="password" name="konto_passwort" minlength="12" maxlength="72" autocomplete="new-password"></label>
                <label><?= te('Passwort wiederholen') ?> <input type="password" name="konto_passwort2" minlength="12" maxlength="72" autocomplete="new-password"></label>
            </div>
            <p class="meldung meldung-hinweis klein"><?= th('<strong>Gültigkeit:</strong> Der Zugang gilt bis <strong>{datum}</strong>', ['datum' => datum_de($kontoBis)]) ?>
                (<?= $kontoMonate === 1 ? te('{n} Monat nach dem Turnier', ['n' => (int) $kontoMonate]) : te('{n} Monate nach dem Turnier', ['n' => (int) $kontoMonate]) ?>) <?= te('und wird danach automatisch gelöscht. Für jedes weitere Turnier ist eine neue Anmeldung nötig – wer dabei angemeldet ist, verlängert damit seinen Zugang.') ?></p>
        </div>
    </section>
    <?php elseif ($angemeldetAls !== null && !$anmeldung): ?>
        <div class="meldung meldung-hinweis"><?= th('Angemeldet als <strong>{email}</strong> – diese Anmeldung erscheint unter „Meine Meldungen“.', ['email' => $angemeldetAls]) ?></div>
    <?php endif; ?>

    <section class="karte">
        <h2><?= $kontoBis !== null ? '5' : '4' ?>. <?= te('Zusammenfassung und Startgeld') ?></h2>
        <p class="hilfe"><?= te('Anzahl der Starts × Startgeld – die Summe ist der zu überweisende Gesamtpreis.') ?></p>
        <div id="preis"><?= $this->teil('anmeldung/preis', ['preis' => $preisFormular]) ?></div>
        <?php if ($einstellungen['hinweis'] !== ''): ?>
            <div class="meldung meldung-hinweis zahlungshinweis"><?= nl2br(e($einstellungen['hinweis'])) ?></div>
        <?php endif; ?>
        <label class="feld"><?= te('Bemerkung an die Wettkampfleitung') ?> <span class="hilfe"><?= te('optional') ?></span>
            <textarea name="bemerkung" rows="3" maxlength="2000"><?= e($w($werte, 'bemerkung')) ?></textarea></label>
        <p class="hilfe"><?= th('Die Angaben werden zur Durchführung des Turniers verarbeitet (Art. 6 Abs. 1 lit. b DSGVO); Name, Verein, Klasse und Ergebnisse erscheinen in Start- und Ergebnislisten und auf der Statusseite, Geburtsdaten nicht. Einzelheiten, Speicherdauer und Rechte: <a href="{link}" target="_blank" rel="noopener">Datenschutzhinweise zum Turnier ↗</a>', ['link' => $datenschutzLink]) ?></p>
        <label class="check"><input type="checkbox" name="einwilligung" value="1" required<?= chk(!empty($werte['einwilligung'])) ?>>
            <?= e(Sprache::t(AnmeldungService::DATENSCHUTZ_BESTAETIGUNG)) ?></label>
        <?php if ($einladungAngebot): ?>
            <label class="check"><input type="checkbox" name="einladungen" value="1"<?= chk(!empty($werte['einladungen'])) ?>>
                <?= e(Sprache::t(\Wettkampf\Meldung\Einladungen::TEXT)) ?></label>
        <?php endif; ?>
        <p><button type="submit" class="knopf primaer gross"><?= $anmeldung ? te('Änderungen speichern') : te('Anmeldung absenden') ?></button></p>
    </section>
</form>
<script src="<?= e(asset('botschutz.js')) ?>" defer></script>
