<?php
/**
 * Abmeldung von Einladungen über den Link in der E-Mail (öffentlich). GET zeigt nur die Frage, erst der Knopf meldet ab.
 * @var array<string, mixed>|null $eintrag null = ungültig bzw. schon abgemeldet (oder erledigt)
 * @var bool $erledigt
 * @var string $ziel
 */
$this->layout('layout_einfach', ['titel' => 'Einladungen abbestellen']);
?>
<h1>Einladungen abbestellen</h1>
<?php if ($erledigt): ?>
    <div class="meldung meldung-erfolg">Erledigt – Ihre Adresse ist gelöscht, Sie erhalten keine Einladungen mehr.</div>
<?php elseif ($eintrag === null): ?>
    <p>Dieser Link ist ungültig oder die Adresse ist bereits abgemeldet. Es werden keine Einladungen mehr an sie verschickt.</p>
<?php else: ?>
    <p>Keine Einladungen zu künftigen Turnieren mehr an <strong><?= e($eintrag['email']) ?></strong>? Die Adresse wird dann gelöscht.</p>
    <form method="post" action="<?= e($ziel) ?>">
        <?= csrf_feld() ?>
        <button class="knopf primaer" type="submit">Abbestellen</button>
    </form>
<?php endif; ?>
