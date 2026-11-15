<?php
/**
 * @var list<array<string, mixed>> $vereine
 * @var list<array{a: array<string, mixed>, b: array<string, mixed>}> $doppelt vermutlich doppelte Vereine
 * @var int $leer Vereine ohne Athleten, Teams und Anmeldungen
 * @var bool|null $nurLesen Demo-Nutzer: nur ansehen
 */
$nurLesen ??= false;
$this->layout('layout', ['titel' => t('Vereine')]);
?>
<div class="seitenkopf">
    <h1><?= te('Vereine / Dojos') ?> <span class="leise klein">(<?= count($vereine) ?>)</span> <?= info('vereine') ?></h1>
    <?php if (!$nurLesen): ?><a class="knopf" href="#neuer-verein" data-aufklappen="neuer-verein">+ <?= te('Neuer Verein') ?></a><?php endif; ?>
</div>
<p class="leise"><?= th('Eine gemeinsame Liste für alle Turniere. Vereine entstehen <strong>automatisch</strong> – bei der Freigabe einer Online-Anmeldung, beim Import oder beim Erfassen eines Teilnehmers. Hier korrigieren Sie Schreibweisen, hinterlegen einen <strong>Kurznamen</strong> (Turnierbaum, Tablet, Statusseite) und führen <strong>doppelte Vereine</strong> zusammen. Ein Klick auf den Namen zeigt Teilnehmer, ihre Turniere und die Coach-Konten.') ?></p>

<?php if ($doppelt): ?>
    <section class="karte karte-hinweis">
        <h2><?= te('Vermutlich doppelt ({n})', ['n' => count($doppelt)]) ?></h2>
        <p class="hilfe"><?= te('Ähnliche Namen – meist dasselbe Dojo in zwei Schreibweisen. Vor dem Zusammenführen sehen Sie, was umzieht.') ?></p>
        <ul class="doppelt-liste">
            <?php foreach ($doppelt as $d): ?>
                <li><?= te('„{a}“ ({na} Athleten) und „{b}“ ({nb})', ['a' => $d['a']['name'], 'na' => (int) $d['a']['athleten'], 'b' => $d['b']['name'], 'nb' => (int) $d['b']['athleten']]) ?>
                    <a class="knopf klein" href="<?= e(url('vereine/zusammenfuehren', ['von' => $d['a']['id'], 'nach' => $d['b']['id']])) ?>"><?= te('Zusammenführen …') ?></a></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($leer > 0): ?>
    <form method="post" action="<?= e(url('vereine/leere-loeschen')) ?>" class="karte knopfleiste" data-bestaetigen="<?= te('{n} Vereine ohne Athleten, Teams und Anmeldungen löschen?', ['n' => $leer]) ?>">
        <?= csrf_feld() ?>
        <span><?= $leer === 1 ? te('Ein Verein wird von keinem Turnier genutzt (keine Athleten, Teams, Anmeldungen oder Coach-Konten).') : te('{n} Vereine werden von keinem Turnier genutzt (keine Athleten, Teams, Anmeldungen oder Coach-Konten).', ['n' => $leer]) ?></span>
        <button class="knopf klein gefahr" type="submit"><?= te('Leere Vereine löschen') ?></button>
    </form>
<?php endif; ?>

<?php if (!$nurLesen): ?>
<details class="karte neues-konto" id="neuer-verein">
    <summary><h2><?= te('Neuer Verein') ?></h2></summary>
    <form method="post" action="<?= e(url('vereine')) ?>" class="formular" style="max-width:640px">
        <?= csrf_feld() ?>
        <p class="hilfe"><?= te('Nur nötig, um ein Coach-Konto für ein Dojo anzulegen, das noch nie gemeldet hat – sonst entsteht der Verein mit der ersten Meldung.') ?></p>
        <div class="zeile">
            <label><?= te('Name') ?> <input name="name" required maxlength="200"></label>
            <label><?= te('Kurzname') ?> <span class="hilfe"><?= te('optional, max. 30 Zeichen') ?></span> <input name="kurzname" maxlength="30"></label>
        </div>
        <div><button class="knopf primaer" type="submit"><?= te('Anlegen') ?></button></div>
    </form>
