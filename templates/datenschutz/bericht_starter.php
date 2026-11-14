<?php
/**
 * Auskunft nach Art. 15 DSGVO zu einem Starter (Auskunft::starter), zum Drucken; Löschung nach Art. 17 am Ende.
 * @var array<string, mixed> $bericht
 * @var list<int> $ids
 */
use Wettkampf\Kern\Http\Session;

$erste = $bericht['personen'][0]['stammdaten'];
$ja = static fn(mixed $w) => $w ? 'ja' : 'nein';
echo $this->teil('datenschutz/bericht_kopf', [
    'titel' => 'Auskunft · ' . $erste['vorname'] . ' ' . $erste['nachname'],
    'json' => url('datenschutz/auskunft/starter', ['format' => 'json']) . '&' . http_build_query(['ids' => $ids]),
]);
?>
<div class="blatt">
    <h1>Auskunft nach Art. 15 DSGVO</h1>
    <p>Gespeicherte Angaben zu <strong><?= e($erste['vorname'] . ' ' . $erste['nachname']) ?></strong> in „Taikai – Karate“, Stand <?= e(date('d.m.Y H:i', (int) strtotime($bericht['erstellt_am']))) ?>.
        Angaben über andere Personen (z. B. Gegner, meldende Personen) sind nicht enthalten.</p>
    <?php foreach ($bericht['personen'] as $i => $p): $s = $p['stammdaten']; ?>
        <h2>Datensatz <?= $i + 1 ?>: Stammdaten</h2>
        <table class="angaben">
            <tr><th>Vorname</th><td><?= e($s['vorname']) ?></td></tr>
            <tr><th>Nachname</th><td><?= e($s['nachname']) ?></td></tr>
            <tr><th>Geburtsdatum</th><td><?= e(datum_de($s['geburtsdatum'])) ?></td></tr>
            <tr><th>Geschlecht</th><td><?= e(geschlecht_text((string) $s['geschlecht'])) ?></td></tr>
            <tr><th>Graduierung</th><td><?= e($s['graduierung']) ?></td></tr>
            <tr><th>Verein/Dojo</th><td><?= e((string) $s['verein']) ?></td></tr>
            <tr><th>Ausweisnummer</th><td><?= e((string) ($s['ausweis_nr'] ?? '')) ?: '–' ?></td></tr>
        </table>
        <?php foreach ($p['teilnahmen'] as $t): ?>
            <h3>Turnier: <?= e($t['turnier']) ?> (<?= e(datum_de($t['datum'])) ?>)</h3>
            <p class="klein">Status: <?= e(status_text((string) $t['status'])) ?> · Startnummer: <?= e((string) ($t['startnummer'] ?? '–')) ?>
                · Disziplinen: <?= e((string) ($t['disziplinen'] ?? '–')) ?> · Ausweis geprüft: <?= $ja($t['ausweis_geprueft']) ?> · Nachmeldung: <?= $ja($t['nachmeldung']) ?>
                <?= $t['vermerk'] ? ' · Vermerk: ' . e($t['vermerk']) : '' ?></p>
            <?php foreach ($t['anmeldungen'] as $an): ?>
                <p class="klein">Herkunft: <?= e($an['quelle']) ?>, eingereicht am <?= e(date('d.m.Y H:i', (int) strtotime($an['eingereicht_am']))) ?> –
                    gemeldet: <?= e(implode(', ', array_map(static fn($k, $v) => $k . ': ' . (is_bool($v) ? ($v ? 'ja' : 'nein') : $v), array_keys($an['angaben']), $an['angaben']))) ?></p>
            <?php endforeach; ?>
            <?php foreach ($t['starts'] as $st): ?>
                <p><strong><?= e($st['klasse']) ?></strong><?= $st['platz'] !== null ? ' · Platz ' . (int) $st['platz'] : '' ?></p>
                <?php if ($st['begegnungen']): ?>
                    <table>
                        <thead><tr><th>Runde</th><th>Seite</th><th>Ergebnis</th><th>Entscheidung</th><th>Kata</th><th>Wertung</th></tr></thead>
                        <tbody><?php foreach ($st['begegnungen'] as $b): ?>
                            <tr><td><?= e(trim($b['phase'] . ' ' . $b['runde'])) ?></td><td><?= e($b['seite']) ?></td><td><?= e($b['ergebnis']) ?></td>
                                <td><?= e((string) $b['entscheidung']) ?></td><td><?= e((string) $b['kata']) ?></td><td class="klein"><?= e((string) $b['wertung']) ?></td></tr>
                        <?php endforeach; ?></tbody>
                    </table>
                <?php endif; ?>
                <?php if ($st['auftritte']): ?>
                    <table>
                        <thead><tr><th>Runde</th><th>Kata</th><th>Gesamt</th><th>Wertungen</th></tr></thead>
                        <tbody><?php foreach ($st['auftritte'] as $au): ?>
                            <tr><td><?= e((string) $au['phase']) ?></td><td><?= e((string) $au['kata']) ?></td><td><?= e((string) $au['gesamt']) ?></td><td class="klein"><?= e((string) $au['wertung']) ?></td></tr>
                        <?php endforeach; ?></tbody>
                    </table>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if ($p['verlauf']): ?>
            <h3>Änderungsverlauf</h3>
            <table>
                <thead><tr><th>Zeit</th><th>Aktion</th><th>Angaben</th></tr></thead>
                <tbody><?php foreach ($p['verlauf'] as $v): ?>
                    <tr><td><?= e($v['zeit']) ?></td><td><?= e($v['aktion']) ?></td><td class="klein"><?= e((string) $v['daten']) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?= $this->teil('datenschutz/bericht_hinweise', ['turniere' => $bericht['turniere']]) ?>
<form method="post" action="<?= e(url('datenschutz/auskunft/loeschen')) ?>" class="werkzeug" style="flex-wrap:wrap">
    <input type="hidden" name="_csrf" value="<?= e(Session::csrfToken()) ?>">
    <?php foreach ($ids as $id): ?><input type="hidden" name="ids[]" value="<?= (int) $id ?>"><?php endforeach; ?>
    <strong>Löschantrag (Art. 17):</strong>
    <span>Name, Geburtsdatum und Ausweisnummer werden in allen Turnieren entfernt; Ergebnisse bleiben als „Gelöscht N“. Zur Bestätigung LÖSCHEN eingeben:</span>
    <input name="bestaetigung" autocomplete="off" required>
    <button type="submit">Person löschen</button>
</form>
</body>
</html>
