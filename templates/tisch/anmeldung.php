<?php
/**
 * @var list<array<string, mixed>> $tatamis
 * @var string $ansicht app | ausruf
 */
$this->layout('layout_einfach', ['titel' => 'Tisch-Anmeldung']);
?>
<p class="obertitel">Tischbesatzung</p>
<h1>Tatami anmelden</h1>
<div class="meldung meldung-hinweis"><strong>Empfohlen: QR-Code scannen.</strong> Die Wettkampfleitung zeigt je Tatami einen QR-Code für Eingabe und Ausruf.
    Mit der Kamera scannen, Gerät benennen, Freigabe abwarten – fertig. Eine PIN ist dann nicht nötig.</div>
<?php if (!$tatamis): ?>
    <p class="leise">Die Anmeldung per PIN ist für die laufenden Turniere abgeschaltet.</p>
    <p class="klein"><a href="<?= e(url('login')) ?>">Anmeldung Wettkampfleitung</a></p>
<?php return; endif; ?>
<h2>Notlösung: mit PIN</h2>
<form method="post" action="<?= e(url('tisch')) ?>" class="formular">
    <?= csrf_feld() ?>
    <label>Tatami
        <select name="tatami_id" required style="font-size:1.2rem;padding:.7rem">
            <option value="">bitte wählen</option>
            <?php $turnier = null; foreach ($tatamis as $t): ?>
                <?php if ($turnier !== $t['turnier']): ?>
                    <?= $turnier !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($t['turnier'] . ' · ' . datum_de($t['datum'])) ?>">
                    <?php $turnier = $t['turnier']; endif; ?>
                <option value="<?= $t['id'] ?>"<?= $t['pin_hash'] ? '' : ' disabled' ?>><?= e($t['bezeichnung']) ?><?= $t['pin_hash'] ? '' : ' (keine PIN gesetzt)' ?></option>
            <?php endforeach; ?>
            <?= $turnier !== null ? '</optgroup>' : '' ?>
        </select>
    </label>
    <fieldset class="ansicht-wahl">
        <legend>Ansicht</legend>
        <label class="check"><input type="radio" name="ansicht" value="app"<?= $ansicht !== 'ausruf' ? ' checked' : '' ?>> <span><strong>Eingabe</strong> – Ergebnisse am Tablet</span></label>
        <label class="check"><input type="radio" name="ansicht" value="ausruf"<?= $ansicht === 'ausruf' ? ' checked' : '' ?>> <span><strong>Ausruf</strong> – Starter aufrufen (Handy)</span></label>
    </fieldset>
    <label>PIN <input name="pin" type="password" inputmode="numeric" pattern="\d{4,8}" required autocomplete="off" style="font-size:1.6rem;padding:.7rem;letter-spacing:.3em"></label>
    <button class="knopf primaer" type="submit" style="font-size:1.2rem;padding:.9rem">Anmelden</button>
</form>
<p class="klein">Die PIN vergibt die Wettkampfleitung unter Turnier → Einstellungen. <a href="<?= e(url('login')) ?>">Anmeldung Wettkampfleitung</a></p>