</details>
<?php endif; ?>

<?php if (count($vereine) > 8): ?>
    <label class="suche-konten"><?= te('Suchen') ?> <input type="search" data-tabellenfilter="vereine-liste" placeholder="<?= te('Name oder Kurzname') ?>" autocomplete="off"></label>
<?php endif; ?>
<table class="tabelle" id="vereine-liste">
    <thead><tr><th><?= te('Name') ?></th><th><?= te('Kurzname') ?> <span class="hilfe"><?= te('für Baum, Tablet, Statusseite') ?></span></th><th class="rechts"><?= te('Athleten') ?></th><th class="rechts"><?= te('Turniere') ?></th><th class="rechts"><?= te('Coach-Konten') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($vereine as $v): $vid = (int) $v['id']; ?>
        <tr data-suche="<?= e(mb_strtolower($v['name'] . ' ' . $v['kurzname'])) ?>">
            <td><a href="<?= e(url("verein/$vid")) ?>"><strong><?= e($v['name']) ?></strong></a>
                <details class="verein-bearbeiten"><summary class="klein"><?= te('umbenennen') ?></summary>
                    <input form="v<?= $vid ?>" name="name" value="<?= e($v['name']) ?>" required maxlength="200" aria-label="<?= te('Name') ?>"></details></td>
            <td><input form="v<?= $vid ?>" name="kurzname" value="<?= e((string) $v['kurzname']) ?>" maxlength="30" aria-label="<?= te('Kurzname') ?>" placeholder="–"></td>
            <td class="rechts"><?= (int) $v['athleten'] ?></td>
            <td class="rechts"><?= (int) $v['turniere'] ?></td>
            <td class="rechts"><?= (int) $v['konten'] ?: '–' ?></td>
            <td class="nowrap">
                <?php if (!$nurLesen): ?>
                <form method="post" action="<?= e(url('vereine')) ?>" id="v<?= $vid ?>" class="inline">
                    <?= csrf_feld() ?>
                    <input type="hidden" name="id" value="<?= $vid ?>">
                    <button class="knopf klein" type="submit"><?= te('Speichern') ?></button>
                </form>
                <?php if ((int) $v['athleten'] === 0 && (int) $v['konten'] === 0): ?>
                    <form method="post" action="<?= e(url("verein/$vid/loeschen")) ?>" class="inline" data-bestaetigen="<?= te('Verein „{name}“ löschen?', ['name' => $v['name']]) ?>">
                        <?= csrf_feld() ?>
                        <button class="knopf klein gefahr" type="submit"><?= te('Löschen') ?></button>
                    </form>
                <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$vereine): ?>
        <tr><td colspan="6" class="leise"><?= te('Noch keine Vereine – sie entstehen mit der ersten Online-Anmeldung, dem Import oder einem erfassten Teilnehmer.') ?></td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php if (count($vereine) > 1 && !$nurLesen): ?>
    <form method="get" action="<?= e(url('vereine/zusammenfuehren')) ?>" class="karte formular" style="max-width:640px">
        <h2><?= te('Zwei Vereine zusammenführen') ?></h2>
        <p class="hilfe"><?= te('Alles vom ersten Verein (Athleten, Teams, Anmeldungen, Coach-Konten) wandert zum zweiten; der erste wird gelöscht. Vorher kommt eine Übersicht.') ?></p>
        <div class="zeile">
            <label><?= te('Von (doppelt)') ?>
                <select name="von"><?php foreach ($vereine as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['name']) ?></option><?php endforeach; ?></select></label>
            <label><?= te('Nach (bleibt)') ?>
                <select name="nach"><?php foreach ($vereine as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['name']) ?></option><?php endforeach; ?></select></label>
        </div>
        <div><button class="knopf" type="submit"><?= te('Weiter zur Übersicht …') ?></button></div>
    </form>
<?php endif; ?>
