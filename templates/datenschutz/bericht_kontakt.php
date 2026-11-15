<?php
/**
 * Auskunft nach Art. 15 DSGVO zu einer E-Mail-Adresse (Auskunft::kontakt), zum Drucken.
 * @var array<string, mixed> $bericht
 */
use Wettkampf\Meldung\AnmeldungService;

echo $this->teil('datenschutz/bericht_kopf', [
    'titel' => t('Auskunft · {email}', ['email' => $bericht['email']]),
    'json' => url('datenschutz/auskunft/kontakt', ['email' => $bericht['email'], 'format' => 'json']),
]);
$liste = static fn(array $zeilen, callable $text): string => implode('; ', array_map($text, $zeilen)) ?: '–';
?>
<div class="blatt">
    <h1><?= te('Auskunft nach Art. 15 DSGVO') ?></h1>
    <p><?= th('Gespeicherte Angaben zur E-Mail-Adresse <strong>{email}</strong> in „Taikai – Karate“, Stand {zeit}.', [
        'email' => $bericht['email'],
        'zeit' => datum_de(substr((string) $bericht['erstellt_am'], 0, 10)) . ' ' . substr((string) $bericht['erstellt_am'], 11, 5),
    ]) ?></p>

    <h2><?= te('Konten ({anzahl})', ['anzahl' => count($bericht['konten'])]) ?></h2>
    <?php foreach ($bericht['konten'] as $k): ?>
        <table class="angaben">
            <tr><th><?= te('Name') ?></th><td><?= e($k['name']) ?></td></tr>
            <tr><th><?= te('Anmeldename') ?></th><td><?= e((string) $k['login']) ?></td></tr>
            <tr><th><?= te('E-Mail / Telefon') ?></th><td><?= e((string) $k['email']) ?> · <?= e((string) ($k['telefon'] ?: '–')) ?></td></tr>
            <tr><th><?= te('Dojo') ?></th><td><?= e((string) ($k['dojo'] ?? '–')) ?></td></tr>
            <tr><th><?= te('Angelegt / gilt bis') ?></th><td><?= e((string) $k['angelegt_am']) ?> · <?= e((string) ($k['gilt_bis'] ?? t('unbegrenzt'))) ?></td></tr>
            <tr><th><?= te('Authenticator-App') ?></th><td><?= $k['authenticator_app'] ? te('eingerichtet') : te('nein') ?> · <?= te('Passwort nur als Hash gespeichert') ?></td></tr>
            <tr><th><?= te('Vertraute Geräte') ?></th><td><?= e($liste($k['vertraute_geraete'], static fn(array $g): string => t('{geraet} (bis {ablauf})', ['geraet' => $g['geraet'], 'ablauf' => $g['ablauf']]))) ?></td></tr>
            <tr><th><?= te('Turnierrollen') ?></th><td><?= e($liste($k['turnierrollen'], static fn(array $r): string => $r['turnier'] . ': ' . $r['rolle'])) ?></td></tr>
        </table>
    <?php endforeach; ?>
    <?php if (!$bericht['konten']): ?><p><?= te('Kein Konto mit dieser Adresse.') ?></p><?php endif; ?>

    <h2><?= te('Online-Anmeldungen ({anzahl})', ['anzahl' => count($bericht['anmeldungen'])]) ?></h2>
    <?php if ($bericht['anmeldungen']): ?>
        <table>
            <thead><tr><th><?= te('Turnier') ?></th><th><?= te('Dojo') ?></th><th><?= te('Ansprechpartner / Telefon') ?></th><th><?= te('Eingereicht') ?></th><th><?= te('Status') ?></th><th class="rechts"><?= te('Betrag') ?></th><th><?= te('Bezahlt') ?></th><th><?= te('Datenschutz bestätigt') ?></th></tr></thead>
            <tbody><?php foreach ($bericht['anmeldungen'] as $a): ?>
                <tr><td><?= e($a['turnier']) ?> (<?= e(datum_de($a['datum'])) ?>)</td><td><?= e($a['dojo']) ?></td>
                    <td><?= e($a['ansprechpartner']) ?><?= $a['telefon'] ? ' · ' . e($a['telefon']) : '' ?><?= $a['bemerkung'] ? '<br><span class="klein">' . e($a['bemerkung']) . '</span>' : '' ?></td>
                    <td><?= e($a['eingereicht_am']) ?></td><td><?= e($a['status']) ?></td><td class="rechts"><?= e(AnmeldungService::euro($a['betrag_cent'])) ?></td>
                    <td><?= $a['bezahlt_cent'] ? te('{betrag} am {datum}', ['betrag' => AnmeldungService::euro($a['bezahlt_cent']), 'datum' => datum_de($a['bezahlt_am'])]) : '–' ?></td>
                    <td class="klein"><?= is_array($a['datenschutz_bestaetigt']) ? e((string) ($a['datenschutz_bestaetigt']['am'] ?? '')) : '–' ?></td></tr>
            <?php endforeach; ?></tbody>
        </table>
        <p class="klein"><?= te('Je Anmeldung zusätzlich gespeichert: die gemeldeten Starter (deren Angaben gehören den Startern und stehen hier nicht).') ?></p>
    <?php endif; ?>

    <h2><?= te('Einwilligung in Einladungen') ?></h2>
    <?php if ($bericht['einladung']): $ei = $bericht['einladung']; ?>
        <p><?= te('Eingewilligt am {am} ({herkunft}, Textfassung {fassung}) als {name}, {dojo}; zuletzt eingeladen: {zuletzt}. Widerruf jederzeit – die Adresse wird dann gelöscht.', [
            'am' => $ei['eingewilligt_am'], 'herkunft' => $ei['herkunft'], 'fassung' => $ei['fassung'], 'name' => $ei['name'], 'dojo' => $ei['dojo'],
            'zuletzt' => (string) ($ei['zuletzt_eingeladen_am'] ?? '–'),
        ]) ?></p>
    <?php else: ?><p><?= te('Keine.') ?></p><?php endif; ?>

    <h2><?= te('Versendete E-Mails ({anzahl})', ['anzahl' => count($bericht['mails'])]) ?></h2>
    <?php if ($bericht['mails']): ?>
        <table>
            <thead><tr><th><?= te('Betreff') ?></th><th><?= te('Anlass') ?></th><th><?= te('Erstellt') ?></th><th><?= te('Gesendet') ?></th></tr></thead>
            <tbody><?php foreach ($bericht['mails'] as $m): ?>
                <tr><td><?= e($m['betreff']) ?></td><td><?= e($m['anlass']) ?></td><td><?= e($m['erstellt_am']) ?></td><td><?= e((string) ($m['gesendet_am'] ?? t('noch nicht'))) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table>
    <?php endif; ?>
</div>
<?= $this->teil('datenschutz/bericht_hinweise', ['turniere' => $bericht['turniere']]) ?>
</body>
</html>
