<?php
/**
 * @var array|null $turnier
 * @var array<string, mixed> $werte
 * @var list<array<string, mixed>> $tatamis
 */
use Wettkampf\Domain\Graduierung;

$tatamis ??= [];
$neu = $turnier === null;
$this->layout('layout', ['titel' => $neu ? 'Neues Turnier' : 'Einstellungen', 'turnier' => $turnier]);
?>
<h1><?= $neu ? 'Neues Turnier' : 'Einstellungen' ?></h1>
<div class="zwei-spalten">
    <form method="post" action="<?= e(url($neu ? 'turnier/neu' : "turnier/{$turnier['id']}/einstellungen")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2>Turnier</h2>
        <label>Name <input name="name" value="<?= e($werte['name']) ?>" required placeholder="z. B. Frühjahrsturnier 2026"></label>
        <div class="zeile">
            <label>Datum <input type="date" name="datum" value="<?= e($werte['datum']) ?>" required></label>
            <label>Anzahl Tatamis <input type="number" name="anzahl_tatamis" min="1" max="20" value="<?= e($werte['anzahl_tatamis']) ?>" required></label>
        </div>
        <div class="zeile">
            <label>Ort <input name="ort" value="<?= e($werte['ort']) ?>"></label>
            <label>Halle <input name="halle" value="<?= e($werte['halle']) ?>"></label>
        </div>
        <div class="zeile">
            <label>Mindestgraduierung
                <select name="min_graduierung">
                    <option value="">keine</option>
                    <?php foreach (Graduierung::auswahl() as $g => $text): ?>
                        <option value="<?= $g ?>"<?= sel($g, $werte['min_graduierung']) ?>><?= e($text) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Meldeschluss <input type="date" name="meldeschluss" value="<?= e($werte['meldeschluss']) ?>"></label>
        </div>
        <label class="check"><input type="checkbox" name="oeffentlich" value="1"<?= chk($werte['oeffentlich']) ?>> Öffentliche Statusseite aktiv (nur Name und Verein, keine Geburtsdaten)</label>
        <p class="hilfe">Das Alter der Starter wird immer am Turniertag berechnet.</p>
        <?php if ($neu): ?>
            <label class="check"><input type="checkbox" name="assistent_starten" value="1" checked> Danach mit dem <strong>Einrichtungs-Assistenten</strong> weitermachen
                <span class="hilfe">führt Schritt für Schritt durch Klassen, Tatamis und PINs, Online-Anmeldung und Startgeld, Zahlungsangaben und Coaching-Portal – jeder Schritt lässt sich überspringen</span></label>
        <?php endif; ?>
        <div><button class="knopf primaer" type="submit"><?= $neu ? 'Turnier anlegen' : 'Speichern' ?></button></div>
    </form>

    <?php if (!$neu): ?>
        <form method="post" action="<?= e(url("turnier/{$turnier['id']}/zugaenge")) ?>" class="karte formular">
            <?= csrf_feld() ?>
            <h2>Tatamis &amp; Zugänge <?= info('zugaenge') ?></h2>
            <p class="hilfe">Empfohlen: Geräte unter „Geräte koppeln“ per QR-Code mit Freigabe anmelden. Als Notlösung meldet die Tischbesatzung ein Tablet mit der PIN des Tatamis an. Korrekturen bereits eingetragener Ergebnisse am Tablet erfordern die Leitungs-PIN. Leere Felder lassen die bisherige PIN unverändert.</p>
            <label>Leitungs-PIN (für Korrekturen am Tablet)
                <input name="leitung_pin" inputmode="numeric" pattern="\d{4,8}" placeholder="<?= $turnier['leitung_pin_hash'] ? 'gesetzt – zum Ändern neu eingeben' : 'noch nicht gesetzt' ?>" autocomplete="off">
            </label>
            <table class="tabelle">
                <thead><tr><th>Nr.</th><th>Bezeichnung</th><th>PIN</th><th>Livestream-URL (optional)</th><th>Stream-Einblendung</th></tr></thead>
                <tbody>
                <?php foreach ($tatamis as $t): ?>
                    <tr>
                        <td><?= e($t['nummer']) ?></td>
                        <td><input name="tatami[<?= $t['id'] ?>][bezeichnung]" value="<?= e($t['bezeichnung']) ?>"></td>
                        <td><input name="tatami[<?= $t['id'] ?>][pin]" inputmode="numeric" pattern="\d{4,8}" placeholder="<?= $t['pin_hash'] ? '••••' : 'fehlt' ?>" autocomplete="off"></td>
                        <td><input name="tatami[<?= $t['id'] ?>][stream_url]" value="<?= e($t['stream_url']) ?>" placeholder="https://…"></td>
                        <td><a href="<?= e(url("live/{$turnier['id']}/einblendung/{$t['nummer']}")) ?>" target="_blank" rel="noopener" title="Als Browserquelle in OBS einbinden (1920×1080)">öffnen ↗</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="hilfe">Stream-Einblendung: Adresse in OBS als <em>Browserquelle</em> (1920×1080) einfügen – zeigt den laufenden Kampf bzw. Vortrag
                und kurz das Ergebnis, Hintergrund transparent. Zusätze: <code>?pos=oben</code>, <code>?hintergrund=gruen</code> (Chroma-Key), <code>?ergebnis=0</code>.
                Funktioniert nur, wenn die Statusseite öffentlich ist.</p>
            <?php $akaSeite = \Wettkampf\Durchfuehrung\AusrufService::akaSeite($turnier); ?>
            <label>Aufstellung für den Ausruf <span class="hilfe">Wo steht AKA – vom Kampfrichtertisch aus gesehen? Der Ausrufer sieht nur „links“/„rechts“.</span>
                <select name="aka_seite" style="max-width:340px">
                    <option value="rechts"<?= sel('rechts', $akaSeite) ?>>AKA rechts · SHIRO links</option>
                    <option value="links"<?= sel('links', $akaSeite) ?>>AKA links · SHIRO rechts</option>
                </select>
            </label>
            <?php $uhr = \Wettkampf\Durchfuehrung\AusrufService::kampfuhr($turnier); ?>
            <fieldset>
                <legend>Kampfuhr am Tablet</legend>
                <label class="check"><input type="checkbox" name="kampfuhr_aktiv" value="1"<?= chk($uhr['aktiv']) ?>> Kampfuhr für Kumite am Tablet anzeigen <span class="hilfe">Hajime/Yame startet und stoppt, 30 Sekunden vor Ende Ato shibaraku, am Ende „Kampfzeit abgelaufen“</span></label>
                <div class="zeile">
                    <label>Kampfzeit (Minuten) <span class="hilfe">Wettkampfordnung: 2</span>
                        <select name="kampfuhr_minuten"><?php foreach ([1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5] as $m): ?><option value="<?= $m ?>"<?= sel($m, $uhr['minuten']) ?>><?= e(str_replace('.', ',', (string) $m)) ?></option><?php endforeach; ?></select></label>
                    <label>Kampfzeit Finale (Minuten) <span class="hilfe">bis 5, Entscheidung der Wettkampfleitung</span>
                        <select name="kampfuhr_minuten_finale"><?php foreach ([1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5] as $m): ?><option value="<?= $m ?>"<?= sel($m, $uhr['minuten_finale']) ?>><?= e(str_replace('.', ',', (string) $m)) ?></option><?php endforeach; ?></select></label>
                </div>
                <label class="check"><input type="checkbox" name="kampfuhr_ton" value="1"<?= chk($uhr['ton']) ?>> Signalton am Tablet (Ato shibaraku einfach, Ende doppelt)</label>
                <p class="hilfe">Saishiai (Verlängerung) dauert immer 2 Minuten. Die Uhr läuft nur auf dem Tablet; die offizielle Zeit nimmt weiterhin der Zeitnehmer.</p>
            </fieldset>
            <div><button class="knopf primaer" type="submit">Zugänge speichern</button></div>
        </form>
    <?php endif; ?>
