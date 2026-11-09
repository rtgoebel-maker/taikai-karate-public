<?php
/**
 * @var list<array<string, mixed>> $vereine
 * @var list<array{a: array<string, mixed>, b: array<string, mixed>}> $doppelt vermutlich doppelte Vereine
 * @var int $leer Vereine ohne Athleten, Teams und Anmeldungen
 */
$this->layout('layout', ['titel' => 'Vereine']);
?>
<div class="seitenkopf">
    <h1>Vereine / Dojos <span class="leise klein">(<?= count($vereine) ?>)</span> <?= info('vereine') ?></h1>
    <a class="knopf" href="#neuer-verein" data-aufklappen="neuer-verein">+ Neuer Verein</a>
</div>
<p class="leise">Eine gemeinsame Liste für alle Turniere. Vereine entstehen <strong>automatisch</strong> – bei der Freigabe einer Online-Anmeldung, beim Import oder beim Erfassen
    eines Teilnehmers. Hier korrigieren Sie Schreibweisen, hinterlegen einen <strong>Kurznamen</strong> (Turnierbaum, Tablet, Statusseite) und führen
    <strong>doppelte Vereine</strong> zusammen. Ein Klick auf den Namen zeigt Teilnehmer, ihre Turniere und die Coach-Konten.</p>

<?php if ($doppelt): ?>
    <section class="karte karte-hinweis">
        <h2>Vermutlich doppelt (<?= count($doppelt) ?>)</h2>
        <p class="hilfe">Ähnliche Namen – meist dasselbe Dojo in zwei Schreibweisen. Vor dem Zusammenführen sehen Sie, was umzieht.</p>
        <ul class="doppelt-liste">
            <?php foreach ($doppelt as $d): ?>
                <li>„<?= e($d['a']['name']) ?>“ (<?= (int) $d['a']['athleten'] ?> Athleten) und „<?= e($d['b']['name']) ?>“ (<?= (int) $d['b']['athleten'] ?>)
                    <a class="knopf klein" href="<?= e(url('vereine/zusammenfuehren', ['von' => $d['a']['id'], 'nach' => $d['b']['id']])) ?>">Zusammenführen …</a></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($leer > 0): ?>
    <form method="post" action="<?= e(url('vereine/leere-loeschen')) ?>" class="karte knopfleiste" data-bestaetigen="<?= $leer ?> Vereine ohne Athleten, Teams und Anmeldungen löschen?">
        <?= csrf_feld() ?>
        <span><?= $leer === 1 ? 'Ein Verein wird' : $leer . ' Vereine werden' ?> von keinem Turnier genutzt (keine Athleten, Teams, Anmeldungen oder Coach-Konten).</span>
        <button class="knopf klein gefahr" type="submit">Leere Vereine löschen</button>
    </form>
<?php endif; ?>

<details class="karte neues-konto" id="neuer-verein">
    <summary><h2>Neuer Verein</h2></summary>
    <form method="post" action="<?= e(url('vereine')) ?>" class="formular" style="max-width:640px">
        <?= csrf_feld() ?>
        <p class="hilfe">Nur nötig, um ein Coach-Konto für ein Dojo anzulegen, das noch nie gemeldet hat – sonst entsteht der Verein mit der ersten Meldung.</p>
        <div class="zeile">
            <label>Name <input name="name" required maxlength="200"></label>
            <label>Kurzname <span class="hilfe">optional, max. 30 Zeichen</span> <input name="kurzname" maxlength="30"></label>
        </div>
        <div><button class="knopf primaer" type="submit">Anlegen</button></div>
    </form>
</details>

<?php if (count($vereine) > 8): ?>
    <label class="suche-konten">Suchen <input type="search" data-tabellenfilter="vereine-liste" placeholder="Name oder Kurzname" autocomplete="off"></label>
<?php endif; ?>
<table class="tabelle" id="vereine-liste">
    <thead><tr><th>Name</th><th>Kurzname <span class="hilfe">für Baum, Tablet, Statusseite</span></th><th class="rechts">Athleten</th><th class="rechts">Turniere</th><th class="rechts">Coach-Konten</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($vereine as $v): $vid = (int) $v['id']; ?>
        <tr data-suche="<?= e(mb_strtolower($v['name'] . ' ' . $v['kurzname'])) ?>">
            <td><a href="<?= e(url("verein/$vid")) ?>"><strong><?= e($v['name']) ?></strong></a>
                <details class="verein-bearbeiten"><summary class="klein">umbenennen</summary>
                    <input form="v<?= $vid ?>" name="name" value="<?= e($v['name']) ?>" required maxlength="200" aria-label="Name"></details></td>
            <td><input form="v<?= $vid ?>" name="kurzname" value="<?= e((string) $v['kurzname']) ?>" maxlength="30" aria-label="Kurzname" placeholder="–"></td>
            <td class="rechts"><?= (int) $v['athleten'] ?></td>
            <td class="rechts"><?= (int) $v['turniere'] ?></td>
            <td class="rechts"><?= (int) $v['konten'] ?: '–' ?></td>
            <td class="nowrap">
                <form method="post" action="<?= e(url('vereine')) ?>" id="v<?= $vid ?>" class="inline">
                    <?= csrf_feld() ?>
                    <input type="hidden" name="id" value="<?= $vid ?>">
                    <button class="knopf klein" type="submit">Speichern</button>
                </form>
                <?php if ((int) $v['athleten'] === 0 && (int) $v['konten'] === 0): ?>
                    <form method="post" action="<?= e(url("verein/$vid/loeschen")) ?>" class="inline" data-bestaetigen="Verein „<?= e($v['name']) ?>“ löschen?">
                        <?= csrf_feld() ?>
                        <button class="knopf klein gefahr" type="submit">Löschen</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$vereine): ?>
        <tr><td colspan="6" class="leise">Noch keine Vereine – sie entstehen mit der ersten Online-Anmeldung, dem Import oder einem erfassten Teilnehmer.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php if (count($vereine) > 1): ?>
    <form method="get" action="<?= e(url('vereine/zusammenfuehren')) ?>" class="karte formular" style="max-width:640px">
        <h2>Zwei Vereine zusammenführen</h2>
        <p class="hilfe">Alles vom ersten Verein (Athleten, Teams, Anmeldungen, Coach-Konten) wandert zum zweiten; der erste wird gelöscht. Vorher kommt eine Übersicht.</p>
        <div class="zeile">
            <label>Von (doppelt)
                <select name="von"><?php foreach ($vereine as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['name']) ?></option><?php endforeach; ?></select></label>
            <label>Nach (bleibt)
                <select name="nach"><?php foreach ($vereine as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['name']) ?></option><?php endforeach; ?></select></label>
        </div>
        <div><button class="knopf" type="submit">Weiter zur Übersicht …</button></div>
    </form>
<?php endif; ?>
