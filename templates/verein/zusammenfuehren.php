<?php
/**
 * Vorschau vor dem Zusammenführen zweier Vereine.
 * @var array<string, mixed> $von
 * @var array<string, mixed> $nach
 * @var int $athleten
 * @var int $teams
 * @var int $anmeldungen
 * @var int $konten
 */
$this->layout('layout', ['titel' => 'Vereine zusammenführen']);
?>
<p class="obertitel"><a href="<?= e(url('vereine')) ?>">Vereine</a></p>
<h1>Vereine zusammenführen</h1>
<form method="post" action="<?= e(url('vereine/zusammenfuehren')) ?>" class="karte formular" style="max-width:720px">
    <?= csrf_feld() ?>
    <input type="hidden" name="von" value="<?= (int) $von['id'] ?>">
    <input type="hidden" name="nach" value="<?= (int) $nach['id'] ?>">
    <p>Von <strong>„<?= e($von['name']) ?>“</strong> nach <strong>„<?= e($nach['name']) ?>“</strong> ziehen um:</p>
    <ul>
        <li><strong><?= $athleten ?></strong> Athleten (mit allen Meldungen in allen Turnieren)</li>
        <li><strong><?= $teams ?></strong> Teams</li>
        <li><strong><?= $anmeldungen ?></strong> Online-Anmeldungen</li>
        <li><strong><?= $konten ?></strong> Coach-Konten</li>
    </ul>
    <p>Danach ist „<?= e($von['name']) ?>“ gelöscht. Das lässt sich <strong>nicht rückgängig</strong> machen (im Änderungsverlauf festgehalten).</p>
    <div class="knopfleiste">
        <button class="knopf primaer" type="submit">Jetzt zusammenführen</button>
        <a class="knopf" href="<?= e(url('vereine/zusammenfuehren', ['von' => $nach['id'], 'nach' => $von['id']])) ?>">Richtung tauschen</a>
        <a href="<?= e(url('vereine')) ?>">Abbrechen</a>
    </div>
</form>
