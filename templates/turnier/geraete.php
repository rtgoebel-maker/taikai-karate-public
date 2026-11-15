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
$this->layout('layout', ['titel' => t('Geräte koppeln'), 'turnier' => $turnier]);
$adresseQuery = in_array($basis, $adressen, true) && count($adressen) > 1 ? ['adresse' => $basis] : [];
?>
<?php /* neue Kopplungsanfragen ohne Neuladen von Hand: Stand alle 3 Sekunden prüfen, nur bei Änderung neu laden (app.js) */ ?>
<div hidden data-aktualisieren="<?= e(url("turnier/$tid/geraete/stand")) ?>" data-intervall="3000"></div>
<p class="obertitel"><a href="<?= e(url("turnier/$tid/einstellungen")) ?>"><?= te('Einstellungen') ?></a></p>
<h1><?= te('Geräte koppeln') ?></h1>
<p class="leise"><?= te('Tablets für die Eingabe und Handys für den Ausruf scannen den QR-Code ihrer Tatami, nennen ein Prüfwort – Sie geben das Gerät hier frei.') ?>
    <?= te('Ein abfotografierter QR-Code allein genügt also nicht. Freigegebene Geräte bleiben bis zum Turnierabschluss angemeldet; Ausruf-Geräte können keine Ergebnisse eintragen.') ?></p>

<?php if ($wartend): ?>
    <section class="karte karte-hinweis">
        <h2><?= te('Warten auf Freigabe ({n})', ['n' => count($wartend)]) ?></h2>
        <p class="hilfe"><?= te('Prüfwort mit der Anzeige auf dem Gerät vergleichen. Unbekannte Geräte ablehnen.') ?></p>
        <table class="tabelle">
            <thead><tr><th><?= te('Prüfwort') ?></th><th><?= te('Tatami') ?></th><th><?= te('Rolle') ?></th><th><?= te('Gerät') ?></th><th><?= te('seit') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($wartend as $g): ?>
                <tr>
                    <td><strong class="pruefwort klein"><?= e($g['pruefwort']) ?></strong></td>
                    <td><?= e($g['tatami']) ?></td>
                    <td><?= e(t(GeraeteKopplung::ROLLEN[$g['rolle']] ?? $g['rolle'])) ?></td>
                    <td><?= e($g['name']) ?></td>
                    <td class="klein"><?= te('{zeit} Uhr', ['zeit' => substr((string) $g['erstellt_am'], 11, 5)]) ?></td>
                    <td class="knopfleiste">
                        <form method="post" action="<?= e(url("turnier/$tid/geraete/{$g['id']}/freigeben")) ?>"><?= csrf_feld() ?>
                            <button class="knopf klein primaer" type="submit"><?= te('Freigeben') ?></button></form>
                        <form method="post" action="<?= e(url("turnier/$tid/geraete/{$g['id']}/sperren")) ?>"><?= csrf_feld() ?>
                            <button class="knopf klein" type="submit"><?= te('Ablehnen') ?></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<section class="karte">
    <h2><?= te('Gekoppelte Geräte ({n})', ['n' => count($frei)]) ?></h2>
    <?php if (!$frei): ?>
        <p class="leise"><?= te('Noch keine Geräte freigegeben.') ?></p>
    <?php else: ?>
        <table class="tabelle">
            <thead><tr><th><?= te('Tatami') ?></th><th><?= te('Rolle') ?></th><th><?= te('Gerät') ?></th><th><?= te('Freigegeben') ?></th><th><?= te('Zuletzt aktiv') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($frei as $g): ?>
                <tr>
                    <td><?= e($g['tatami']) ?></td>
                    <td><?= e(t(GeraeteKopplung::ROLLEN[$g['rolle']] ?? $g['rolle'])) ?></td>
                    <td><?= e($g['name']) ?> <span class="leise klein"><?= te('(Gerät {nr})', ['nr' => (int) $g['id']]) ?></span></td>
                    <td class="klein"><?= e(substr((string) $g['freigegeben_am'], 11, 5)) ?> · <?= e((string) $g['freigegeben_von']) ?></td>
                    <td class="klein"><?= $g['zuletzt_am'] ? e(substr((string) $g['zuletzt_am'], 0, 10) === date('Y-m-d') ? t('{zeit} Uhr', ['zeit' => substr((string) $g['zuletzt_am'], 11, 5)]) : datum_de((string) $g['zuletzt_am'])) : '–' ?></td>
                    <td>
                        <form method="post" action="<?= e(url("turnier/$tid/geraete/{$g['id']}/sperren")) ?>" data-bestaetigen="<?= te('Gerät „{name}“ sofort abmelden und sperren?', ['name' => $g['name']]) ?>"><?= csrf_feld() ?>
                            <button class="knopf klein gefahr" type="submit"><?= te('Sperren') ?></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php if ($gesperrt): ?>
        <form method="post" action="<?= e(url("turnier/$tid/geraete/aufraeumen")) ?>" class="knopfleiste">
            <?= csrf_feld() ?>
            <span class="klein leise"><?= te('{n} gesperrt:', ['n' => count($gesperrt)]) ?> <?= e(implode(', ', array_map(static fn($g) => $g['name'] . ' (' . $g['tatami'] . ')', $gesperrt))) ?></span>
            <button class="knopf klein" type="submit"><?= te('Aus der Liste entfernen') ?></button>
        </form>
    <?php endif; ?>
