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
    $namen[$k] = trim($w($t, 'vorname') . ' ' . $w($t, 'nachname')) ?: "Teilnehmer $k";
}
$kumiteArten = ['' => '–', 'kumite' => 'ja', 'kumite:kihon_ippon' => 'Kihon-Ippon', 'kumite:jiyu_ippon' => 'Jiyu-Ippon', 'kumite:jiyu' => 'Jiyu-Kumite'];
$preisDaten = array_intersect_key($einstellungen, AnmeldungService::POSTEN);
$status = [
    'eingereicht' => ['Eingereicht – wird von der Wettkampfleitung geprüft', 'hinweis'],
    'freigegeben' => ['Freigegeben – die Starter sind für das Turnier gemeldet', 'erfolg'],
    'abgelehnt' => ['Abgelehnt', 'fehler'],
];
?>
<div class="titelzeile"><h1>Anmeldung<?= $anmeldung ? ' · ' . e($anmeldung['dojo']) : '' ?></h1></div>
<p class="leise">
    <?= e($turnier['name']) ?> am <?= e(datum_de($turnier['datum'])) ?><?= $turnier['ort'] ? ' in ' . e($turnier['ort']) : '' ?>
    <?= $turnier['meldeschluss'] ? ' · Meldeschluss <strong>' . e(datum_de($turnier['meldeschluss'])) . '</strong>' : '' ?>
</p>

<section class="karte startgeld">
    <h2>Startgeld</h2>
    <div class="startgeld-liste">
        <?php foreach (AnmeldungService::POSTEN as $schluessel => $text): ?>
            <div class="startgeld-posten">
                <span class="startgeld-betrag"><?= $einstellungen[$schluessel] > 0 ? e(AnmeldungService::euro($einstellungen[$schluessel])) : 'frei' ?></span>
                <span><?= e($text) ?><br><span class="klein"><?= str_ends_with($schluessel, '_team') ? 'je Team' : 'je Starter und Disziplin' ?></span></span>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="hilfe">Jede gemeldete Einzeldisziplin ist ein Start: Wer Kata und Kumite läuft, zahlt zweimal das Einzel-Startgeld.
        Teams zahlen das Team-Startgeld einmal je Team – zusätzlich zu den Einzelstarts ihrer Mitglieder. Der Gesamtpreis wird unten bei jeder Eingabe sofort berechnet.</p>
</section>

<?php if ($anmeldung): [$statusText, $statusArt] = $status[$anmeldung['status']]; ?>
    <section class="karte">
        <h2>Bearbeitungsstand</h2>
        <?= $this->teil('teile/anmeldestand', ['stand' => $stand]) ?>
        <?php if ($stand['offen'] > 0 && $anmeldung['status'] !== 'abgelehnt'): ?>
            <p><strong>Noch zu überweisen: <?= e(AnmeldungService::euro($stand['offen'])) ?></strong> – Bankverbindung und Verwendungszweck stehen in der
                <a href="<?= e(url("anmeldung/$tid/{$anmeldung['code']}/uebersicht")) ?>">Übersicht mit Zahlungsaufforderung</a>.</p>
        <?php endif; ?>
    </section>
    <div class="meldung meldung-<?= $statusArt ?>">
        <strong>Status: <?= e($statusText) ?></strong>
        <?= $anmeldung['grund'] ? '<br>Grund: ' . e($anmeldung['grund']) : '' ?>
        <?php if ($anmeldung['status'] === 'eingereicht'): ?>
            <br><span class="klein">Eingereicht am <?= e(date('d.m.Y H:i', strtotime($anmeldung['eingereicht_am']))) ?><?= $anmeldung['geaendert_am'] ? ', zuletzt geändert ' . e(date('d.m.Y H:i', strtotime($anmeldung['geaendert_am']))) : '' ?>.
            Änderungen sind bis zur Freigabe <?= $turnier['meldeschluss'] ? 'und bis zum Meldeschluss ' : '' ?>möglich.</span>
        <?php endif; ?>
    </div>
    <div class="karte bearbeitungslink">
        <label class="feld">Ihr persönlicher Bearbeitungslink – bitte speichern (nicht weitergeben):
            <input readonly id="bearbeitungslink" value="<?= e(url("anmeldung/$tid/{$anmeldung['code']}")) ?>" data-auswaehlen>
        </label>
        <p class="knopfleiste"><a class="knopf" href="<?= e(url("anmeldung/$tid/{$anmeldung['code']}/uebersicht")) ?>">🖨 Übersicht mit Zahlungsaufforderung</a></p>
    </div>
