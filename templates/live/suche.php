<?php
/**
 * @var array<string, mixed> $turnier
 * @var string $q
 * @var list<array<string, mixed>> $treffer
 */
$this->layout('live/layout', ['turnier' => $turnier, 'aktiv' => 'suche', 'automatisch' => false]);
$tid = $turnier['id'];
?>
<h1>Starter oder Verein suchen</h1>
<form method="get" class="knopfleiste karte">
    <input name="q" value="<?= e($q) ?>" placeholder="Name oder Verein (mind. 2 Zeichen)" autofocus style="max-width:420px">
    <button class="knopf primaer" type="submit">Suchen</button>
</form>
<?php if ($q !== '' && !$treffer): ?><p class="leise">Keine Treffer.</p><?php endif; ?>
<?php if ($treffer): ?>
    <table class="tabelle">
        <thead><tr><th>Name</th><th>Verein</th><th>Klasse</th><th>Nächster Kampf</th></tr></thead>
        <tbody>
        <?php foreach ($treffer as $t): ?>
            <tr>
                <td><strong><?= e($t['name']) ?></strong></td>
                <td><?= e($t['verein']) ?></td>
                <td><a href="<?= e(url("live/$tid/klasse/{$t['klasse_id']}")) ?>"><?= e($t['code']) ?></a> <span class="klein"><?= e($t['bezeichnung']) ?></span></td>
                <td><?= $t['naechster'] ? ($t['naechster']['status'] === 'laeuft' ? '<span class="tag warn">läuft jetzt</span> ' : '') . e(($t['naechster']['tatami'] ?? 'Tatami offen') . ' · ' . $t['naechster']['phase']) : '<span class="leise">–</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
