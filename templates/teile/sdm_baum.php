<?php
/**
 * Wettkampfbaum im SDM-Layout: Spalten je Runde, Verbindungslinien,
 * Shiro oben (dunkel), Aka unten (rot), ★ Sieger, gelbe Ziel-/Finalkarten.
 * Große Bäume werden in Teile zu je 4 Erstrundenkämpfen aufgeteilt.
 *
 * @var array<string, mixed> $phase      Phase aus KlassenAnsicht
 * @var string $code                     Gruppen-Code
 * @var string $stand                    Zeitstempel 'Y-m-d H:i:s'
 * @var int $revision
 * @var string|null $zielText            Zielkarte je Pool-Sieger (z. B. „Punktefinale“)
 * @var bool $druck                      Druckansicht (Teile auf eigenen Seiten, auf A4 quer skaliert)
 */
use Wettkampf\Ausgaben\BaumLayout;

$zielText ??= null;
$druck ??= false;
$kurz = $phase['tatami_nummer'] !== null ? 'T' . $phase['tatami_nummer'] : '';
$standText = datum_de(substr($stand, 0, 10)) . ' ' . substr($stand, 11, 5);
$teile = BaumLayout::teile($phase['begegnungen'], $zielText);
$platzhalter = static fn (string $t) => str_replace(['Sieger Kampf ', 'Verlierer Kampf '], ['Sieger #', 'Verlierer #'], $t);

$seite = static function (array $b, string $farbe) use ($platzhalter): string {
    $name = $b["{$farbe}_name"];
    $gewinnt = $b['sieger_start_id'] !== null && (int) $b['sieger_start_id'] === (int) $b["{$farbe}_start_id"] && $b["{$farbe}_start_id"] !== null;
    if ($name === '') {
        $text = $platzhalter((string) $b["{$farbe}_platzhalter"]);
        return '<div class="sb-seite ' . $farbe . '"><div class="sb-name">' . e($text !== '' ? $text : '–') . '</div></div>';
    }
    return '<div class="sb-seite ' . $farbe . '"><div class="sb-name">' . ($gewinnt ? '★ ' : '') . e($name) . '</div>'
        . '<div class="sb-dojo">' . e($b["{$farbe}_verein"]) . '</div></div>';
};
$titel = static function (array $b) use ($kurz): string {
    $runde = match ($b['art']) {
        'finale' => 'Finale',
        'platz3' => 'Platz 3',
        default => 'Runde ' . $b['runde'],
    };
    return implode(' · ', array_filter([$b['nr'] ? '#' . $b['nr'] : 'Freilos', $runde, $kurz]));
};
?>
<?php foreach ($teile as $i => $teil):
    $zoom = $druck ? min(1, 1040 / max(1, $teil['breite'])) : 1;
    ?>
    <div class="sb-teil<?= $druck && $i > 0 ? ' sb-neue-seite' : '' ?>">
        <p class="sb-legende">
            <strong><?= e($code) ?> · Stand <?= e($standText) ?> · Revision <?= (int) $revision ?></strong><br>
            Shiro oben · Aka unten · ★ Sieger<?= count($teile) > 1 ? ' · Teil ' . ($i + 1) . '/' . count($teile) : '' ?>
        </p>
        <div class="sb-rahmen">
            <div class="sb-baum" style="width:<?= $teil['breite'] ?>px;height:<?= $teil['hoehe'] ?>px<?= $zoom < 1 ? ';zoom:' . round($zoom, 3) : '' ?>">
                <svg class="sb-linien" width="<?= $teil['breite'] ?>" height="<?= $teil['hoehe'] ?>" aria-hidden="true">
                    <?php foreach ($teil['linien'] as $pfad): ?><path d="<?= e($pfad) ?>"/><?php endforeach; ?>
                </svg>
                <?php foreach ($teil['karten'] as $k): ?>
                    <?php if ($k['art'] === 'zielplatz'):
                        $quelle = current(array_filter($phase['begegnungen'], static fn ($b) => (int) $b['id'] === $k['quelle']));
                        $sieger = $quelle && $quelle['sieger_start_id'] !== null
                            ? ((int) $quelle['sieger_start_id'] === (int) $quelle['aka_start_id'] ? $quelle['aka_name'] : $quelle['shiro_name'])
                            : 'Sieger #' . ($quelle['nr'] ?? '?');
                        ?>
                        <div class="sb-karte ziel" style="left:<?= $k['x'] ?>px;top:<?= $k['y'] ?>px">
                            <div class="sb-kopf"><?= e($k['text']) ?></div>
                            <div class="sb-seite aka"><div class="sb-name"><?= e($sieger) ?></div></div>
                        </div>
                    <?php else: $b = $k['b']; ?>
                        <div class="sb-karte<?= $k['art'] === 'ziel' ? ' ziel' : '' ?><?= $b['status'] === 'freilos' ? ' freilos' : '' ?><?= $b['status'] === 'laeuft' ? ' laeuft' : '' ?>" style="left:<?= $k['x'] ?>px;top:<?= $k['y'] ?>px">
                            <div class="sb-kopf"><?= e($titel($b)) ?><?= $b['status'] === 'laeuft' ? ' · läuft' : '' ?></div>
                            <?= $seite($b, 'shiro') ?>
                            <?= $seite($b, 'aka') ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
