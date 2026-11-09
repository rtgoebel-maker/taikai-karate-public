<?php
/**
 * Anzeige einer Tatami (Statusseite und Vollbild an der Matte): wer tritt jetzt
 * an, wer macht sich bereit (AKA rot / SHIRO weiß), wer wird gerade aufgerufen.
 * @var array<string, mixed> $turnier
 * @var array<string, mixed> $t Zustand der Tatami (TatamiAnsicht::fuerTatami)
 * @var bool $vollbild
 */
$tid = $turnier['id'];
$listenSichtbar = $statusBereiche['starterlisten'] ?? true;
$nurTatamis = !($statusBereiche['starterlisten'] ?? true) && !($statusBereiche['ergebnisse'] ?? true);
$anzahlDanach = $nurTatamis ? 8 : 4; // ohne Menüzeile ist Platz für mehr
/** Klassenkürzel – als Link zur Gruppe, wenn die Starterlisten sichtbar sind */
$gruppe = static fn (array $e, bool $fett = false) => $listenSichtbar
    ? '<a href="' . e(url("live/$tid/klasse/{$e['klasse_id']}")) . '">' . ($fett ? '<strong>' . e($e['klasse']) . '</strong>' : e($e['klasse'])) . '</a>'
    : ($fett ? '<strong>' . e($e['klasse']) . '</strong>' : e($e['klasse']));
$vollbild ??= false;
$l = $t['laufend'];
// Eine Gruppe erscheint erst, wenn ihr Ausruf läuft (vom Tisch gestartet, Gruppen-Aufruf oder
// ein Kampf im Ausruf) oder sie schon begonnen hat – auch der „aktuelle“ Kata-Vortrag nicht vorher
$aktiv = static function (array $e): bool {
    $a = $e['aufruf'] ?? [];
    return $e['status'] === 'laeuft' || !empty($e['gruppe_begonnen']) || !empty($e['ausruf_freigegeben']) || !empty($e['gruppe_aufgerufen'])
        || !empty($a['fertig']) || ($a['aka'] ?? $a['shiro'] ?? $a['starter'] ?? null) !== null;
};
$aktiveGruppen = [];
foreach (array_filter([$l, ...$t['naechste']]) as $e) {
    if ($aktiv($e)) {
        $aktiveGruppen[$e['klasse_id']] = true;
    }
}
if ($l !== null && !isset($aktiveGruppen[$l['klasse_id']])) {
    $t['naechste'] = [$l, ...$t['naechste']]; // noch nicht ausgerufen: nur als „Nächste Gruppe … Ausruf folgt“
    $l = null;
}
// Solange der Ausruf einer Gruppe läuft (noch nicht abgeschlossen, Gruppe nicht begonnen), stehen ihre
// Starter nur unter „Bitte zur Tatami“ – „Jetzt tritt an“ und „Bereit machen“ erst danach
$ausrufLaeuft = [];
foreach (array_filter([$l, ...$t['naechste']]) as $e) {
    if (isset($aktiveGruppen[$e['klasse_id']]) && empty($e['gruppe_begonnen']) && $e['status'] !== 'laeuft' && empty($e['aufruf']['fertig'])) {
        $ausrufLaeuft[$e['klasse_id']] = true;
    }
}
if ($l !== null && isset($ausrufLaeuft[$l['klasse_id']])) {
    $l = null;
}
// Stand des laufenden Ausrufs je Gruppe (sichtbar auch, wenn alle schon „da“ sind und die Liste leer ist)
$ausrufStand = [];
foreach (array_filter([$t['laufend'], ...$t['naechste']]) as $e) {
    if (!isset($ausrufLaeuft[$e['klasse_id']])) {
        continue;
    }
    $g = &$ausrufStand[$e['klasse_id']];
    $g ??= ['klasse' => $e['klasse'], 'starter' => [], 'da' => []];
    foreach ($e['starts'] as $seite => $startId) {
        if ($startId !== null) {
            $g['starter'][$startId] = true;
            if (($e['aufruf'][$seite] ?? null) === 'da') {
                $g['da'][$startId] = true;
            }
        }
    }
    unset($g);
}
$sichtbar = array_values(array_filter($t['naechste'], static fn ($e) => isset($aktiveGruppen[$e['klasse_id']]) && !isset($ausrufLaeuft[$e['klasse_id']])));
$bereit = $sichtbar[0] ?? null;
$weitere = array_slice($sichtbar, 1);
// nächste Gruppe, aus der noch niemand gerufen wird – nur als Hinweis ohne Namen
$folgeGruppe = null;
foreach ($t['naechste'] as $e) {
    if (!isset($aktiveGruppen[$e['klasse_id']])) {
        $folgeGruppe = $e;
        break;
    }
}
$art = static function (array $e): string {
    $text = match (true) {
        $e['disziplin'] === 'kumite' && !empty($e['kumite_form']) => kumite_form_text($e['kumite_form']),
        !empty($e['kumite_form']) => disziplin_text($e['disziplin']) . ' · ' . kumite_form_text($e['kumite_form']),
        default => disziplin_text($e['disziplin']),
    };
    $css = str_starts_with($e['disziplin'], 'kata') ? 'kata' : 'kumite';
    return '<span class="disziplin-tag ' . $css . (str_ends_with($e['disziplin'], '_team') ? ' team' : '') . '">' . e($text) . '</span>';
};
/** Paarung bzw. Starter: AKA rot, SHIRO weiß; Kata-Vortrag ohne Farbe. */
$paarung = static function (array $e): string {
    if ($e['typ'] === 'auftritt') {
        return '<div class="antritt einzel"><span class="name">' . e($e['starter']['name']) . '</span><span class="verein">' . e($e['starter']['verein']) . '</span></div>';
    }
    return '<div class="antritt paar">'
        . '<div class="seite aka"><span class="kennung">AKA</span><span class="name">' . e($e['aka']['name']) . '</span><span class="verein">' . e($e['aka']['verein']) . '</span></div>'
        . '<div class="seite shiro"><span class="kennung">SHIRO</span><span class="name">' . e($e['shiro']['name']) . '</span><span class="verein">' . e($e['shiro']['verein']) . '</span></div>'
        . '</div>';
};
$kopf = static fn (array $e, string $text) => '<div class="anzeige-kopf"><strong>' . $text . '</strong> · '
    . $gruppe($e) . ' · ' . e($e['runde'])
    . ($e['typ'] === 'auftritt' ? ' · Vortrag ' . e($e['nr']) : ' · Kampf ' . e($e['nr'])) . ' ' . $art($e) . '</div>';