</section>

<section class="karte">
    <div class="knopfleiste" style="justify-content:space-between">
        <h2><?= te('QR-Codes') ?></h2>
        <a class="knopf" href="<?= e(url("turnier/$tid/geraete/druck", $adresseQuery)) ?>" target="_blank" rel="noopener"><?= te('QR-Codes drucken') ?></a>
    </div>
    <?php if ($adressen): ?>
        <form method="get" action="<?= e(url("turnier/$tid/geraete")) ?>" class="formular">
            <label><?= te('Adresse dieses Laptops im Hallennetz') ?> <span class="hilfe"><?= te('Sie haben die Seite über „localhost“ geöffnet – die Geräte brauchen die Netzwerkadresse.') ?></span>
                <select name="adresse" data-auto-absenden>
                    <?php foreach ($adressen as $a): ?><option value="<?= e($a) ?>"<?= $a === $basis ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
                </select></label>
            <noscript><button class="knopf klein" type="submit"><?= te('Übernehmen') ?></button></noscript>
        </form>
    <?php endif; ?>
    <p class="hilfe"><?= th('Die Codes führen zu <code>{adresse}</code>. Die Geräte müssen diese Adresse erreichen (gleiches WLAN bzw. Internet).', ['adresse' => $basis]) ?></p>
    <?php if (!$qr): ?>
        <p class="leise"><?= te('Noch keine Tatamis angelegt (Einstellungen → Tatamis & Zugänge).') ?></p>
    <?php endif; ?>
    <div class="qr-raster">
        <?php foreach ($qr as $t): ?>
            <div class="qr-tatami">
                <h3><?= e($t['bezeichnung']) ?></h3>
                <div class="qr-paar">
                    <?php foreach ($t['codes'] as $rolle => $c): ?>
                        <figure>
                            <?= $c['svg'] /* vom Server erzeugtes SVG (chillerlan/php-qrcode), keine Nutzereingabe */ ?>
                            <figcaption><?= e(t(GeraeteKopplung::ROLLEN[$rolle])) ?>
                                <a class="klein qr-link" href="<?= e($c['url']) ?>" title="<?= te('Link für Geräte ohne Kamera (kopieren und dort öffnen)') ?>"><?= te('Link') ?></a></figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
                <form method="post" action="<?= e(url("turnier/$tid/geraete/qr-erneuern")) ?>" data-bestaetigen="<?= te('Neue QR-Codes für {tatami}? Die bisherigen (auch ausgedruckte) gelten dann nicht mehr; gekoppelte Geräte bleiben angemeldet.', ['tatami' => $t['bezeichnung']]) ?>">
                    <?= csrf_feld() ?>
                    <input type="hidden" name="tatami_id" value="<?= (int) $t['id'] ?>">
                    <button class="knopf klein" type="submit"><?= te('Neue Codes') ?></button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<form method="post" action="<?= e(url("turnier/$tid/geraete/pin")) ?>" class="karte formular">
    <?= csrf_feld() ?>
    <h2><?= te('Notlösung: Anmeldung per PIN') ?></h2>
    <p class="hilfe"><?= te('Ohne Kamera oder wenn die Kopplung nicht klappt, kann sich ein Gerät weiter mit der Tatami-PIN anmelden (Einstellungen → Tatamis & Zugänge).') ?>
        <?= te('Abgeschaltet sind nur noch freigegebene Geräte möglich; per PIN angemeldete Geräte werden sofort abgemeldet.') ?></p>
    <label class="check"><input type="checkbox" name="erlaubt" value="1"<?= (int) $turnier['tisch_pin_erlaubt'] ? ' checked' : '' ?>> <?= te('Anmeldung per PIN erlauben') ?></label>
    <div><button class="knopf" type="submit"><?= te('Speichern') ?></button></div>
</form>
