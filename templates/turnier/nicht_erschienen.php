<?php
/**
 * Rückmeldungen des Ausrufs: Starter, die nicht erschienen sind – Entscheidung der Leitung.
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $meldungen
 */
$tid = $turnier['id'];
$knopf = static function (array $m, string $aktion, string $text, string $art = '', string $frage = '') use ($tid): string {
    return '<form method="post" action="' . e(url("turnier/$tid/nicht-erschienen")) . '" class="inline"' . ($frage !== '' ? ' data-bestaetigen="' . e($frage) . '"' : '') . '>'
        . csrf_feld()
        . '<input type="hidden" name="typ" value="' . e($m['typ']) . '"><input type="hidden" name="eintrag" value="' . (int) $m['id'] . '">'
        . '<input type="hidden" name="seite" value="' . e($m['seite']) . '"><input type="hidden" name="aktion" value="' . e($aktion) . '">'
        . '<button class="knopf klein ' . $art . '" type="submit">' . e($text) . '</button></form>';
};
?>
<section class="karte nicht-erschienen" id="nicht-erschienen">
    <h2>⚠ Nicht erschienen <span class="tag fehler"><?= count($meldungen) ?></span> <?= info('nicht-erschienen') ?></h2>
    <p class="hilfe">Rückmeldungen der Ausrufer. Ein Starter kann gerade auf einer anderen Tatami sein – deshalb entscheidet die Leitung.</p>
    <div class="tabelle-scroll">
    <table class="tabelle">
        <thead><tr><th>Starter</th><th>Wo</th><th>Weitere Klassen</th><th>Entscheidung</th></tr></thead>
        <tbody>
        <?php foreach ($meldungen as $m): $wer = $m['name'] . ' (' . $m['klasse'] . ')'; ?>
            <tr>
                <td><strong><?= e($m['name']) ?></strong><br><span class="klein"><?= e($m['verein']) ?><?= $m['team'] ? ' · Team' : '' ?></span></td>
                <td><a href="<?= e(url("turnier/$tid/klasse/{$m['klasse_id']}")) ?>"><?= e($m['klasse']) ?></a> · <?= e($m['wo']) ?>
                    <?= $m['seite'] !== 'starter' ? '· ' . strtoupper($m['seite']) : '' ?><br>
                    <span class="klein"><?= e($m['tatami'] ?? '') ?> · Ausruf <?= e($m['zeit']) ?> Uhr</span></td>
                <td><?= $m['weitere'] ? e(implode(', ', $m['weitere'])) : '<span class="leise">–</span>' ?></td>
                <td class="knopfleiste">
                    <?= $knopf($m, 'da', 'Ist doch da') ?>
                    <?= $knopf($m, 'kiken', 'Kiken nur hier', '', "$wer: nur in diesem " . ($m['typ'] === 'auftritt' ? 'Vortrag' : 'Kampf') . ' kampflos (Kiken) werten?') ?>
                    <?= $knopf($m, 'zurueckziehen', $m['team'] ? 'Team zurückziehen' : 'Aus allen Wettkämpfen', 'gefahr',
                        $m['team'] ? "Team $wer zurückziehen? Alle offenen Kämpfe des Teams werden kampflos (Kiken) gewertet."
                            : "$wer als nicht erschienen kennzeichnen? Alle offenen Kämpfe" . ($m['weitere'] ? ' (auch ' . implode(', ', $m['weitere']) . ')' : '') . ' werden kampflos (Kiken) gewertet.') ?>
                    <?php if ($m['neu_auslosbar']): ?>
                        <?= $knopf($m, 'neu_auslosen', '… und ' . $m['klasse'] . ' neu auslosen', 'gefahr',
                            "$wer aus allen Wettkämpfen nehmen und Klasse {$m['klasse']} ohne den Starter neu auslosen? (Noch kein Ergebnis in der Klasse – die Paarungen ändern sich.)") ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