?>
<div class="tatami-anzeige<?= $vollbild ? ' vollbild' : '' ?>">
    <h2><?= e($t['tatami']['bezeichnung']) ?><?php if (!empty($t['tatami']['gesperrt_am'])): ?> <span class="tag">🔒 gesperrt</span><?php endif; ?>
        <?php if (!$vollbild): ?>
            <span class="knopfleiste">
                <?= $t['tatami']['stream_url'] ? '<a class="tag info" href="' . e($t['tatami']['stream_url']) . '" target="_blank" rel="noopener">Livestream ↗</a>' : '' ?>
                <a class="tag" href="<?= e(url("live/$tid/tatami/{$t['tatami']['nummer']}")) ?>" target="_blank" title="Vollbild für einen Bildschirm an der Matte">Vollbild ↗</a>
            </span>
        <?php endif; ?>
    </h2>

    <?php if ($l): ?>
        <section class="block jetzt">
            <?= $kopf($l, 'Jetzt tritt an') ?>
            <div class="klein leise"><?= e($l['klasse_name']) ?></div>
            <?php if (!empty($l['unterbrochen'])): ?><div class="unterbrochen-hinweis">⏸ Kampf unterbrochen</div><?php endif; ?>
            <?= $paarung($l) ?>
        </section>
    <?php elseif ($t['offen'] === 0 && $t['beendet'] > 0): ?>
        <p class="leise">Alle Kämpfe und Vorträge dieser Tatami sind beendet.</p>
    <?php elseif (!$bereit && !$folgeGruppe && !$ausrufLaeuft): ?>
        <p class="leise">Aufruf folgt</p>
    <?php endif; ?>

    <?php if ($bereit): ?>
        <section class="block bereit">
            <?= $kopf($bereit, $l ? 'Bereit machen' : 'Als Nächstes') ?>
            <?php if (!$l): ?><div class="klein leise"><?= e($bereit['klasse_name']) ?></div><?php endif; ?>
            <?= $paarung($bereit) ?>
        </section>
    <?php endif; ?>

    <?php foreach ($ausrufStand as $g): $alleDa = count($g['da']) === count($g['starter']); ?>
        <div class="ausruf-stand<?= $alleDa ? ' fertig' : '' ?>">📣 Ausruf Gruppe <strong><?= e($g['klasse']) ?></strong> ·
            <?= $alleDa ? 'alle ' . count($g['starter']) . ' Starter da – gleich geht es los' : count($g['da']) . ' von ' . count($g['starter']) . ' da' ?></div>
    <?php endforeach; ?>
    <?php if ($t['aufruf_liste']): ?>
        <section class="block aufruf">
            <div class="anzeige-kopf"><strong>📣 Bitte zur <?= e($t['tatami']['bezeichnung']) ?></strong> <span class="klein">(<?= count($t['aufruf_liste']) ?>)</span></div>
            <ul class="aufruf-liste">
                <?php foreach ($t['aufruf_liste'] as $p): ?>
                    <li<?= $p['fehlt'] ? ' class="fehlt"' : '' ?>><span class="name"><?= e($p['name']) ?></span> <span class="klein"><?= e($p['verein']) ?> · <?= e($p['klasse']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($weitere && !$vollbild): ?>
        <div class="klein" style="margin-top:.6rem"><strong>DANACH</strong></div>
        <ol class="naechste">
            <?php foreach (array_slice($weitere, 0, $anzahlDanach) as $n): ?>
                <li><?= $gruppe($n, true) ?> · <?= e($n['runde']) ?> <?= $art($n) ?><br>
                    <?= $n['typ'] === 'auftritt' ? e($n['starter']['name']) : '<span class="aka-text">' . e($n['aka']['name']) . '</span> <span class="leise">/</span> ' . e($n['shiro']['name']) ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if (count($weitere) > $anzahlDanach): ?><p class="klein leise">+ <?= count($weitere) - $anzahlDanach ?> weitere</p><?php endif; ?>
    <?php endif; ?>
    <?php if ($folgeGruppe): ?>
        <div class="folge-gruppe">Nächste Gruppe <strong><?= e($folgeGruppe['klasse']) ?></strong> <span class="klein"><?= e($folgeGruppe['klasse_name']) ?></span> · <span class="leise">Ausruf folgt</span></div>
    <?php endif; ?>
    <p class="klein"><?= $t['beendet'] ?> beendet · <?= $t['offen'] ?> offen</p>
</div>
