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
    <h2>⚠ <?= te('Nicht erschienen') ?> <span class="tag fehler"><?= count($meldungen) ?></span> <?= info('nicht-erschienen') ?></h2>
    <p class="hilfe"><?= te('Rückmeldungen der Ausrufer. Ein Starter kann gerade auf einer anderen Tatami sein – deshalb entscheidet die Leitung.') ?></p>
    <div class="tabelle-scroll">
    <table class="tabelle">
        <thead><tr><th><?= te('Starter') ?></th><th><?= te('Wo') ?></th><th><?= te('Weitere Klassen') ?></th><th><?= te('Entscheidung') ?></th></tr></thead>
        <tbody>
        <?php foreach ($meldungen as $m): $wer = $m['name'] . ' (' . $m['klasse'] . ')'; ?>
            <tr>
                <td><strong><?= e($m['name']) ?></strong><br><span class="klein"><?= e($m['verein']) ?><?= $m['team'] ? ' · ' . te('Team') : '' ?></span></td>
                <td><a href="<?= e(url("turnier/$tid/klasse/{$m['klasse_id']}")) ?>"><?= e($m['klasse']) ?></a> · <?= e($m['wo']) ?>
                    <?= $m['seite'] !== 'starter' ? '· ' . strtoupper($m['seite']) : '' ?><br>
                    <span class="klein"><?= e($m['tatami'] ?? '') ?> · <?= te('Ausruf {zeit} Uhr', ['zeit' => $m['zeit']]) ?></span></td>
                <td><?= $m['weitere'] ? e(implode(', ', $m['weitere'])) : '<span class="leise">–</span>' ?></td>
                <td class="knopfleiste">
                    <?= $knopf($m, 'da', t('Ist doch da')) ?>
                    <?= $knopf($m, 'kiken', t('Kiken nur hier'), '', $m['typ'] === 'auftritt'
                        ? t('{wer}: nur in diesem Vortrag kampflos (Kiken) werten?', ['wer' => $wer])
                        : t('{wer}: nur in diesem Kampf kampflos (Kiken) werten?', ['wer' => $wer])) ?>
                    <?= $knopf($m, 'zurueckziehen', $m['team'] ? t('Team zurückziehen') : t('Aus allen Wettkämpfen'), 'gefahr',
                        $m['team'] ? t('Team {wer} zurückziehen? Alle offenen Kämpfe des Teams werden kampflos (Kiken) gewertet.', ['wer' => $wer])
                            : ($m['weitere']
                                ? t('{wer} als nicht erschienen kennzeichnen? Alle offenen Kämpfe (auch {weitere}) werden kampflos (Kiken) gewertet.', ['wer' => $wer, 'weitere' => implode(', ', $m['weitere'])])
                                : t('{wer} als nicht erschienen kennzeichnen? Alle offenen Kämpfe werden kampflos (Kiken) gewertet.', ['wer' => $wer]))) ?>
                    <?php if ($m['neu_auslosbar']): ?>
                        <?= $knopf($m, 'neu_auslosen', t('… und {klasse} neu auslosen', ['klasse' => $m['klasse']]), 'gefahr',
                            t('{wer} aus allen Wettkämpfen nehmen und Klasse {klasse} ohne den Starter neu auslosen? (Noch kein Ergebnis in der Klasse – die Paarungen ändern sich.)', ['wer' => $wer, 'klasse' => $m['klasse']])) ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
