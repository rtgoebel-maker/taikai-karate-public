<?php
/**
 * Auskunft nach Art. 15 DSGVO zu einem Starter (Auskunft::starter), zum Drucken; Löschung nach Art. 17 am Ende.
 * @var array<string, mixed> $bericht
 * @var list<int> $ids
 */
use Wettkampf\Kern\Http\Session;

$erste = $bericht['personen'][0]['stammdaten'];
$ja = static fn(mixed $w) => $w ? t('ja') : t('nein');
$zeit = static fn(string $z): string => datum_de(date('Y-m-d', (int) strtotime($z))) . date(' H:i', (int) strtotime($z));
echo $this->teil('datenschutz/bericht_kopf', [
    'titel' => t('Auskunft · {name}', ['name' => $erste['vorname'] . ' ' . $erste['nachname']]),
    'json' => url('datenschutz/auskunft/starter', ['format' => 'json']) . '&' . http_build_query(['ids' => $ids]),
]);
?>
<div class="blatt">
    <h1><?= te('Auskunft nach Art. 15 DSGVO') ?></h1>
    <p><?= th('Gespeicherte Angaben zu <strong>{name}</strong> in „Taikai – Karate“, Stand {zeit}. Angaben über andere Personen (z. B. Gegner, meldende Personen) sind nicht enthalten.', [
        'name' => $erste['vorname'] . ' ' . $erste['nachname'], 'zeit' => $zeit((string) $bericht['erstellt_am']),
    ]) ?></p>
    <?php foreach ($bericht['personen'] as $i => $p): $s = $p['stammdaten']; ?>
        <h2><?= te('Datensatz {nr}: Stammdaten', ['nr' => $i + 1]) ?></h2>
        <table class="angaben">
            <tr><th><?= te('Vorname') ?></th><td><?= e($s['vorname']) ?></td></tr>
            <tr><th><?= te('Nachname') ?></th><td><?= e($s['nachname']) ?></td></tr>
            <tr><th><?= te('Geburtsdatum') ?></th><td><?= e(datum_de($s['geburtsdatum'])) ?></td></tr>
            <tr><th><?= te('Geschlecht') ?></th><td><?= e(geschlecht_text((string) $s['geschlecht'])) ?></td></tr>
            <tr><th><?= te('Graduierung') ?></th><td><?= e($s['graduierung']) ?></td></tr>
            <tr><th><?= te('Verein/Dojo') ?></th><td><?= e((string) $s['verein']) ?></td></tr>
            <tr><th><?= te('Ausweisnummer') ?></th><td><?= e((string) ($s['ausweis_nr'] ?? '')) ?: '–' ?></td></tr>
        </table>
        <?php foreach ($p['teilnahmen'] as $t): ?>
            <h3><?= te('Turnier: {turnier} ({datum})', ['turnier' => $t['turnier'], 'datum' => datum_de($t['datum'])]) ?></h3>
            <p class="klein"><?= te('Status: {status} · Startnummer: {nummer} · Disziplinen: {disziplinen} · Ausweis geprüft: {ausweis} · Nachmeldung: {nachmeldung}', [
                'status' => status_text((string) $t['status']), 'nummer' => (string) ($t['startnummer'] ?? '–'), 'disziplinen' => (string) ($t['disziplinen'] ?? '–'),
                'ausweis' => $ja($t['ausweis_geprueft']), 'nachmeldung' => $ja($t['nachmeldung']),
            ]) ?><?= $t['vermerk'] ? ' · ' . te('Vermerk: {vermerk}', ['vermerk' => $t['vermerk']]) : '' ?></p>
            <?php foreach ($t['anmeldungen'] as $an): ?>
                <p class="klein"><?= te('Herkunft: {quelle}, eingereicht am {zeit} – gemeldet: {angaben}', [
                    'quelle' => $an['quelle'], 'zeit' => $zeit((string) $an['eingereicht_am']),
                    'angaben' => implode(', ', array_map(static fn($k, $v) => $k . ': ' . (is_bool($v) ? $ja($v) : $v), array_keys($an['angaben']), $an['angaben'])),
                ]) ?></p>
            <?php endforeach; ?>
            <?php foreach ($t['starts'] as $st): ?>
                <p><strong><?= e($st['klasse']) ?></strong><?= $st['platz'] !== null ? ' · ' . te('Platz {platz}', ['platz' => (int) $st['platz']]) : '' ?></p>
                <?php if ($st['begegnungen']): ?>
                    <table>
                        <thead><tr><th><?= te('Runde') ?></th><th><?= te('Seite') ?></th><th><?= te('Ergebnis') ?></th><th><?= te('Entscheidung') ?></th><th><?= te('Kata') ?></th><th><?= te('Wertung') ?></th></tr></thead>
                        <tbody><?php foreach ($st['begegnungen'] as $b): ?>
                            <tr><td><?= e(trim($b['phase'] . ' ' . $b['runde'])) ?></td><td><?= e($b['seite']) ?></td><td><?= e($b['ergebnis']) ?></td>
                                <td><?= e((string) $b['entscheidung']) ?></td><td><?= e((string) $b['kata']) ?></td><td class="klein"><?= e((string) $b['wertung']) ?></td></tr>
                        <?php endforeach; ?></tbody>
                    </table>
                <?php endif; ?>
                <?php if ($st['auftritte']): ?>
                    <table>
                        <thead><tr><th><?= te('Runde') ?></th><th><?= te('Kata') ?></th><th><?= te('Gesamt') ?></th><th><?= te('Wertungen') ?></th></tr></thead>
                        <tbody><?php foreach ($st['auftritte'] as $au): ?>
                            <tr><td><?= e((string) $au['phase']) ?></td><td><?= e((string) $au['kata']) ?></td><td><?= e((string) $au['gesamt']) ?></td><td class="klein"><?= e((string) $au['wertung']) ?></td></tr>
                        <?php endforeach; ?></tbody>
                    </table>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if ($p['verlauf']): ?>
            <h3><?= te('Änderungsverlauf') ?></h3>
            <table>
                <thead><tr><th><?= te('Zeit') ?></th><th><?= te('Aktion') ?></th><th><?= te('Angaben') ?></th></tr></thead>
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
    <strong><?= te('Löschantrag (Art. 17):') ?></strong>
    <span><?= te('Name, Geburtsdatum und Ausweisnummer werden in allen Turnieren entfernt; Ergebnisse bleiben als „Gelöscht N“. Zur Bestätigung LÖSCHEN eingeben:') ?></span>
    <input name="bestaetigung" autocomplete="off" required>
    <button type="submit"><?= te('Person löschen') ?></button>
</form>
</body>
</html>
