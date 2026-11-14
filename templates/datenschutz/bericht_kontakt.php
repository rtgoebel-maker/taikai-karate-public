<?php
/**
 * Auskunft nach Art. 15 DSGVO zu einer E-Mail-Adresse (Auskunft::kontakt), zum Drucken.
 * @var array<string, mixed> $bericht
 */
use Wettkampf\Meldung\AnmeldungService;

echo $this->teil('datenschutz/bericht_kopf', [
    'titel' => 'Auskunft · ' . $bericht['email'],
    'json' => url('datenschutz/auskunft/kontakt', ['email' => $bericht['email'], 'format' => 'json']),
]);
$liste = static fn(array $zeilen, callable $text): string => implode('; ', array_map($text, $zeilen)) ?: '–';
?>
<div class="blatt">
    <h1>Auskunft nach Art. 15 DSGVO</h1>
    <p>Gespeicherte Angaben zur E-Mail-Adresse <strong><?= e($bericht['email']) ?></strong> in „Taikai – Karate“, Stand <?= e(date('d.m.Y H:i', (int) strtotime($bericht['erstellt_am']))) ?>.</p>

    <h2>Konten (<?= count($bericht['konten']) ?>)</h2>
    <?php foreach ($bericht['konten'] as $k): ?>
        <table class="angaben">
            <tr><th>Name</th><td><?= e($k['name']) ?></td></tr>
            <tr><th>Anmeldename</th><td><?= e((string) $k['login']) ?></td></tr>
            <tr><th>E-Mail / Telefon</th><td><?= e((string) $k['email']) ?> · <?= e((string) ($k['telefon'] ?: '–')) ?></td></tr>
            <tr><th>Dojo</th><td><?= e((string) ($k['dojo'] ?? '–')) ?></td></tr>
            <tr><th>Angelegt / gilt bis</th><td><?= e((string) $k['angelegt_am']) ?> · <?= e((string) ($k['gilt_bis'] ?? 'unbegrenzt')) ?></td></tr>
            <tr><th>Authenticator-App</th><td><?= $k['authenticator_app'] ? 'eingerichtet' : 'nein' ?> · Passwort nur als Hash gespeichert</td></tr>
            <tr><th>Vertraute Geräte</th><td><?= e($liste($k['vertraute_geraete'], static fn(array $g): string => $g['geraet'] . ' (bis ' . $g['ablauf'] . ')')) ?></td></tr>
            <tr><th>Turnierrollen</th><td><?= e($liste($k['turnierrollen'], static fn(array $r): string => $r['turnier'] . ': ' . $r['rolle'])) ?></td></tr>
        </table>
    <?php endforeach; ?>
    <?php if (!$bericht['konten']): ?><p>Kein Konto mit dieser Adresse.</p><?php endif; ?>

    <h2>Online-Anmeldungen (<?= count($bericht['anmeldungen']) ?>)</h2>
    <?php if ($bericht['anmeldungen']): ?>
        <table>
            <thead><tr><th>Turnier</th><th>Dojo</th><th>Ansprechpartner / Telefon</th><th>Eingereicht</th><th>Status</th><th class="rechts">Betrag</th><th>Bezahlt</th><th>Datenschutz bestätigt</th></tr></thead>
            <tbody><?php foreach ($bericht['anmeldungen'] as $a): ?>
                <tr><td><?= e($a['turnier']) ?> (<?= e(datum_de($a['datum'])) ?>)</td><td><?= e($a['dojo']) ?></td>
                    <td><?= e($a['ansprechpartner']) ?><?= $a['telefon'] ? ' · ' . e($a['telefon']) : '' ?><?= $a['bemerkung'] ? '<br><span class="klein">' . e($a['bemerkung']) . '</span>' : '' ?></td>
                    <td><?= e($a['eingereicht_am']) ?></td><td><?= e($a['status']) ?></td><td class="rechts"><?= e(AnmeldungService::euro($a['betrag_cent'])) ?></td>
                    <td><?= $a['bezahlt_cent'] ? e(AnmeldungService::euro($a['bezahlt_cent'])) . ' am ' . e(datum_de($a['bezahlt_am'])) : '–' ?></td>
                    <td class="klein"><?= is_array($a['datenschutz_bestaetigt']) ? e((string) ($a['datenschutz_bestaetigt']['am'] ?? '')) : '–' ?></td></tr>
            <?php endforeach; ?></tbody>
        </table>
        <p class="klein">Je Anmeldung zusätzlich gespeichert: die gemeldeten Starter (deren Angaben gehören den Startern und stehen hier nicht).</p>
    <?php endif; ?>

    <h2>Einwilligung in Einladungen</h2>
    <?php if ($bericht['einladung']): $ei = $bericht['einladung']; ?>
        <p>Eingewilligt am <?= e($ei['eingewilligt_am']) ?> (<?= e($ei['herkunft']) ?>, Textfassung <?= e($ei['fassung']) ?>) als <?= e($ei['name']) ?>, <?= e($ei['dojo']) ?>;
            zuletzt eingeladen: <?= e((string) ($ei['zuletzt_eingeladen_am'] ?? '–')) ?>. Widerruf jederzeit – die Adresse wird dann gelöscht.</p>
    <?php else: ?><p>Keine.</p><?php endif; ?>

    <h2>Versendete E-Mails (<?= count($bericht['mails']) ?>)</h2>
    <?php if ($bericht['mails']): ?>
        <table>
            <thead><tr><th>Betreff</th><th>Anlass</th><th>Erstellt</th><th>Gesendet</th></tr></thead>
            <tbody><?php foreach ($bericht['mails'] as $m): ?>
                <tr><td><?= e($m['betreff']) ?></td><td><?= e($m['anlass']) ?></td><td><?= e($m['erstellt_am']) ?></td><td><?= e((string) ($m['gesendet_am'] ?? 'noch nicht')) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table>
    <?php endif; ?>
</div>
<?= $this->teil('datenschutz/bericht_hinweise', ['turniere' => $bericht['turniere']]) ?>
</body>
</html>
