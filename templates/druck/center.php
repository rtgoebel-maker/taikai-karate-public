<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $tatamis
 * @var list<array<string, mixed>> $klassen
 * @var array<string, string> $urkunde Texte der Urkunden
 * @var array<string, array<string, mixed>> $logos vorhandene Logos (Art → Größe, Version)
 */
use Wettkampf\Turnier\LogoService;

$this->layout('layout', ['titel' => 'Druck', 'turnier' => $turnier, 'aktiv' => 'druck']);
$tid = $turnier['id'];
?>
<h1>Druckcenter <?= info('druck') ?></h1>
<p class="leise">Alle Ausdrucke sind auf A4 und Schwarz-Weiß ausgelegt. Im Browser über „Drucken“ ausgeben oder als PDF speichern.</p>
<?php $bestaetigt = array_filter($klassen, static fn ($k) => $k['status'] === 'bestaetigt'); ?>
<div class="druck-raster">
    <section class="karte spalten-2">
        <h2>Ausdrucke je Klasse <?= info('notfall') ?></h2>
        <form method="get" target="_blank" class="formular">
            <p class="hilfe">Klassen ankreuzen – ohne Auswahl werden alle ausgelosten Klassen gedruckt.</p>
            <div class="klassen-auswahl">
                <?php foreach ($klassen as $k): ?>
                    <label class="check"><input type="checkbox" name="klassen[]" value="<?= $k['id'] ?>"> <strong><?= e($k['code']) ?></strong> <span class="klein"><?= e($k['bezeichnung']) ?></span></label>
                <?php endforeach; ?>
                <?php if (!$klassen): ?><p class="leise">Noch keine Klasse ausgelost.</p><?php endif; ?>
            </div>
            <div class="knopfleiste">
                <button class="knopf" type="submit" formaction="<?= e(url("turnier/$tid/druck/baeume")) ?>">🌳 Turnierbäume &amp; Pool-Listen</button>
                <button class="knopf primaer" type="submit" formaction="<?= e(url("turnier/$tid/druck/notfall")) ?>">🛟 Notfall-Satz (Baum + Kampfliste)</button>
                <button class="knopf" type="submit" formaction="<?= e(url("turnier/$tid/druck/aushang")) ?>">📋 Aushang A4</button>
                <button class="knopf" type="submit" formaction="<?= e(url("turnier/$tid/druck/aushang")) ?>" name="format" value="a3">📋 Aushang A3</button>
            </div>
            <p class="hilfe"><strong>Notfall-Satz</strong>, falls die Technik ausfällt: je Klasse Baum bzw. Pool-Liste und Kampfliste mit Eintragefeldern und Feld „Tatami“ – vor Turnierbeginn ausdrucken und bereitlegen.
                <strong>Aushang</strong>: je Klasse ein großes Blatt (quer), ersetzt die Statusseite; Ergebnisse werden von Hand fortgeschrieben.</p>
        </form>
    </section>

    <section class="karte">
        <h2>Kampflisten</h2>
        <p class="hilfe">Für die Tischbesatzung: je Tatami in der Reihenfolge des Tatami-Plans, mit Feldern für Flaggen/Punkte, Sieger und Kata.</p>
        <table class="tabelle kompakt">
            <tbody>
            <?php foreach ($tatamis as $t): ?>
                <tr>
                    <td><a class="knopf" href="<?= e(url("turnier/$tid/druck/kampfliste/{$t['id']}")) ?>" target="_blank"><?= e($t['bezeichnung']) ?></a></td>
                    <td><a class="klein" href="<?= e(url("turnier/$tid/druck/kampfliste/{$t['id']}", ['offen' => 1])) ?>" target="_blank">nur offene</a></td>
                    <td class="rechts klein leise"><?= (int) $t['phasen'] ?> Phasen</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="karte">
        <h2>Ergebnisse &amp; Listen</h2>
        <div class="knopf-stapel">
            <a class="knopf" href="<?= e(url("turnier/$tid/druck/siegerliste")) ?>" target="_blank">Siegerliste (bestätigt)</a>
            <a class="knopf" href="<?= e(url("turnier/$tid/druck/siegerliste", ['alle' => 1])) ?>" target="_blank">Siegerliste inkl. vorläufiger</a>
            <a class="knopf" href="<?= e(url("turnier/$tid/druck/ergebnisse")) ?>" target="_blank">Ergebnisliste (alle Kämpfe)</a>
            <a class="knopf" href="<?= e(url("turnier/$tid/druck/vereine")) ?>" target="_blank">Starter nach Verein <span class="klein">(Einlass)</span></a>
        </div>
    </section>

    <section class="karte">
        <h2>Urkunden <?= info('urkunden') ?></h2>
        <p class="hilfe">Eine Urkunde je Platzierung (bei Teams je Mitglied) – nur für <strong>bestätigte</strong> Klassen: <strong><?= count($bestaetigt) ?> von <?= count($klassen) ?></strong>.</p>
        <div class="knopf-stapel">
            <a class="knopf<?= $bestaetigt ? ' primaer' : '' ?>" href="<?= e(url("turnier/$tid/druck/urkunden")) ?>" target="_blank">🏅 Alle Urkunden</a>
            <a class="knopf" href="<?= e(url("turnier/$tid/druck/urkunden", ['vordruck' => 1])) ?>" target="_blank">Nur Text (für Vordruck-Papier)</a>
            <a class="knopf" href="#urkunden-gestalten" data-aufklappen="urkunden-gestalten">Logos &amp; Unterschriften …</a>
        </div>
    </section>

    <section class="karte">
        <h2>Handouts</h2>
        <p class="hilfe">Kurzanleitungen mit Bildern – mit „Falsch eingegeben – was tun?“ – und der Flyer zum Weitergeben.</p>
        <div class="knopf-stapel">
        <?php foreach (\Wettkampf\Betrieb\Http\HilfeController::HANDOUTS as $name => $titel): ?>
            <a class="knopf" href="<?= e(url("handout/$name", ['turnier' => $tid])) ?>" target="_blank"><?= e($titel) ?></a>
        <?php endforeach; ?>
        </div>
    </section>

    <details class="karte spalten-3 urkunden-gestalten" id="urkunden-gestalten">
        <summary><h2>Urkunden gestalten</h2> <span class="klein leise">Logos, Untertitel und Unterschriften – einmal je Turnier</span></summary>
        <form method="post" action="<?= e(url("turnier/$tid/druck/urkunden")) ?>" class="formular" enctype="multipart/form-data">
            <?= csrf_feld() ?>
            <div class="urkunden-spalten">
            <div class="formular">
            <label>Untertitel <span class="hilfe">z. B. Veranstalter oder Verband</span><input name="untertitel" value="<?= e($urkunde['untertitel']) ?>" maxlength="200" placeholder="z. B. Karateverband Musterland e. V."></label>
            <fieldset class="logos">
                <legend>Unterschriften <span class="hilfe">Beschriftung unter der Linie; optional die Unterschrift als Bild (z. B. Scan, PNG mit transparentem Hintergrund)</span></legend>
                <?php foreach (LogoService::UNTERSCHRIFTEN as $art => $text): $l = $logos[$art] ?? null; ?>
                    <div class="logo-zeile">
                        <div class="logo-vorschau"><?php if ($l): ?><img src="<?= e(url("turnier/$tid/logo/$art", ['v' => $l['version']])) ?>" alt="<?= e($text) ?>"><?php else: ?><span class="klein leise">keine</span><?php endif; ?></div>
                        <div class="formular" style="gap:.4rem">
                            <label><?= e($text) ?> – Beschriftung <input name="<?= $art ?>" value="<?= e($urkunde[$art]) ?>" maxlength="100"></label>
                            <label>Unterschrift als Bild <input type="file" name="logo_<?= $art ?>" accept="image/png,image/jpeg,image/webp,image/gif"></label>
                        </div>
                        <?php if ($l): ?><label class="check klein"><input type="checkbox" name="logo_entfernen[]" value="<?= $art ?>"> entfernen</label><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </fieldset>
            </div>
            <div class="formular">
            <fieldset class="logos">
                <legend>Logos <span class="hilfe">optional · PNG, JPG, WebP oder GIF, max. 2 MB</span></legend>
                <?php foreach (LogoService::ARTEN as $art => $text): $l = $logos[$art] ?? null; ?>
                    <div class="logo-zeile">
                        <div class="logo-vorschau"><?php if ($l): ?><img src="<?= e(url("turnier/$tid/logo/$art", ['v' => $l['version']])) ?>" alt="<?= e($text) ?>"><?php else: ?><span class="klein leise">kein Logo</span><?php endif; ?></div>
                        <label><?= e($text) ?> <span class="hilfe"><?= ['verband' => 'oben mittig', 'verein' => 'oben links', 'dojo' => 'oben rechts'][$art] ?></span>
                            <input type="file" name="logo_<?= $art ?>" accept="image/png,image/jpeg,image/webp,image/gif"></label>
                        <?php if ($l): ?><label class="check klein"><input type="checkbox" name="logo_entfernen[]" value="<?= $art ?>"> entfernen</label><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </fieldset>
            <?php $h = $logos['hintergrund'] ?? null; ?>
            <fieldset class="logos">
                <legend>Hintergrundbild <span class="hilfe">optional · PNG, JPG oder WebP, max. 5 MB</span></legend>
                <div class="logo-zeile hintergrund-zeile">
                    <div class="logo-vorschau hochkant"><?php if ($h): ?><img src="<?= e(url("turnier/$tid/logo/hintergrund", ['v' => $h['version']])) ?>" alt="Hintergrundbild"><?php else: ?><span class="klein leise">Standard</span><?php endif; ?></div>
                    <label>Eigenes Hintergrundbild
                        <span class="hilfe"><?= $h ? 'ersetzt Rahmen und Wasserzeichen' : 'ohne eigenes Bild: Standard mit Rahmen und Wasserzeichen' ?> · A4 hochkant, z. B. 2480 × 3508 px (300 dpi);
                            Text, Logos und Unterschriften liegen darüber – in der Mitte hell lassen. Druckränder von ca. 1 cm bleiben weiß.</span>
                        <input type="file" name="logo_hintergrund" accept="image/png,image/jpeg,image/webp"></label>
                    <?php if ($h): ?><label class="check klein"><input type="checkbox" name="logo_entfernen[]" value="hintergrund"> entfernen</label><?php endif; ?>
                </div>
            </fieldset>
            </div>
            </div>
            <div><button class="knopf" type="submit">Urkunden-Einstellungen speichern</button></div>
        </form>
    </details>
</div>
