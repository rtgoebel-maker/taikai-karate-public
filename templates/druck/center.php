<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $tatamis
 * @var list<array<string, mixed>> $klassen
 */
use Wettkampf\Kern\Sprache;

$this->layout('layout', ['titel' => t('Druck'), 'turnier' => $turnier, 'aktiv' => 'druck']);
$tid = $turnier['id'];
?>
<h1><?= te('Druckcenter') ?> <?= info('druck') ?></h1>
<p class="leise"><?= te('Alle Ausdrucke sind auf A4 und Schwarz-Weiß ausgelegt. Im Browser über „Drucken“ ausgeben oder als PDF speichern.') ?></p>
<?php $bestaetigt = array_filter($klassen, static fn ($k) => $k['status'] === 'bestaetigt'); ?>
<div class="druck-raster">
    <section class="karte spalten-2">
        <h2><?= te('Ausdrucke je Klasse') ?> <?= info('notfall') ?></h2>
        <form method="get" target="_blank" class="formular">
            <p class="hilfe"><?= te('Klassen ankreuzen – ohne Auswahl werden alle ausgelosten Klassen gedruckt.') ?></p>
            <div class="klassen-auswahl">
                <?php foreach ($klassen as $k): ?>
                    <label class="check"><input type="checkbox" name="klassen[]" value="<?= $k['id'] ?>"> <strong><?= e($k['code']) ?></strong> <span class="klein"><?= e($k['bezeichnung']) ?></span></label>
                <?php endforeach; ?>
                <?php if (!$klassen): ?><p class="leise"><?= te('Noch keine Klasse ausgelost.') ?></p><?php endif; ?>
            </div>
            <div class="knopfleiste">
                <button class="knopf" type="submit" formaction="<?= e(url("turnier/$tid/druck/baeume")) ?>">🌳 <?= te('Turnierbäume & Pool-Listen') ?></button>
                <button class="knopf primaer" type="submit" formaction="<?= e(url("turnier/$tid/druck/notfall")) ?>">🛟 <?= te('Notfall-Satz (Baum + Kampfliste)') ?></button>
                <button class="knopf" type="submit" formaction="<?= e(url("turnier/$tid/druck/aushang")) ?>">📋 <?= te('Aushang A4') ?></button>
                <button class="knopf" type="submit" formaction="<?= e(url("turnier/$tid/druck/aushang")) ?>" name="format" value="a3">📋 <?= te('Aushang A3') ?></button>
            </div>
            <p class="hilfe"><?= th('<strong>Notfall-Satz</strong>, falls die Technik ausfällt: je Klasse Baum bzw. Pool-Liste und Kampfliste mit Eintragefeldern und Feld „Tatami“ – vor Turnierbeginn ausdrucken und bereitlegen.') ?>
                <?= th('<strong>Aushang</strong>: je Klasse ein großes Blatt (quer), ersetzt die Statusseite; Ergebnisse werden von Hand fortgeschrieben.') ?></p>
        </form>
    </section>

    <section class="karte">
        <h2><?= te('Kampflisten') ?></h2>
        <p class="hilfe"><?= te('Für die Tischbesatzung: je Tatami in der Reihenfolge des Tatami-Plans, mit Feldern für Flaggen/Punkte, Sieger und Kata.') ?></p>
        <table class="tabelle kompakt">
            <tbody>
            <?php foreach ($tatamis as $t): ?>
                <tr>
                    <td><a class="knopf" href="<?= e(url("turnier/$tid/druck/kampfliste/{$t['id']}")) ?>" target="_blank"><?= e($t['bezeichnung']) ?></a></td>
                    <td><a class="klein" href="<?= e(url("turnier/$tid/druck/kampfliste/{$t['id']}", ['offen' => 1])) ?>" target="_blank"><?= te('nur offene') ?></a></td>
                    <td class="rechts klein leise"><?= te('{n} Phasen', ['n' => (int) $t['phasen']]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="karte">
        <h2><?= te('Ergebnisse & Listen') ?></h2>
        <div class="knopf-stapel">
            <a class="knopf" href="<?= e(url("turnier/$tid/druck/siegerliste")) ?>" target="_blank"><?= te('Siegerliste (bestätigt)') ?></a>
            <a class="knopf" href="<?= e(url("turnier/$tid/druck/siegerliste", ['alle' => 1])) ?>" target="_blank"><?= te('Siegerliste inkl. vorläufiger') ?></a>
            <a class="knopf" href="<?= e(url("turnier/$tid/druck/ergebnisse")) ?>" target="_blank"><?= te('Ergebnisliste (alle Kämpfe)') ?></a>
            <a class="knopf" href="<?= e(url("turnier/$tid/druck/vereine")) ?>" target="_blank"><?= te('Starter nach Verein') ?> <span class="klein"><?= te('(Einlass)') ?></span></a>
        </div>
    </section>

    <section class="karte">
        <h2><?= te('Urkunden') ?> <?= info('urkunden') ?></h2>
        <p class="hilfe"><?= th('Eine Urkunde je Platzierung (bei Teams je Mitglied) – nur für <strong>bestätigte</strong> Klassen: <strong>{n} von {alle}</strong>.', ['n' => count($bestaetigt), 'alle' => count($klassen)]) ?></p>
        <div class="knopf-stapel">
            <a class="knopf<?= $bestaetigt ? ' primaer' : '' ?>" href="<?= e(url("turnier/$tid/druck/urkunden")) ?>" target="_blank">🏅 <?= te('Alle Urkunden') ?></a>
            <a class="knopf" href="<?= e(url("turnier/$tid/druck/urkunden", ['vordruck' => 1])) ?>" target="_blank"><?= te('Nur Text (für Vordruck-Papier)') ?></a>
            <a class="knopf" href="<?= e(url("turnier/$tid/druck/urkunden/gestalten")) ?>">🎨 <?= te('Urkunden gestalten …') ?> <span class="klein"><?= te('(Logos, Unterschriften, Vorschau)') ?></span></a>
        </div>
    </section>

    <section class="karte">
        <h2><?= te('Handouts') ?></h2>
        <p class="hilfe"><?= te('Kurzanleitungen mit Bildern – mit „Falsch eingegeben – was tun?“ – und der Flyer zum Weitergeben.') ?></p>
        <div class="knopf-stapel">
        <?php foreach (\Wettkampf\Betrieb\Http\HilfeController::HANDOUTS as $name => $titel): ?>
            <a class="knopf" href="<?= e(url("handout/$name", ['turnier' => $tid])) ?>" target="_blank"><?= e(Sprache::t($titel)) ?></a>
        <?php endforeach; ?>
        </div>
    </section>
</div>
