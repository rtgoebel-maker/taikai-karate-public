<?php
/**
 * Geräte koppeln: QR-Codes je Tatami und Rolle, wartende Geräte freigeben, gekoppelte sperren.
 * @var array<string, mixed> $turnier
 * @var list<array{id: int, bezeichnung: string, codes: array<string, array{url: string, svg: string}>}> $qr
 * @var list<array<string, mixed>> $geraete
 * @var string $basis
 * @var list<string> $adressen
 */
use Wettkampf\Durchfuehrung\GeraeteKopplung;

$tid = (int) $turnier['id'];
$wartend = array_values(array_filter($geraete, static fn($g) => $g['status'] === 'wartet'));
$frei = array_values(array_filter($geraete, static fn($g) => $g['status'] === 'frei'));
$gesperrt = array_values(array_filter($geraete, static fn($g) => $g['status'] === 'gesperrt'));
$this->layout('layout', ['titel' => 'Geräte koppeln', 'turnier' => $turnier]);
$adresseQuery = in_array($basis, $adressen, true) && count($adressen) > 1 ? ['adresse' => $basis] : [];
?>
<?php /* neue Kopplungsanfragen ohne Neuladen von Hand: Stand alle 3 Sekunden prüfen, nur bei Änderung neu laden (app.js) */ ?>
<div hidden data-aktualisieren="<?= e(url("turnier/$tid/geraete/stand")) ?>" data-intervall="3000"></div>
<p class="obertitel"><a href="<?= e(url("turnier/$tid/einstellungen")) ?>">Einstellungen</a></p>
<h1>Geräte koppeln</h1>
<p class="leise">Tablets für die Eingabe und Handys für den Ausruf scannen den QR-Code ihrer Tatami, nennen ein Prüfwort – Sie geben das Gerät hier frei.
    Ein abfotografierter QR-Code allein genügt also nicht. Freigegebene Geräte bleiben bis zum Turnierabschluss angemeldet; Ausruf-Geräte können keine Ergebnisse eintragen.</p>

<?php if ($wartend): ?>
    <section class="karte karte-hinweis">
        <h2>Warten auf Freigabe (<?= count($wartend) ?>)</h2>
        <p class="hilfe">Prüfwort mit der Anzeige auf dem Gerät vergleichen. Unbekannte Geräte ablehnen.</p>
        <table class="tabelle">
            <thead><tr><th>Prüfwort</th><th>Tatami</th><th>Rolle</th><th>Gerät</th><th>seit</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($wartend as $g): ?>
                <tr>
                    <td><strong class="pruefwort klein"><?= e($g['pruefwort']) ?></strong></td>
                    <td><?= e($g['tatami']) ?></td>
                    <td><?= e(GeraeteKopplung::ROLLEN[$g['rolle']] ?? $g['rolle']) ?></td>
                    <td><?= e($g['name']) ?></td>
                    <td class="klein"><?= e(substr((string) $g['erstellt_am'], 11, 5)) ?> Uhr</td>
                    <td class="knopfleiste">
                        <form method="post" action="<?= e(url("turnier/$tid/geraete/{$g['id']}/freigeben")) ?>"><?= csrf_feld() ?>
                            <button class="knopf klein primaer" type="submit">Freigeben</button></form>
                        <form method="post" action="<?= e(url("turnier/$tid/geraete/{$g['id']}/sperren")) ?>"><?= csrf_feld() ?>
                            <button class="knopf klein" type="submit">Ablehnen</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<section class="karte">
    <h2>Gekoppelte Geräte (<?= count($frei) ?>)</h2>
    <?php if (!$frei): ?>
        <p class="leise">Noch keine Geräte freigegeben.</p>
    <?php else: ?>
        <table class="tabelle">
            <thead><tr><th>Tatami</th><th>Rolle</th><th>Gerät</th><th>Freigegeben</th><th>Zuletzt aktiv</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($frei as $g): ?>
                <tr>
                    <td><?= e($g['tatami']) ?></td>
                    <td><?= e(GeraeteKopplung::ROLLEN[$g['rolle']] ?? $g['rolle']) ?></td>
                    <td><?= e($g['name']) ?> <span class="leise klein">(Gerät <?= (int) $g['id'] ?>)</span></td>
                    <td class="klein"><?= e(substr((string) $g['freigegeben_am'], 11, 5)) ?> · <?= e((string) $g['freigegeben_von']) ?></td>
                    <td class="klein"><?= $g['zuletzt_am'] ? e(substr((string) $g['zuletzt_am'], 0, 10) === date('Y-m-d') ? substr((string) $g['zuletzt_am'], 11, 5) . ' Uhr' : datum_de((string) $g['zuletzt_am'])) : '–' ?></td>
                    <td>
                        <form method="post" action="<?= e(url("turnier/$tid/geraete/{$g['id']}/sperren")) ?>" data-bestaetigen="Gerät „<?= e($g['name']) ?>“ sofort abmelden und sperren?"><?= csrf_feld() ?>
                            <button class="knopf klein gefahr" type="submit">Sperren</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php if ($gesperrt): ?>
        <form method="post" action="<?= e(url("turnier/$tid/geraete/aufraeumen")) ?>" class="knopfleiste">
            <?= csrf_feld() ?>
            <span class="klein leise"><?= count($gesperrt) ?> gesperrt: <?= e(implode(', ', array_map(static fn($g) => $g['name'] . ' (' . $g['tatami'] . ')', $gesperrt))) ?></span>
            <button class="knopf klein" type="submit">Aus der Liste entfernen</button>
        </form>
    <?php endif; ?>
