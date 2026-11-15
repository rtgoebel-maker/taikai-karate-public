<?php
/**
 * Druckcenter → Urkunden gestalten (eigene Seite): links die Einstellungen in Schritten, rechts die mitlaufende Vorschau.
 *
 * @var array<string, mixed> $turnier
 * @var array<string, mixed> $urkunde Texte und Logo-Lage (DruckController::urkundenEinstellungen)
 * @var array<string, array<string, mixed>> $logos vorhandene Bilder (Art → Größe, Version)
 * @var int $bestaetigt Anzahl bestätigter Klassen
 */
use Wettkampf\Ausgaben\UrkundenLayout;
use Wettkampf\Kern\Sprache;
use Wettkampf\Turnier\LogoService;

$this->layout('layout', ['titel' => t('Urkunden gestalten'), 'turnier' => $turnier, 'aktiv' => 'druck']);
$tid = $turnier['id'];
$vorschau = url("turnier/$tid/druck/urkunden/vorschau");
$bild = static fn(string $art, string $alt): string => isset($logos[$art])
    ? '<img src="' . e(url("turnier/$tid/logo/$art", ['v' => $logos[$art]['version']])) . '" alt="' . e($alt) . '">'
    : '';
?>
<p class="klein"><a href="<?= e(url("turnier/$tid/druck")) ?>">← <?= te('Druckcenter') ?></a></p>
<h1><?= te('Urkunden gestalten') ?> <?= info('urkunden') ?></h1>
<p class="leise"><?= te('Einmal je Turnier: Texte, Logos, Unterschriften und Hintergrund. Die Vorschau zeigt eine Musterurkunde.') ?></p>

