<?php
/**
 * Öffentlicher Aushang „Starterlisten & Wettkampfbäume“.
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $gruppen
 * @var list<array<string, mixed>> $personen
 * @var int $starts
 * @var int $revision
 * @var string $stand
 * @var list<array<string, mixed>> $tatamis
 * @var bool $offline
 * @var string|null $css
 * @var string|null $js   eingebettetes Skript (nur Offlinekopie)
 */
$this->layout('live/layout', ['turnier' => $turnier, 'aktiv' => 'starterlisten', 'automatisch' => false, 'offline' => $offline, 'css' => $css ?? null]);
$tid = $turnier['id'];
$monate = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
$d = explode('-', $turnier['datum']);
$datumLang = (int) $d[2] . '. ' . $monate[(int) $d[1]] . ' ' . $d[0];
$standText = datum_de(substr($stand, 0, 10)) . ' ' . substr($stand, 11, 5);
$statusText = [
    'offen' => ['Auslosung folgt', 'warn'],
    'ausgelost' => ['Startliste bestätigt', 'ok'],
    'laeuft' => ['Wettkampf läuft', 'info'],
    'beendet' => ['Wettkampf beendet', 'info'],
    'bestaetigt' => ['Ergebnis bestätigt', 'ok'],
];
?>
<p class="obertitel"><?= e(mb_strtoupper($turnier['name'])) ?> · <?= e(mb_strtoupper($datumLang)) ?></p>
<h1>Starterlisten &amp; Wettkampfbäume</h1>
<p>Alle Gruppen direkt als Aushang – für alle Besucher ohne Anmeldung.</p>
<p class="sl-stand">
    <span class="tag ok">Öffentlich freigegeben</span>
    <span class="klein">Listenstand <?= e($standText) ?> · Revision <?= $revision ?> · Maßgeblich bleibt der Aufruf an der Tatami.</span>
</p>
<?php if ($offline): ?>
    <div class="meldung meldung-hinweis">Gespeicherte öffentliche Listen · funktionieren auch ohne Liveverbindung · Stand <?= e($standText) ?></div>
<?php else: ?>
    <div class="meldung meldung-hinweis sl-neu" id="sl-neu" hidden>
        Ein neuer Listenstand ist verfügbar. <button type="button" class="knopf klein" data-neu-laden>Jetzt aktualisieren</button>
    </div>
<?php endif; ?>

<div class="sl-reiter" role="tablist">
    <button type="button" class="aktiv" data-reiter="gruppen" role="tab">Gruppen &amp; Bäume</button>
    <button type="button" data-reiter="namen" role="tab">Nach Namen suchen</button>
</div>

