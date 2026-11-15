<?php
/**
 * Übertragungsstand Hallen-Laptop → Online (Schritt 9e), kurz für Hinweisband und Halle-Seite.
 * @var array{revision: int, bestaetigt: int|null, kontakt: string|null, versuch: string|null, fehler: string|null, laeuft: bool} $stand
 */
$uhr = static fn(?string $z) => $z ? date('H:i', (int) strtotime($z)) : null;
$offen = $stand['bestaetigt'] !== $stand['revision'];
?>
<span class="sync-stand">
    <?= te('Übertragung an Online:') ?>
    <?php if (!$offen): ?>
        <span class="tag ok"><?= te('alles übertragen') ?></span><?= $stand['kontakt'] ? ' ' . te('(zuletzt {zeit} Uhr)', ['zeit' => $uhr($stand['kontakt'])]) : '' ?>
    <?php elseif (!$stand['laeuft']): ?>
        <span class="tag fehler"><?= te('läuft nicht') ?></span> – <?= te('ist das Fenster „Wettkampf Sync“ geöffnet? (startet mit start-lokal.bat)') ?>
    <?php elseif ($stand['fehler']): ?>
        <span class="tag warn"><?= te('ausstehend') ?></span> <?= e(t($stand['fehler'])) ?><?= $stand['kontakt'] ? ' · ' . te('letzter Kontakt {zeit} Uhr', ['zeit' => $uhr($stand['kontakt'])]) : '' ?>
        – <?= te('die Halle arbeitet normal weiter und sendet, sobald Online erreichbar ist.') ?>
    <?php else: ?>
        <span class="tag warn"><?= te('wird gesendet') ?></span><?= $stand['kontakt'] ? ' · ' . te('letzter Kontakt {zeit} Uhr', ['zeit' => $uhr($stand['kontakt'])]) : '' ?>
    <?php endif; ?>
</span>
