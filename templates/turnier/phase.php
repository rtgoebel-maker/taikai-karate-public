<?php
/**
 * Phasenleiste der Turnier-Übersicht: aktuelle Phase, Wechsel mit Prüfliste.
 * @var array<string, mixed> $turnier
 * @var string $phase
 * @var bool $meldephaseGewesen gab es eine Meldephase? Sonst gilt sie als übersprungen
 * @var array<string, list<string>> $offenePunkte je Zielphase; 'laeuft_rest' = vor dem Start nicht automatisch behebbar
 */
use Wettkampf\Domain\TurnierPhase;

$tid = $turnier['id'];
$phasen = array_keys(TurnierPhase::ALLE);
$index = array_search($phase, $phasen, true);
$wirkung = [
    TurnierPhase::VORBEREITUNG => 'Klassen, Teilnehmer und Plan vorbereiten. Online-Anmeldung geschlossen, Ergebnisse gesperrt, die Statusseite zeigt „Turnier noch nicht gestartet“.',
    TurnierPhase::MELDEPHASE => 'Die Online-Anmeldung für die Dojos ist geöffnet (bis zum Meldeschluss). Ergebnisse sind noch gesperrt.',
    TurnierPhase::LAEUFT => 'Turniertag: Tablets und PC tragen Ergebnisse ein, die Statusseite ist live. Die Online-Anmeldung ist geschlossen.',
    TurnierPhase::ABGESCHLOSSEN => 'Ergebnisse sind gesperrt, die Siegerliste ist endgültig. Für Korrekturen zurück auf „Turnier läuft“ schalten.',
];
$startPunkte = $offenePunkte[TurnierPhase::LAEUFT] ?? [];
$startRest = $offenePunkte['laeuft_rest'] ?? [];
$behebbar = count($startPunkte) > count($startRest);
$vorbereiten = '<label class="check vorbereiten"><input type="checkbox" name="vorbereiten" value="1" checked> '
    . 'Offene Klassen jetzt auslosen und auf die Tatamis verteilen <span class="hilfe">(Kata und Kumite getrennt; Klassen ohne Starter bleiben unberücksichtigt)</span></label>';

/**
 * Formular für einen Phasenwechsel mit Prüfliste. „trotzdem umschalten“ ist nur
 * Pflicht, wenn Punkte offen bleiben, die nicht automatisch erledigt werden.
 *
 * @param list<string> $punkte
 * @param list<string> $rest
 */
$knopf = function (string $ziel, string $text, string $art = '', array $punkte = [], array $rest = [], string $zusatz = '') use ($tid): string {
    $html = '<form method="post" action="' . e(url("turnier/$tid/phase")) . '" class="phase-wechsel" data-bestaetigen="' . e('Turnierphase auf „' . TurnierPhase::text($ziel) . '“ umschalten?') . '">'
        . csrf_feld() . '<input type="hidden" name="phase" value="' . e($ziel) . '">';
    if ($punkte) {
        $html .= '<div class="meldung meldung-warnung"><strong>Noch offen:</strong><ul>';
        foreach ($punkte as $p) {
            $html .= '<li>' . e($p) . '</li>';
        }
        $html .= '</ul>' . $zusatz;
        if ($rest) {
            $html .= '<label class="check"><input type="checkbox" name="trotzdem" value="1" required> trotzdem umschalten</label>';
        }
        $html .= '</div>';
    }
    return $html . '<button class="knopf ' . $art . '" type="submit">' . e($text) . '</button></form>';
};
$start = static fn (string $art) => $knopf(TurnierPhase::LAEUFT, '▶ Turnier starten', $art, $startPunkte, $startRest, $behebbar ? $vorbereiten : '');
?>
<section class="karte phasen">
    <h2>Turnierphase <?= info('phasen') ?></h2>
    <ol class="phasen-leiste">
        <?php foreach ($phasen as $i => $p): ?>
            <?php $uebersprungen = $i < $index && $p === TurnierPhase::MELDEPHASE && !($meldephaseGewesen ?? true); ?>
            <li class="<?= $uebersprungen ? 'uebersprungen' : ($i < $index ? 'erledigt' : ($i === $index ? 'aktiv' : '')) ?>"><span class="nr"><?= $i + 1 ?></span> <?= e(TurnierPhase::text($p)) ?><?= $uebersprungen ? ' <span class="klein">(übersprungen)</span>' : '' ?></li>
        <?php endforeach; ?>
    </ol>
    <p><strong><?= e(TurnierPhase::text($phase)) ?>:</strong> <?= e($wirkung[$phase]) ?></p>
    <div class="phase-knoepfe">
        <?php if ($phase === TurnierPhase::VORBEREITUNG): ?>
            <?= $knopf(TurnierPhase::MELDEPHASE, 'Meldephase starten (Online-Anmeldung öffnen)', 'primaer') ?>
            <?= $start('') ?>
        <?php elseif ($phase === TurnierPhase::MELDEPHASE): ?>
            <?= $start('primaer') ?>
            <?= $knopf(TurnierPhase::VORBEREITUNG, 'Anmeldung schließen (zurück zur Vorbereitung)') ?>
        <?php elseif ($phase === TurnierPhase::LAEUFT): ?>
            <?php if ($behebbar): ?>
                <form method="post" action="<?= e(url("turnier/$tid/phase")) ?>" class="phase-wechsel">
                    <?= csrf_feld() ?><input type="hidden" name="phase" value="<?= TurnierPhase::LAEUFT ?>"><input type="hidden" name="vorbereiten" value="1">
                    <div class="meldung meldung-warnung"><strong>Ohne Tatami – auf den Tablets nicht sichtbar:</strong><ul>
                        <?php foreach (array_diff($startPunkte, $startRest) as $p): ?><li><?= e($p) ?></li><?php endforeach; ?>
                    </ul></div>
                    <button class="knopf primaer" type="submit">Offene Klassen auslosen und auf die Tatamis verteilen</button>
                </form>
            <?php endif; ?>
            <?= $knopf(TurnierPhase::ABGESCHLOSSEN, '■ Turnier abschließen', $behebbar ? '' : 'primaer', $offenePunkte[TurnierPhase::ABGESCHLOSSEN] ?? [], $offenePunkte[TurnierPhase::ABGESCHLOSSEN] ?? []) ?>
            <?= $knopf(TurnierPhase::VORBEREITUNG, 'Turnier anhalten (zurück zur Vorbereitung)') ?>
        <?php else: ?>
            <?= $knopf(TurnierPhase::LAEUFT, 'Wieder öffnen (Turnier läuft)') ?>
        <?php endif; ?>
    </div>
</section>
