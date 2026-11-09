<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $tatamis
 * @var array<int, list<array<string, mixed>>> $plan  0 = nicht zugeordnet
 * @var string $beginn
 * @var array<string, float> $minuten
 * @var int $offeneKlassen
 * @var array{modus: string, getrennt: bool} $verteilung
 * @var list<array<string, mixed>> $nachrichten  letzte Nachrichten an die Tische
 */
use Wettkampf\Durchfuehrung\NachrichtService;
use Wettkampf\Durchfuehrung\PlanService;

$this->layout('layout', ['titel' => 'Tatami-Plan', 'turnier' => $turnier, 'aktiv' => 'plan']);
$tid = $turnier['id'];
$dynamisch = $verteilung['modus'] === 'dynamisch';
$spalten = [0 => $dynamisch ? 'Warteschlange' : 'Nicht zugeordnet'];
$gesperrt = [];
foreach ($tatamis as $t) {
    $spalten[(int) $t['id']] = $t['bezeichnung'];
    if ($t['gesperrt_am']) {
        $gesperrt[(int) $t['id']] = $t['sperr_grund'] ?: '';
    }
}
$fmt = static fn (float $min) => $min >= 60 ? sprintf('%d:%02d h', intdiv((int) round($min), 60), (int) round($min) % 60) : round($min) . ' min';
?>
<div class="titelzeile">
    <h1>Tatami-Plan <?= info('tatami-plan') ?></h1>
    <?php if (!$dynamisch): ?>
    <form method="post" action="<?= e(url("turnier/$tid/plan/automatisch")) ?>" class="knopfleiste">
        <?= csrf_feld() ?>
        <label class="check" title="Kata-Gruppen auf die erste Hälfte der Tatamis, Kumite-Gruppen auf die zweite"><input type="checkbox" name="getrennt" value="1"<?= $verteilung['getrennt'] ? ' checked' : '' ?>> Kata und Kumite auf getrennten Tatamis</label>
        <button class="knopf primaer" type="submit">Offene automatisch verteilen</button>
    </form>
    <?php endif; ?>
    <?php if (!$dynamisch): ?>
        <form method="post" action="<?= e(url("turnier/$tid/plan/leeren")) ?>" class="inline" data-bestaetigen="Alle noch nicht begonnenen Gruppen von den Tatamis nehmen? Laufende und beendete bleiben."><?= csrf_feld() ?><button class="knopf" type="submit">Plan leeren</button></form>
    <?php endif; ?>
    <a class="knopf" href="<?= e(url("turnier/$tid/druck")) ?>">Kampflisten drucken</a>
</div>
<?php if ($offeneKlassen > 0): ?>
    <div class="meldung meldung-hinweis"><?= $offeneKlassen ?> Klassen mit Startern sind noch nicht ausgelost und erscheinen erst nach der Auslosung im Plan. <a href="<?= e(url("turnier/$tid/klassen")) ?>">Zu den Klassen</a></div>
<?php endif; ?>

<div class="steuerung">
    <form method="post" action="<?= e(url("turnier/$tid/plan/verteilung")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2>Verteilung <?= info('verteilung') ?></h2>
        <?php foreach (PlanService::MODI as $modus => $text): ?>
            <label class="check"><input type="radio" name="modus" value="<?= e($modus) ?>"<?= $verteilung['modus'] === $modus ? ' checked' : '' ?>> <?= e($text) ?></label>
        <?php endforeach; ?>
        <label class="check" title="Kata-Gruppen bevorzugt auf die erste Hälfte der Tatamis, Kumite auf die zweite"><input type="checkbox" name="getrennt" value="1"<?= $verteilung['getrennt'] ? ' checked' : '' ?>> Kata und Kumite getrennt (bevorzugt)</label>
        <div class="knopfleiste">
            <button class="knopf" type="submit">Übernehmen</button>
            <button class="knopf" type="submit" formaction="<?= e(url("turnier/$tid/plan/schlange-sortieren")) ?>" title="Kata vor Kumite, jüngere bzw. niedrigere Klassen zuerst – von Hand geänderte Reihenfolge der Warteschlange geht verloren">Warteschlange neu sortieren</button>
            <?php if ($dynamisch): ?>
                <button class="knopf" type="submit" formaction="<?= e(url("turnier/$tid/plan/zurueckstellen")) ?>" title="z. B. nach dem Wechsel vom festen Plan: Tatamis behalten nur ihre begonnene Gruppe">Nicht begonnene Gruppen in die Warteschlange</button>
            <?php endif; ?>
        </div>
        <p class="hilfe"><?= $dynamisch
            ? 'Jede Tatami hat nur ihre aktuelle Gruppe. Ist deren letzter Kampf entschieden, bekommt die Tatami sofort die nächste Gruppe aus der Warteschlange – so steht keine Tatami still. Reihenfolge der Warteschlange per Ziehen ändern.'
            : 'Alle Gruppen werden vorab auf die Tatamis verteilt; Reihenfolge per Ziehen ändern.' ?></p>
    </form>

    <form method="post" action="<?= e(url("turnier/$tid/nachricht")) ?>" class="karte formular">
        <?= csrf_feld() ?>
        <h2>Nachricht an die Tische <?= info('nachricht') ?></h2>
        <div class="zeile">
            <label>An <select name="tatami_id">
                <option value="">alle Tatamis</option>
                <?php foreach ($tatamis as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['bezeichnung']) ?></option><?php endforeach; ?>
            </select></label>
            <label style="grid-column: span 3">Text <input name="text" list="nachricht-vorlagen" required maxlength="500" placeholder="z. B. Bitte ein Mitglied der Tischbesatzung zur Wettkampfleitung."></label>
        </div>
        <datalist id="nachricht-vorlagen"><?php foreach (NachrichtService::VORLAGEN as $v): ?><option value="<?= e($v) ?>"><?php endforeach; ?></datalist>
        <div><button class="knopf primaer" type="submit">✉ Senden</button></div>
        <?php if ($nachrichten): ?>
            <table class="tabelle klein nachrichten-liste">
                <?php foreach ($nachrichten as $n): ?>
                    <tr><td><?= e(substr($n['erstellt_am'], 11, 5)) ?></td><td><?= e($n['tatami']) ?></td><td><?= e($n['text']) ?></td>
                        <td><?= $n['bestaetigt_am'] ? '<span class="tag ok">✓ ' . e(substr($n['bestaetigt_am'], 11, 5)) . '</span>' : '<span class="tag warn">offen</span>' ?></td></tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </form>