</section>

<section class="karte">
    <div class="knopfleiste" style="justify-content:space-between">
        <h2>QR-Codes</h2>
        <a class="knopf" href="<?= e(url("turnier/$tid/geraete/druck", $adresseQuery)) ?>" target="_blank" rel="noopener">QR-Codes drucken</a>
    </div>
    <?php if ($adressen): ?>
        <form method="get" action="<?= e(url("turnier/$tid/geraete")) ?>" class="formular">
            <label>Adresse dieses Laptops im Hallennetz <span class="hilfe">Sie haben die Seite über „localhost“ geöffnet – die Geräte brauchen die Netzwerkadresse.</span>
                <select name="adresse" data-auto-absenden>
                    <?php foreach ($adressen as $a): ?><option value="<?= e($a) ?>"<?= $a === $basis ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
                </select></label>
            <noscript><button class="knopf klein" type="submit">Übernehmen</button></noscript>
        </form>
    <?php endif; ?>
    <p class="hilfe">Die Codes führen zu <code><?= e($basis) ?></code>. Die Geräte müssen diese Adresse erreichen (gleiches WLAN bzw. Internet).</p>
    <?php if (!$qr): ?>
        <p class="leise">Noch keine Tatamis angelegt (Einstellungen → Tatamis &amp; Zugänge).</p>
    <?php endif; ?>
    <div class="qr-raster">
        <?php foreach ($qr as $t): ?>
            <div class="qr-tatami">
                <h3><?= e($t['bezeichnung']) ?></h3>
                <div class="qr-paar">
                    <?php foreach ($t['codes'] as $rolle => $c): ?>
                        <figure>
                            <?= $c['svg'] /* vom Server erzeugtes SVG (chillerlan/php-qrcode), keine Nutzereingabe */ ?>
                            <figcaption><?= e(GeraeteKopplung::ROLLEN[$rolle]) ?>
                                <a class="klein qr-link" href="<?= e($c['url']) ?>" title="Link für Geräte ohne Kamera (kopieren und dort öffnen)">Link</a></figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
                <form method="post" action="<?= e(url("turnier/$tid/geraete/qr-erneuern")) ?>" data-bestaetigen="Neue QR-Codes für <?= e($t['bezeichnung']) ?>? Die bisherigen (auch ausgedruckte) gelten dann nicht mehr; gekoppelte Geräte bleiben angemeldet.">
                    <?= csrf_feld() ?>
                    <input type="hidden" name="tatami_id" value="<?= (int) $t['id'] ?>">
                    <button class="knopf klein" type="submit">Neue Codes</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<form method="post" action="<?= e(url("turnier/$tid/geraete/pin")) ?>" class="karte formular">
    <?= csrf_feld() ?>
    <h2>Notlösung: Anmeldung per PIN</h2>
    <p class="hilfe">Ohne Kamera oder wenn die Kopplung nicht klappt, kann sich ein Gerät weiter mit der Tatami-PIN anmelden (Einstellungen → Tatamis &amp; Zugänge).
        Abgeschaltet sind nur noch freigegebene Geräte möglich; per PIN angemeldete Geräte werden sofort abgemeldet.</p>
    <label class="check"><input type="checkbox" name="erlaubt" value="1"<?= (int) $turnier['tisch_pin_erlaubt'] ? ' checked' : '' ?>> Anmeldung per PIN erlauben</label>
    <div><button class="knopf" type="submit">Speichern</button></div>
</form>
