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
$this->layout('layout', ['titel' => t('Vereine zusammenführen')]);
?>
<p class="obertitel"><a href="<?= e(url('vereine')) ?>"><?= te('Vereine') ?></a></p>
<h1><?= te('Vereine zusammenführen') ?></h1>
<form method="post" action="<?= e(url('vereine/zusammenfuehren')) ?>" class="karte formular" style="max-width:720px">
    <?= csrf_feld() ?>
    <input type="hidden" name="von" value="<?= (int) $von['id'] ?>">
    <input type="hidden" name="nach" value="<?= (int) $nach['id'] ?>">
    <p><?= th('Von <strong>„{von}“</strong> nach <strong>„{nach}“</strong> ziehen um:', ['von' => $von['name'], 'nach' => $nach['name']]) ?></p>
    <ul>
        <li><?= th('<strong>{n}</strong> Athleten (mit allen Meldungen in allen Turnieren)', ['n' => $athleten]) ?></li>
        <li><?= th('<strong>{n}</strong> Teams', ['n' => $teams]) ?></li>
        <li><?= th('<strong>{n}</strong> Online-Anmeldungen', ['n' => $anmeldungen]) ?></li>
        <li><?= th('<strong>{n}</strong> Coach-Konten', ['n' => $konten]) ?></li>
    </ul>
    <p><?= th('Danach ist „{name}“ gelöscht. Das lässt sich <strong>nicht rückgängig</strong> machen (im Änderungsverlauf festgehalten).', ['name' => $von['name']]) ?></p>
    <div class="knopfleiste">
        <button class="knopf primaer" type="submit"><?= te('Jetzt zusammenführen') ?></button>
        <a class="knopf" href="<?= e(url('vereine/zusammenfuehren', ['von' => $nach['id'], 'nach' => $von['id']])) ?>"><?= te('Richtung tauschen') ?></a>
        <a href="<?= e(url('vereine')) ?>"><?= te('Abbrechen') ?></a>
    </div>
</form>
