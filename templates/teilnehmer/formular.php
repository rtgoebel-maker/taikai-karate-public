<?php
/**
 * @var array<string, mixed> $turnier
 * @var int|null $teilnahmeId
 * @var array<string, mixed> $werte
 * @var list<array<string, mixed>> $vereine
 * @var list<array<string, mixed>> $starts
 */
use Wettkampf\Domain\Graduierung;
use Wettkampf\Kern\Sprache;

$tid = $turnier['id'];
$neu = $teilnahmeId === null;
$this->layout('layout', ['titel' => $neu ? t('Teilnehmer erfassen') : t('Teilnehmer bearbeiten'), 'turnier' => $turnier, 'aktiv' => 'teilnehmer']);
$disziplinen = array_filter(explode(',', (string) $werte['disziplinen']));
?>
<h1><?= $neu ? te('Teilnehmer erfassen') : e($werte['vorname'] . ' ' . $werte['nachname']) ?></h1>
<div class="zwei-spalten">
    <form method="post" action="<?= e(url("turnier/$tid/teilnehmer")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <?php if (!$neu): ?><input type="hidden" name="teilnahme_id" value="<?= $teilnahmeId ?>"><?php endif; ?>
        <div class="zeile">
            <label><?= te('Vorname') ?> <input name="vorname" value="<?= e($werte['vorname']) ?>" required autofocus></label>
            <label><?= te('Nachname') ?> <input name="nachname" value="<?= e($werte['nachname']) ?>" required></label>
        </div>
        <div class="zeile">
            <label><?= te('Geschlecht') ?>
                <select name="geschlecht" required>
                    <option value=""><?= te('bitte wählen') ?></option>
                    <option value="w"<?= sel('w', $werte['geschlecht']) ?>><?= te('weiblich') ?></option>
                    <option value="m"<?= sel('m', $werte['geschlecht']) ?>><?= te('männlich') ?></option>
                    <option value="d"<?= sel('d', $werte['geschlecht']) ?>><?= te('divers') ?></option>
                </select>
            </label>
            <label><?= te('Graduierung') ?>
                <select name="graduierung" required>
                    <option value=""><?= te('bitte wählen') ?></option>
                    <?php foreach (Graduierung::auswahl() as $g => $text): ?>
                        <option value="<?= $g ?>"<?= sel($g, $werte['graduierung']) ?>><?= e($text) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="zeile">
            <label><?= te('Geburtsdatum') ?> <input type="date" name="geburtsdatum" value="<?= e($werte['geburtsdatum']) ?>"></label>
            <label><?= te('oder Alter am Turniertag') ?> <input type="number" name="alter_manuell" min="3" max="99" value="<?= e($werte['alter_manuell']) ?>"></label>
        </div>
        <div class="zeile">
            <label><?= te('Verein') ?>
                <select name="verein_id">
                    <option value=""><?= te('– neuer Verein (rechts) –') ?></option>
                    <?php foreach ($vereine as $v): ?>
                        <option value="<?= $v['id'] ?>"<?= sel($v['id'], $werte['verein_id']) ?>><?= e($v['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= te('Neuer Verein') ?> <input name="verein_name" placeholder="<?= te('nur wenn nicht in der Liste') ?>"></label>
        </div>
        <fieldset>
            <legend><?= te('Gemeldete Einzeldisziplinen') ?></legend>
            <label class="check"><input type="checkbox" name="disziplinen[]" value="kata"<?= chk(in_array('kata', $disziplinen, true)) ?>> <?= te('Kata Einzel') ?></label>
            <?php $kumite = current(array_filter($disziplinen, static fn ($d) => str_starts_with($d, 'kumite'))) ?: ''; ?>
            <label class="feld"><?= te('Kumite Einzel') ?>
                <select name="disziplinen[]">
                    <option value=""><?= te('nicht gemeldet') ?></option>
                    <option value="kumite"<?= sel('kumite', $kumite) ?>><?= te('gemeldet – Kampfform aus der Klasse') ?></option>
                    <option value="kumite:kihon_ippon"<?= sel('kumite:kihon_ippon', $kumite) ?>>Kihon-Ippon-Kumite</option>
                    <option value="kumite:jiyu_ippon"<?= sel('kumite:jiyu_ippon', $kumite) ?>>Jiyu-Ippon-Kumite</option>
                    <option value="kumite:jiyu"<?= sel('kumite:jiyu', $kumite) ?>>Jiyu-Kumite</option>
                </select>
            </label>
            <p class="hilfe"><?= te('Die passende Klasse wird automatisch aus Geschlecht, Alter am Turniertag und Graduierung bestimmt. Teams werden unter „Teams“ gebildet.') ?></p>
        </fieldset>
        <div class="zeile">
            <label><?= te('Status') ?>
                <select name="status">
                    <?php foreach (\Wettkampf\Turnier\StammdatenService::STATUS as $wert => $text): ?>
                        <option value="<?= $wert ?>"<?= sel($wert, $werte['status']) ?>><?= e(Sprache::t($text)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= te('Vermerk') ?> <input name="vermerk" value="<?= e($werte['vermerk']) ?>" placeholder="<?= te('z. B. Sportbrille erlaubt') ?>"></label>
        </div>
        <label class="check"><input type="checkbox" name="ausweis_geprueft" value="1"<?= chk($werte['ausweis_geprueft']) ?>> <?= te('DJKB-Ausweis geprüft (Jahressichtmarke, Grad)') ?></label>
        <label class="check"><input type="checkbox" name="nachmeldung" value="1"<?= chk($werte['nachmeldung']) ?>> <?= te('Nachmeldung') ?></label>
        <div class="knopfleiste">
            <button class="knopf primaer" type="submit"><?= te('Speichern') ?></button>
            <?php if ($neu): ?><button class="knopf" type="submit" name="weiter" value="neu"><?= te('Speichern & nächster') ?></button><?php endif; ?>
            <a class="knopf" href="<?= e(url("turnier/$tid/teilnehmer")) ?>"><?= te('Abbrechen') ?></a>
        </div>
    </form>

    <?php if (!$neu): ?>
        <div>
            <div class="karte">
                <h2><?= te('Starts') ?></h2>
                <?php if (!$starts): ?>
                    <p class="leise"><?= te('Noch keinem Start zugeordnet.') ?></p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($starts as $s): ?>
                            <li><a href="<?= e(url("turnier/$tid/klasse/{$s['klasse_id']}")) ?>"><strong><?= e($s['code']) ?></strong> <?= e($s['bezeichnung']) ?></a>
                                <?= $s['manuell'] ? '<span class="tag info">' . te('manuell') . '</span>' : '' ?>
                                <?= $s['status'] !== 'gemeldet' ? '<span class="tag">' . e(status_text($s['status'])) . '</span>' : '' ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php if (!in_array($werte['status'], \Wettkampf\Turnier\StammdatenService::AUSGEFALLEN, true)): ?>
                <form method="post" action="<?= e(url("turnier/$tid/teilnehmer/$teilnahmeId/nicht-erschienen")) ?>" class="karte formular"
                      data-bestaetigen="<?= te('Als nicht erschienen kennzeichnen? Alle offenen Kämpfe gehen kampflos (Kiken) an die Gegner.') ?>">
                    <?= csrf_feld() ?>
                    <h2><?= te('Nicht erschienen') ?></h2>
                    <p class="hilfe"><?= te('Der Starter scheidet aus allen Wettkämpfen aus: offene und künftige Kämpfe/Vorträge werden kampflos (Kiken) gewertet und so in Bäumen und auf der Statusseite angezeigt.') ?></p>
                    <?php $auslosbar = array_filter($starts, static fn ($s) => $s['neu_auslosbar']); ?>
                    <?php if ($auslosbar): ?>
                        <p class="hilfe"><?= th('Klassen ohne Ergebnis können stattdessen ohne den Starter <strong>neu ausgelost</strong> werden (neue Paarungen, Tatami bleibt):') ?></p>
                        <?php foreach ($auslosbar as $s): ?>
                            <label class="check"><input type="checkbox" name="neu_auslosen[]" value="<?= (int) $s['klasse_id'] ?>"> <?= te('{klasse} neu auslosen', ['klasse' => $s['code']]) ?></label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div><button class="knopf gefahr" type="submit"><?= te('Als nicht erschienen kennzeichnen') ?></button></div>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= e(url("turnier/$tid/teilnehmer/$teilnahmeId/loeschen")) ?>" class="karte" data-bestaetigen="<?= te('Teilnehmer wirklich löschen?') ?>">
                <?= csrf_feld() ?>
                <h2><?= te('Löschen') ?></h2>
                <p class="hilfe"><?= te('Nur möglich, solange keine Ergebnisse vorliegen. Danach bitte „nicht erschienen“ bzw. den Status „abgemeldet“ verwenden.') ?></p>
                <button class="knopf gefahr" type="submit"><?= te('Teilnehmer löschen') ?></button>
            </form>
        </div>
    <?php endif; ?>
</div>