<form method="post" action="<?= e(url("turnier/$tid/druck/urkunden")) ?>" enctype="multipart/form-data" class="urkunde-gestalten">
    <?= csrf_feld() ?>
    <div class="urkunde-einstellungen">
        <section class="karte formular">
            <h2><span class="schritt-nr">1</span> <?= te('Texte') ?></h2>
            <label><?= te('Untertitel') ?> <span class="hilfe"><?= te('z. B. Veranstalter oder Verband') ?></span>
                <input name="untertitel" value="<?= e($urkunde['untertitel']) ?>" maxlength="200" placeholder="<?= te('z. B. Karateverband Musterland e. V.') ?>" data-urkunde-feld></label>
            <div class="zeile urkunde-zwei">
                <?php foreach (LogoService::UNTERSCHRIFTEN as $art => $text): ?>
                    <label><?= te('{text} – Beschriftung', ['text' => Sprache::t($text)]) ?>
                        <input name="<?= $art ?>" value="<?= e($urkunde[$art]) ?>" maxlength="100" data-urkunde-feld></label>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="karte">
            <h2><span class="schritt-nr">2</span> <?= te('Logos') ?> <span class="hilfe"><?= te('optional · PNG, JPG, WebP oder GIF, max. 2 MB') ?></span></h2>
            <p class="hilfe"><?= te('Position auf einem festen Raster, Höhe in mm – die Breite folgt dem Seitenverhältnis, nichts wird verzerrt.') ?></p>
            <div class="logo-karten">
                <?php foreach (LogoService::ARTEN as $art => $text): $text = Sprache::t($text); ?>
                    <fieldset class="logo-karte">
                        <legend><?= e($text) ?></legend>
                        <div class="logo-vorschau"><?= $bild($art, $text) ?: '<span class="klein leise">' . te('kein Logo') . '</span>' ?></div>
                        <label class="logo-datei"><span class="unsichtbar"><?= te('Bild auswählen') ?></span>
                            <input type="file" name="logo_<?= $art ?>" accept="image/png,image/jpeg,image/webp,image/gif"></label>
                        <div class="logo-lage">
                            <label><?= te('Position') ?>
                                <select name="logo_pos[<?= $art ?>]" data-urkunde-feld>
                                    <?php foreach (UrkundenLayout::POSITIONEN as $pos => $posText): ?>
                                        <option value="<?= e($pos) ?>"<?= sel($urkunde['logos'][$art]['pos'], $pos) ?>><?= e(t($posText)) ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                            <label><?= te('Höhe (mm)') ?>
                                <input type="number" name="logo_groesse[<?= $art ?>]" value="<?= (int) $urkunde['logos'][$art]['groesse'] ?>" min="<?= UrkundenLayout::GROESSE_MIN ?>" max="<?= UrkundenLayout::GROESSE_MAX ?>" step="1" data-urkunde-feld></label>
                        </div>
                        <?php if (isset($logos[$art])): ?><label class="check klein"><input type="checkbox" name="logo_entfernen[]" value="<?= $art ?>"> <?= te('Logo entfernen') ?></label><?php endif; ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="karte">
            <h2><span class="schritt-nr">3</span> <?= te('Unterschriften als Bild') ?> <span class="hilfe"><?= te('optional · z. B. Scan, PNG mit transparentem Hintergrund') ?></span></h2>
            <div class="logo-karten">
                <?php foreach (LogoService::UNTERSCHRIFTEN as $art => $text): $text = Sprache::t($text); ?>
                    <fieldset class="logo-karte">
                        <legend><?= e($text) ?></legend>
                        <div class="logo-vorschau"><?= $bild($art, $text) ?: '<span class="klein leise">' . te('keine') . '</span>' ?></div>
                        <label class="logo-datei"><span class="unsichtbar"><?= te('Bild auswählen') ?></span>
                            <input type="file" name="logo_<?= $art ?>" accept="image/png,image/jpeg,image/webp,image/gif"></label>
                        <?php if (isset($logos[$art])): ?><label class="check klein"><input type="checkbox" name="logo_entfernen[]" value="<?= $art ?>"> <?= te('entfernen') ?></label><?php endif; ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="karte">
            <h2><span class="schritt-nr">4</span> <?= te('Hintergrundbild') ?> <span class="hilfe"><?= te('optional · PNG, JPG oder WebP, max. 5 MB') ?></span></h2>
            <div class="logo-zeile hintergrund-zeile">
                <div class="logo-vorschau hochkant"><?= $bild('hintergrund', t('Hintergrundbild')) ?: '<span class="klein leise">' . te('Standard') . '</span>' ?></div>
                <label><?= te('Eigenes Hintergrundbild') ?>
                    <span class="hilfe"><?= isset($logos['hintergrund']) ? te('ersetzt Rahmen und Wasserzeichen') : te('ohne eigenes Bild: Standard mit Rahmen und Wasserzeichen') ?> · <?= te('A4 hochkant, z. B. 2480 × 3508 px (300 dpi);') ?>
                        <?= te('Text, Logos und Unterschriften liegen darüber – in der Mitte hell lassen. Druckränder von ca. 1 cm bleiben weiß.') ?></span>
                    <input type="file" name="logo_hintergrund" accept="image/png,image/jpeg,image/webp"></label>
                <?php if (isset($logos['hintergrund'])): ?><label class="check klein"><input type="checkbox" name="logo_entfernen[]" value="hintergrund"> <?= te('entfernen') ?></label><?php endif; ?>
            </div>
        </section>
    </div>

    <aside class="urkunde-vorschau-spalte">
        <div class="karte">
            <h2><?= te('Vorschau') ?></h2>
            <p class="hilfe"><?= te('Musterurkunde – Position, Größe und Texte erscheinen sofort, neue Bilder nach dem Speichern') ?></p>
            <div class="urkunde-vorschau-rahmen"><iframe title="<?= te('Vorschau der Urkunde') ?>" data-urkunde-vorschau="<?= e($vorschau) ?>" src="<?= e($vorschau) ?>"></iframe></div>
            <div class="knopf-stapel">
                <button class="knopf primaer" type="submit"><?= te('Urkunden-Einstellungen speichern') ?></button>
                <a class="knopf" href="<?= e($vorschau) ?>" target="_blank"><?= te('Vorschau in voller Größe ↗') ?></a>
                <a class="knopf" href="<?= e(url("turnier/$tid/druck/urkunden")) ?>" target="_blank">🏅 <?= te('Alle Urkunden') ?> <span class="klein">(<?= te('{n} bestätigte Klassen', ['n' => $bestaetigt]) ?>)</span></a>
            </div>
        </div>
    </aside>
</form>
<script src="<?= e(asset('urkunde-vorschau.js')) ?>" defer></script>