<section id="sl-gruppen">
    <div class="karte sl-filter">
        <input id="sl-suche" type="search" placeholder="Name, Dojo oder Gruppe" aria-label="Name, Dojo oder Gruppe">
        <select id="sl-gruppe" aria-label="Gruppe">
            <option value="">Alle Gruppen</option>
            <?php foreach ($gruppen as $g): ?>
                <option value="<?= e($g['anker']) ?>"><?= e($g['code']) ?> · <?= $g['anzahl'] ?> Starts</option>
            <?php endforeach; ?>
        </select>
        <select id="sl-tatami" aria-label="Tatami">
            <option value="">Alle Tatamis</option>
            <?php foreach ($tatamis as $t): ?><option value="<?= (int) $t['nummer'] ?>"><?= e($t['bezeichnung']) ?></option><?php endforeach; ?>
        </select>
        <span id="sl-zusammenfassung" class="sl-zusammenfassung"><?= count($gruppen) ?> Gruppen · <?= $starts ?> Starts</span>
        <span class="sl-aktionen">
            <button type="button" class="knopf" id="sl-auswahl-drucken">Auswahl drucken</button>
            <?php if (!$offline): ?>
                <a class="knopf" href="<?= e(url("live/$tid/starterlisten/offline")) ?>">Offlinekopie</a>
            <?php endif; ?>
        </span>
    </div>

    <nav class="sl-index" aria-label="Gruppen">
        <?php foreach ($gruppen as $g): ?>
            <a class="sl-chip" href="#<?= e($g['anker']) ?>" data-anker="<?= e($g['anker']) ?>"><strong><?= e($g['code']) ?></strong><span><?= $g['anzahl'] ?></span></a>
        <?php endforeach; ?>
    </nav>

    <?php foreach ($gruppen as $g):
        // Suchtext für den Filter: Code, Bezeichnung, Namen und Vereine
        $namen = array_merge(
            array_column($g['starter'], 'name'), array_column($g['starter'], 'verein'),
            ...array_map(static fn ($p) => array_merge(array_column($p['starter_liste'], 'name'), array_column($p['starter_liste'], 'verein')), $g['phasen']),
        );
        $suchtext = mb_strtolower($g['code'] . ' ' . $g['bezeichnung'] . ' ' . implode(' ', $namen));
        [$status, $statusCss] = $statusText[$g['status']] ?? [$g['status'], ''];
        ?>
        <article class="karte sl-gruppe" id="<?= e($g['anker']) ?>" data-tatamis=",<?= e(implode(',', array_keys($g['tatamis']))) ?>," data-text="<?= e($suchtext) ?>">
            <p class="obertitel sl-disziplin"><?= e(disziplin_text($g['disziplin'])) ?></p>
            <div class="titelzeile">
                <h2><?= e($g['code']) ?></h2>
                <span class="tag <?= $statusCss ?>"><?= e($status) ?></span>
                <button type="button" class="knopf klein sl-gruppe-drucken" data-anker="<?= e($g['anker']) ?>">Diese Gruppe drucken</button>
            </div>
            <p class="sl-regeln"><?= e($g['bezeichnung']) ?> <span class="leise">(<?= e($g['regeln']) ?>)</span></p>
            <p><strong><?= $g['anzahl'] ?> Starts<?= $g['tatamis'] ? ' · ' . e(implode(', ', $g['tatamis'])) : '' ?></strong></p>

            <?php if ($g['status'] === 'offen'): ?>
                <h4 class="sl-unter">Starterliste (vorläufig)</h4>
                <table class="tabelle sl-liste">
                    <thead><tr><th class="sl-nr">Nr.</th><th>Starter / Team</th><th>Dojo</th></tr></thead>
                    <tbody>
                    <?php foreach ($g['starter'] as $i => $s): ?>
                        <tr><td class="sl-nr zahl"><?= $i + 1 ?></td><td><strong><?= e($s['name']) ?></strong></td><td><?= e($s['verein']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="klein">Der Wettkampfbaum erscheint nach der Auslosung.</p>
            <?php else: ?>
                <?php foreach ($g['phasen'] as $phase): ?>
                    <?= $this->teil('live/sl_phase', [
                        'phase' => $phase, 'gruppe' => $g, 'mehrerePhasen' => count($g['phasen']) > 1,
                        'stand' => $stand, 'revision' => $revision,
                    ]) ?>
                <?php endforeach; ?>
                <?php if ($g['platzierungen'] ?? []): ?>
                    <h4 class="sl-unter">Platzierungen</h4>
                    <table class="tabelle podest sl-liste">
                        <?php foreach ($g['platzierungen'] as $p): ?>
                            <tr><td class="platz"><?= $p['platz'] ?>.</td><td><strong><?= e($p['name']) ?></strong></td><td><?= e($p['verein']) ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if (!$gruppen): ?><div class="karte leer">Noch keine Gruppen mit Startern.</div><?php endif; ?>
    <p class="leise" id="sl-keine" hidden>Keine Gruppe passt zum Filter.</p>
</section>

<section id="sl-namen" hidden>
    <div class="karte sl-filter">
        <input id="sl-namensuche" type="search" placeholder="Name oder Dojo eingeben (mind. 2 Zeichen)" aria-label="Name oder Dojo">
        <span class="sl-zusammenfassung"><?= count($personen) ?> Starts</span>
    </div>
    <table class="tabelle">
        <thead><tr><th>Starter / Team</th><th>Dojo</th><th>Gruppe</th><th>Tatami</th></tr></thead>
        <tbody id="sl-treffer"><tr><td colspan="4" class="leise">Bitte Suchbegriff eingeben.</td></tr></tbody>
    </table>
</section>

<script type="application/json" id="sl-daten"><?= json_encode([
    'personen' => $personen,
    'revision' => (int) $revision,
    'revisionUrl' => $offline ? null : url("api/v1/turnier/$tid"),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php if ($offline): ?>
<?php /* Offlinekopie: eigenständige Datei ohne Server und ohne CSP – Skript eingebettet */ ?>
<script><?= $js ?? '' ?></script>
<?php else: ?>
<script src="<?= e(asset('starterlisten.js')) ?>" defer></script>
<?php endif; ?>