<?php endif; ?>

<?php if (!$bearbeitbar): ?>
    <?php if ($geschlossen && (!$anmeldung || $anmeldung['status'] === 'eingereicht')): ?>
        <div class="meldung meldung-hinweis"><?= e($geschlossen) ?> Bitte bei Fragen an die Wettkampfleitung wenden.</div>
    <?php endif; ?>
    <?php if ($anmeldung): ?>
        <div class="karte"><?= $this->teil('anmeldung/zusammenfassung', ['turnier' => $turnier, 'angaben' => $angaben, 'preis' => $preis]) ?></div>
    <?php endif; ?>
    <?php return; ?>
<?php endif; ?>

<?php if (!empty($bindungHinweis)): ?>
    <div class="meldung meldung-hinweis"><?= e($bindungHinweis) ?>
        <form method="post" action="<?= e(url('logout')) ?>" class="inline"><?= csrf_feld() ?><button class="knopf klein" type="submit">Abmelden</button></form></div>
<?php endif; ?>
<?php if ($fehler): ?>
    <div class="meldung meldung-fehler">
        <strong>Bitte korrigieren:</strong>
        <ul><?php foreach ($fehler as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($formularZiel) ?>" class="anmeldeformular" id="anmeldeformular"
      data-preise="<?= e(json_encode($preisDaten)) ?>">
    <?= csrf_feld() ?>
    <?= $botschutz ?>
    <div class="unsichtbar" aria-hidden="true"><label>Webseite <input name="webseite" tabindex="-1" autocomplete="off"></label></div>

    <section class="karte formular">
        <h2>1. Dojo und Ansprechperson</h2>
        <div class="zeile">
            <?php if (!empty($festesDojo)): ?>
                <label>Dojo / Verein <input value="<?= e($festesDojo) ?>" readonly aria-readonly="true"><span class="hilfe">aus dem Konto – Meldungen erscheinen unter „Meine Meldungen“</span></label>
            <?php else: ?>
            <label>Dojo / Verein <input name="dojo" value="<?= e($w($werte, 'dojo')) ?>" required maxlength="200"></label>
            <?php endif; ?>
            <label>Ansprechperson <input name="ansprechpartner" value="<?= e($w($werte, 'ansprechpartner')) ?>" required maxlength="200"></label>
        </div>
        <div class="zeile">
            <label>E-Mail <input type="email" name="email" value="<?= e($w($werte, 'email')) ?>" required maxlength="200"></label>
            <label>Telefon <input name="telefon" value="<?= e($w($werte, 'telefon')) ?>" maxlength="50" placeholder="optional"></label>
        </div>
    </section>

    <section class="karte">
        <h2>2. Teilnehmer und Einzeldisziplinen</h2>
        <p class="hilfe">Je Starter eine Zeile. Die Klasse wird automatisch aus Geschlecht, Alter am Turniertag und Graduierung bestimmt.
            Bei Kumite kann die Kampfform vorgegeben werden („ja“ = Kampfform nach Klasse). Wer nur im Team startet, braucht keine Einzeldisziplin.</p>
        <div class="tabelle-scroll">
        <table class="tabelle eingabe-tabelle" id="teilnehmer">
            <thead><tr><th>#</th><th>Vorname</th><th>Nachname</th><th>Geschlecht</th><th>Geburtsdatum</th><th>Graduierung</th><th>Kata<br><span class="klein"><?= e(AnmeldungService::euro($einstellungen['preis_kata'])) ?></span></th><th>Kumite<br><span class="klein"><?= e(AnmeldungService::euro($einstellungen['preis_kumite'])) ?></span></th><th class="rechts">Startgeld</th><th></th></tr></thead>
            <tbody>
            <?php $nr = 0; foreach ($teilnehmer as $k => $t): $nr++; $k = (int) $k; ?>
                <tr data-schluessel="<?= $k ?>">
                    <td class="nr"><?= $nr ?></td>
                    <td><input name="teilnehmer[<?= $k ?>][vorname]" value="<?= e($w($t, 'vorname')) ?>" aria-label="Vorname" maxlength="100"></td>
                    <td><input name="teilnehmer[<?= $k ?>][nachname]" value="<?= e($w($t, 'nachname')) ?>" aria-label="Nachname" maxlength="100"></td>
                    <td><select name="teilnehmer[<?= $k ?>][geschlecht]" aria-label="Geschlecht">
                        <option value="">–</option>
                        <?php foreach (['w' => 'weiblich', 'm' => 'männlich', 'd' => 'divers'] as $g => $text): ?>
                            <option value="<?= $g ?>"<?= sel($g, $w($t, 'geschlecht')) ?>><?= $text ?></option>
                        <?php endforeach; ?>
                    </select></td>
                    <td><input type="date" name="teilnehmer[<?= $k ?>][geburtsdatum]" value="<?= e($w($t, 'geburtsdatum')) ?>" aria-label="Geburtsdatum"></td>
                    <td><select name="teilnehmer[<?= $k ?>][graduierung]" aria-label="Graduierung">
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
                    <td><button type="button" class="knopf klein" data-aktion="zeile-weg" title="Zeile entfernen">✕</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p><button type="button" class="knopf" data-aktion="teilnehmer-neu">+ Teilnehmer hinzufügen</button></p>
    </section>

    <section class="karte">
        <h2>3. Teams <span class="hilfe">optional</span></h2>
        <p class="hilfe">Ein Team besteht aus 3 Startern (plus optional einem Ersatz) aus der Teilnehmerliste oben. Das Team startet in der Altersklasse des ältesten Mitglieds.
            Startgeld je Team: Kata-Team <?= e(AnmeldungService::euro($einstellungen['preis_kata_team'])) ?>, Kumite-Team <?= e(AnmeldungService::euro($einstellungen['preis_kumite_team'])) ?>.</p>
        <div id="teams">
            <?php foreach ($teams as $i => $team): ?>
                <?= $this->teil('anmeldung/team', ['i' => $i, 'team' => $team, 'namen' => $namen, 'einstellungen' => $einstellungen]) ?>
            <?php endforeach; ?>
        </div>
        <p><button type="button" class="knopf" data-aktion="team-neu">+ Team hinzufügen</button></p>
        <template id="team-vorlage"><?= $this->teil('anmeldung/team', ['i' => '__I__', 'team' => [], 'namen' => [], 'einstellungen' => $einstellungen]) ?></template>
    </section>

    <?php if ($kontoBis !== null): ?>
    <section class="karte formular">
        <h2>4. Zugang für Ihr Dojo <span class="klein">(optional)</span></h2>
        <p class="hilfe">Mit einem Zugang sehen Sie den Bearbeitungsstand dieser Anmeldung jederzeit unter „Meine Meldungen“, ohne den Bearbeitungslink
            aufzuheben. Anmelden mit der E-Mail-Adresse oben. Sie erhalten eine E-Mail mit einem Bestätigungslink – erst danach ist der Zugang nutzbar.</p>
        <label class="check"><input type="checkbox" name="konto_anlegen" value="1"<?= chk(!empty($werte['konto_anlegen'])) ?>> Zugang für unser Dojo anlegen</label>
        <div id="konto-felder" class="konto-felder">
            <div class="zeile">
                <label>Vorname <input name="konto_vorname" value="<?= e($w($werte, 'konto_vorname')) ?>" maxlength="100" autocomplete="given-name"></label>
                <label>Nachname <input name="konto_nachname" value="<?= e($w($werte, 'konto_nachname')) ?>" maxlength="100" autocomplete="family-name"></label>
            </div>
            <div class="zeile">
                <label>Passwort <span class="hilfe">mindestens 12 Zeichen</span> <input type="password" name="konto_passwort" minlength="12" maxlength="72" autocomplete="new-password"></label>
                <label>Passwort wiederholen <input type="password" name="konto_passwort2" minlength="12" maxlength="72" autocomplete="new-password"></label>
            </div>
            <p class="meldung meldung-hinweis klein"><strong>Gültigkeit:</strong> Der Zugang gilt bis <strong><?= e(datum_de($kontoBis)) ?></strong>
                (<?= (int) $kontoMonate ?> <?= $kontoMonate === 1 ? 'Monat' : 'Monate' ?> nach dem Turnier) und wird danach automatisch gelöscht.
                Für jedes weitere Turnier ist eine neue Anmeldung nötig – wer dabei angemeldet ist, verlängert damit seinen Zugang.</p>
        </div>
    </section>
    <?php elseif ($angemeldetAls !== null && !$anmeldung): ?>
        <div class="meldung meldung-hinweis">Angemeldet als <strong><?= e($angemeldetAls) ?></strong> – diese Anmeldung erscheint unter „Meine Meldungen“.</div>
    <?php endif; ?>

    <section class="karte">
        <h2><?= $kontoBis !== null ? '5' : '4' ?>. Zusammenfassung und Startgeld</h2>
        <p class="hilfe">Anzahl der Starts × Startgeld – die Summe ist der zu überweisende Gesamtpreis.</p>
        <div id="preis"><?= $this->teil('anmeldung/preis', ['preis' => $preisFormular]) ?></div>
        <?php if ($einstellungen['hinweis'] !== ''): ?>
            <div class="meldung meldung-hinweis zahlungshinweis"><?= nl2br(e($einstellungen['hinweis'])) ?></div>
        <?php endif; ?>
        <label class="feld">Bemerkung an die Wettkampfleitung <span class="hilfe">optional</span>
            <textarea name="bemerkung" rows="3" maxlength="2000"><?= e($w($werte, 'bemerkung')) ?></textarea></label>
        <p class="hilfe">Die Angaben werden zur Durchführung des Turniers verarbeitet (Art. 6 Abs. 1 lit. b DSGVO); Name, Verein, Klasse und Ergebnisse
            erscheinen in Start- und Ergebnislisten und auf der Statusseite, Geburtsdaten nicht. Einzelheiten, Speicherdauer und Rechte:
            <a href="<?= e($datenschutzLink) ?>" target="_blank" rel="noopener">Datenschutzhinweise zum Turnier ↗</a></p>
        <label class="check"><input type="checkbox" name="einwilligung" value="1" required<?= chk(!empty($werte['einwilligung'])) ?>>
            <?= e(\Wettkampf\Meldung\AnmeldungService::DATENSCHUTZ_BESTAETIGUNG) ?></label>
        <?php if ($einladungAngebot): ?>
            <label class="check"><input type="checkbox" name="einladungen" value="1"<?= chk(!empty($werte['einladungen'])) ?>>
                <?= e(\Wettkampf\Meldung\Einladungen::TEXT) ?></label>
        <?php endif; ?>
        <p><button type="submit" class="knopf primaer gross"><?= $anmeldung ? 'Änderungen speichern' : 'Anmeldung absenden' ?></button></p>
    </section>
</form>
<script src="<?= e(asset('botschutz.js')) ?>" defer></script>