</div>

<?php if (!$neu): ?>
    <?php $coach = \Wettkampf\Meldung\CoachService::einstellungen($turnier); ?>
    <form method="post" action="<?= e(url("turnier/{$turnier['id']}/coach-einstellungen")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2>Coaching-Portal</h2>
        <p class="hilfe">Coaches (Konten mit Dojo) melden sich an und finden das Portal unter „Meine Meldungen“ im Abschnitt „Coaching-Portal“ – nur ihre eigenen Starter, nie die anderer Dojos. Die Online-Anmeldung dort bleibt unabhängig davon nutzbar.</p>
        <label class="check"><input type="checkbox" name="aktiv" value="1"<?= chk($coach['aktiv']) ?>> Coaching-Portal für dieses Turnier freigeben</label>
        <div class="zeile">
            <?php foreach (\Wettkampf\Meldung\CoachService::BEREICHE as $b => $text): ?>
                <label class="check"><input type="checkbox" name="bereiche[]" value="<?= e($b) ?>"<?= chk($coach['bereiche'][$b]) ?>> <?= e($text) ?></label>
            <?php endforeach; ?>
        </div>
        <p class="hilfe">Korrekturanträge bearbeitet die Leitung unter <a href="<?= e(url("turnier/{$turnier['id']}/korrekturen")) ?>">Anmeldungen → Korrekturanträge</a>.</p>
        <div><button class="knopf primaer" type="submit">Coaching-Portal speichern</button></div>
    </form>
    <div class="zwei-spalten">
        <div class="karte">
            <h2>Sicherung <?= info('sicherung') ?></h2>
            <p>Exportiert das komplette Turnier als JSON-Datei – für Backups und den Umzug lokal → online. Enthalten: Einstellungen (Startgeld, Urkunden-Texte, Ausruf-Aufstellung), Tatamis mit PINs, Vereine, Teilnehmer, Teams, Online-Anmeldungen, Klassen, Auslosungen, Tatami-Plan, Ergebnisse und Ausruf-Stand, Logos und Unterschriften der Urkunden sowie der Änderungsverlauf. Nach dem Import (Menü „Sicherung“) geht es nahtlos weiter.</p>
            <a class="knopf" href="<?= e(url("turnier/{$turnier['id']}/export")) ?>">Turnier exportieren</a>
        </div>
        <div class="karte">
            <h2>Hallen-Laptop</h2>
            <?php if (\Wettkampf\Kern\Http\Session::istAdmin()): ?><p class="klein"><a href="<?= e(url('halle')) ?>">Übersicht aller Turniere und Übernahme auf dem Laptop →</a></p><?php endif; ?>
            <?php $fuehrend = (string) ($turnier['fuehrung_instanz'] ?? ''); ?>
            <?php if ($fuehrend !== '' && hash_equals((string) ($instanzId ?? ''), $fuehrend)): ?>
                <p>Dieser Laptop führt das Turnier. Nach dem Turnier hier an Online zurückgeben.</p>
                <a class="knopf primaer" href="<?= e(url("turnier/{$turnier['id']}/halle")) ?>">An Online zurückgeben …</a>
            <?php elseif ($fuehrend !== ''): ?>
                <p>Das Turnier ist an einen Hallen-Laptop übergeben bzw. wird von Online geführt – hier nur lesend.</p>
                <a class="knopf" href="<?= e(url("turnier/{$turnier['id']}/halle")) ?>">Übergabe ansehen …</a>
            <?php else: ?>
                <p>Ohne verlässliches Internet in der Halle führt ein Laptop das Turnier; online ist es währenddessen nur lesend.</p>
                <a class="knopf" href="<?= e(url("turnier/{$turnier['id']}/halle")) ?>">An die Halle übergeben …</a>
            <?php endif; ?>
        </div>
        <div class="karte">
            <h2>Geräte koppeln</h2>
            <p>Tablets (Eingabe) und Handys (Ausruf) per QR-Code anmelden – Sie geben jedes Gerät frei und können es jederzeit sperren.</p>
            <a class="knopf" href="<?= e(url("turnier/{$turnier['id']}/geraete")) ?>">Geräte koppeln …</a>
        </div>
        <div class="karte">
            <h2>Turnierleitung</h2>
            <p>Wer dieses Turnier bearbeiten darf. Jede Turnierleitung kann weitere ernennen.</p>
            <a class="knopf" href="<?= e(url("turnier/{$turnier['id']}/leitung")) ?>">Turnierleitung verwalten</a>
        </div>
        <?php if (\Wettkampf\Kern\Http\Session::istAdmin()): ?>
            <form method="post" action="<?= e(url("turnier/{$turnier['id']}/loeschen")) ?>" class="karte formular" data-bestaetigen="Turnier endgültig löschen?">
                <?= csrf_feld() ?>
                <h2>Turnier löschen</h2>
                <p class="hilfe">Löscht das Turnier mit allen Klassen, Meldungen und Ergebnissen. Vorher exportieren!</p>
                <label>Zur Bestätigung den Turniernamen eingeben <input name="bestaetigung" autocomplete="off"></label>
                <div><button class="knopf gefahr" type="submit">Endgültig löschen</button></div>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
