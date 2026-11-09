<?php
/**
 * Übertragungsstand Hallen-Laptop → Online (Schritt 9e), kurz für Hinweisband und Halle-Seite.
 * @var array{revision: int, bestaetigt: int|null, kontakt: string|null, versuch: string|null, fehler: string|null, laeuft: bool} $stand
 */
$uhr = static fn(?string $z) => $z ? date('H:i', (int) strtotime($z)) : null;
$offen = $stand['bestaetigt'] !== $stand['revision'];
?>
<span class="sync-stand">
    Übertragung an Online:
    <?php if (!$offen): ?>
        <span class="tag ok">alles übertragen</span><?= $stand['kontakt'] ? ' (zuletzt ' . e($uhr($stand['kontakt'])) . ' Uhr)' : '' ?>
    <?php elseif (!$stand['laeuft']): ?>
        <span class="tag fehler">läuft nicht</span> – ist das Fenster „Wettkampf Sync“ geöffnet? (startet mit start-lokal.bat)
    <?php elseif ($stand['fehler']): ?>
        <span class="tag warn">ausstehend</span> <?= e($stand['fehler']) ?><?= $stand['kontakt'] ? ' · letzter Kontakt ' . e($uhr($stand['kontakt'])) . ' Uhr' : '' ?>
        – die Halle arbeitet normal weiter und sendet, sobald Online erreichbar ist.
    <?php else: ?>
        <span class="tag warn">wird gesendet</span><?= $stand['kontakt'] ? ' · letzter Kontakt ' . e($uhr($stand['kontakt'])) . ' Uhr' : '' ?>
    <?php endif; ?>
</span>