</div>

<form method="post" action="<?= e(url("turnier/$tid/plan/einstellungen")) ?>" class="karte knopfleiste">
    <?= csrf_feld() ?>
    <label class="feld">Beginn <input name="beginn" value="<?= e($beginn) ?>" pattern="\d{1,2}:\d{2}" style="width:6rem"></label>
    <?php foreach ($minuten as $d => $m): ?>
        <label class="feld"><?= e(disziplin_text($d)) ?> (min/Kampf) <input name="minuten_<?= $d ?>" value="<?= e(str_replace('.', ',', (string) $m)) ?>" inputmode="decimal" style="width:6rem"></label>
    <?php endforeach; ?>
    <button class="knopf" type="submit" style="align-self:end">Zeiten übernehmen</button>
</form>

<p class="klein"><?= $dynamisch ? 'In der Warteschlange bestimmt die Reihenfolge, welche Gruppe als nächste an eine freie Tatami geht. ' : '' ?>Karten per Maus ziehen, um Reihenfolge und Tatami zu ändern. Klassen mit „bleibt auf derselben Tatami“ wandern komplett mit. Zeiten sind Schätzungen aus der Anzahl der Kämpfe.</p>
<label class="check klein"><input type="checkbox" id="beendete-zeigen"> Beendete Gruppen einblenden</label>
<div class="plan" style="--spalten: <?= count($spalten) ?>" data-verschieben="<?= e(url("turnier/$tid/plan/verschieben")) ?>">
    <?php foreach ($spalten as $tatamiId => $titel):
        $eintraege = $plan[$tatamiId] ?? [];
        $gesamt = array_sum(array_column($eintraege, 'dauer'));
        ?>
        <div class="plan-spalte<?= isset($gesperrt[$tatamiId]) ? ' gesperrt' : '' ?>" data-tatami="<?= $tatamiId ?>">
            <h3><span><?= isset($gesperrt[$tatamiId]) ? '🔒 ' : '' ?><?= e($titel) ?></span><span class="klein"><?= count($eintraege) ?> · <?= e($fmt($gesamt)) ?></span></h3>
            <?php if ($tatamiId !== 0): ?>
                <p class="klein" style="margin:-.3rem .3rem .5rem"><a href="<?= e(url('tisch/app', ['tatami' => $tatamiId])) ?>" target="_blank">Tisch-Ansicht ↗</a> · <a href="<?= e(url('tisch/ausruf', ['tatami' => $tatamiId])) ?>" target="_blank">Ausruf ↗</a></p>
                <form method="post" action="<?= e(url("turnier/$tid/tatami/$tatamiId/sperre")) ?>" class="sperre">
                    <?= csrf_feld() ?>
                    <?php if (isset($gesperrt[$tatamiId])): ?>
                        <div class="sperr-hinweis">Gesperrt – keine neuen Gruppen<?= $gesperrt[$tatamiId] !== '' ? ': ' . e($gesperrt[$tatamiId]) : '' ?></div>
                        <button class="knopf klein" type="submit" name="an" value="0">🔓 Freigeben</button>
                    <?php else: ?>
                        <details><summary class="klein">🔒 Sperren …</summary>
                            <input name="grund" maxlength="200" placeholder="Grund (optional), z. B. Matte wird getauscht">
                            <button class="knopf klein" type="submit" name="an" value="1">Sperren</button>
                            <p class="klein leise">Eine laufende Gruppe läuft zu Ende, danach bekommt die Tatami keine neuen Gruppen. Noch nicht begonnene gehen zurück in die Warteschlange.</p>
                        </details>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
            <?php
            // beendete Gruppen standardmäßig eingeklappt (Übersicht); laufende und offene bleiben sichtbar
            $beendet = array_values(array_filter($eintraege, static fn ($p) => $p['status'] === 'beendet'));
            ?>
            <?php if ($beendet): ?>
                <details class="beendete-gruppen">
                    <summary>✓ <?= count($beendet) ?> beendete <?= count($beendet) === 1 ? 'Gruppe' : 'Gruppen' ?></summary>
                    <?php foreach ($beendet as $p): ?>
                        <div class="plan-karte beendet">
                            <div class="titel"><a href="<?= e(url("turnier/$tid/klasse/{$p['klasse_id']}")) ?>"><?= e($p['code']) ?></a> · <?= e($p['bezeichnung']) ?></div>
                            <div class="klein"><?= e(disziplin_text($p['disziplin'])) ?> · <?= (int) $p['starter'] ?> Starter · <?= (int) $p['kaempfe_beendet'] + (int) $p['auftritte_beendet'] ?> / <?= (int) $p['kaempfe'] + (int) $p['auftritte'] ?> <?= (int) $p['auftritte'] > 0 ? 'Vorträge' : 'Kämpfe' ?></div>
                        </div>
                    <?php endforeach; ?>
                </details>
            <?php endif; ?>
            <?php
            $position = array_flip(array_map('intval', array_column($eintraege, 'id'))); // Position in der ganzen Spalte (fürs Verschieben)
            $eintraege = array_values(array_filter($eintraege, static fn ($p) => $p['status'] !== 'beendet'));
            ?>
            <?php foreach ($eintraege as $i => $p):
                $einheiten = (int) $p['kaempfe'] + (int) $p['auftritte'];
                $fertig = (int) $p['kaempfe_beendet'] + (int) $p['auftritte_beendet'];
                $css = $p['status'] === 'beendet' ? 'beendet' : ($p['status'] === 'laeuft' ? 'laeuft' : '');
                ?>
                <div class="plan-karte <?= $css ?>" draggable="true" data-phase="<?= $p['id'] ?>">
                    <div class="titel">
                        <?= $p['beginn'] ? '<span class="leise">' . e($p['beginn']) . '</span> ' : '' ?>
                        <a href="<?= e(url("turnier/$tid/klasse/{$p['klasse_id']}")) ?>"><?= e($p['code']) ?></a> · <?= e($p['bezeichnung']) ?>
                    </div>
                    <div class="klein"><?= e(disziplin_text($p['disziplin'])) ?> · <?= e(geschlecht_text($p['geschlecht'])) ?><?= $p['gleiche_flaeche'] ? ' · 📌 feste Tatami' : '' ?></div>
                    <div class="klein">
                        <?= (int) $p['starter'] ?> Starter ·
                        <?= $p['status'] === 'wartet' ? 'wartet auf Vorrunde' : "$fertig / $einheiten " . ((int) $p['auftritte'] > 0 ? 'Vorträge' : 'Kämpfe') ?>
                        · <?= e($fmt($p['dauer'])) ?>
                    </div>
                    <form method="post" action="<?= e(url("turnier/$tid/plan/verschieben")) ?>" class="knopfleiste" style="margin-top:.3rem">
                        <?= csrf_feld() ?>
                        <input type="hidden" name="phase_id" value="<?= $p['id'] ?>">
                        <select name="tatami_id" aria-label="Tatami" style="width:auto;padding:.15rem .3rem;font-size:.8rem">
                            <?php foreach ($spalten as $sid => $stitel): ?><option value="<?= $sid ?>"<?= sel($sid, $tatamiId) ?>><?= e($stitel) ?></option><?php endforeach; ?>
                        </select>
                        <button class="knopf klein" type="submit" title="Zur gewählten Tatami (ans Ende)">→</button>
                        <?php if ($tatamiId !== 0 && $i > 0): ?>
                            <button class="knopf klein" type="submit" name="position" value="<?= $position[(int) $p['id']] - 1 ?>" data-setzen="tatami_id" data-wert="<?= $tatamiId ?>" title="nach oben">↑</button>
                        <?php endif; ?>
                        <?php if ($tatamiId !== 0 && $i < count($eintraege) - 1): ?>
                            <button class="knopf klein" type="submit" name="position" value="<?= $position[(int) $p['id']] + 1 ?>" data-setzen="tatami_id" data-wert="<?= $tatamiId ?>" title="nach unten">↓</button>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<script src="<?= e(asset('plan.js')) ?>" defer></script>
