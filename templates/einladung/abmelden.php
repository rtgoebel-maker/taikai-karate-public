<?php
/**
 * Abmeldung von Einladungen über den Link in der E-Mail (öffentlich). GET zeigt nur die Frage, erst der Knopf meldet ab.
 * @var array<string, mixed>|null $eintrag null = ungültig bzw. schon abgemeldet (oder erledigt)
 * @var bool $erledigt
 * @var string $ziel
 */
$this->layout('layout_einfach', ['titel' => t('Einladungen abbestellen')]);
?>
<h1><?= te('Einladungen abbestellen') ?></h1>
<?php if ($erledigt): ?>
    <div class="meldung meldung-erfolg"><?= te('Erledigt – Ihre Adresse ist gelöscht, Sie erhalten keine Einladungen mehr.') ?></div>
<?php elseif ($eintrag === null): ?>
    <p><?= te('Dieser Link ist ungültig oder die Adresse ist bereits abgemeldet. Es werden keine Einladungen mehr an sie verschickt.') ?></p>
<?php else: ?>
    <p><?= th('Keine Einladungen zu künftigen Turnieren mehr an <strong>{email}</strong>? Die Adresse wird dann gelöscht.', ['email' => $eintrag['email']]) ?></p>
    <form method="post" action="<?= e($ziel) ?>">
        <?= csrf_feld() ?>
        <button class="knopf primaer" type="submit"><?= te('Abbestellen') ?></button>
    </form>
<?php endif; ?>
