<?php
/**
 * Bearbeitungsstand einer Online-Anmeldung als Schrittfolge (Dojo-Übersicht, Leitung).
 * @var array{schritte: list<array{schluessel: string, titel: string, zustand: string, text: string, datum: string|null}>} $stand
 */
$zeichen = ['erledigt' => '✓', 'aktiv' => '●', 'offen' => '○', 'entfaellt' => '–', 'abgelehnt' => '✗'];
?>
<ol class="anmeldestand">
    <?php foreach ($stand['schritte'] as $s): ?>
        <li class="schritt <?= e($s['zustand']) ?>">
            <span class="zeichen" aria-hidden="true"><?= $zeichen[$s['zustand']] ?? '' ?></span>
            <span class="titel"><?= e($s['titel']) ?></span>
            <?php if ($s['datum']): ?><span class="datum"><?= e(strlen($s['datum']) > 10 ? date('d.m.Y H:i', (int) strtotime($s['datum'])) : datum_de($s['datum'])) ?></span><?php endif; ?>
            <?php if ($s['text'] !== ''): ?><span class="text"><?= e($s['text']) ?></span><?php endif; ?>
        </li>
    <?php endforeach; ?>
</ol>
