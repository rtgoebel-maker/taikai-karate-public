<?php
/**
 * Kämpfe/Vorträge mit Eintragefeldern (Kampfliste der Tischbesatzung, Notfall-Satz).
 * @var list<array{klasse: array<string, mixed>, phase: array<string, mixed>}> $bloecke
 * @var bool|null $handschrift  Notfall-Satz: wartendes Punktefinale als leere Tabelle
 */
$handschrift ??= false;
$vorher = null;
$legendeGezeigt = false;
// Offizielle Wertungssymbole zum schnellen Mitschreiben: Kreis = Ippon, Dreieck = Wazaari
$legende = '<p class="symbol-legende">Mitschrift Kumite: <span class="symbol">◯</span> Ippon · <span class="symbol">△</span> Wazaari (2 △ = ◯, Awazete Ippon) · '
    . 'Strafen: K Keikoku, C Hansoku Chui, H Hansoku – mit J bzw. M davor für Jogai bzw. Mubobi (z. B. JK, MC)</p>';
$entscheidungen = '<span class="kasten"></span>Wertung <span class="kasten"></span>Hantei <span class="kasten"></span>Kiken <span class="kasten"></span>Hansoku <span class="kasten"></span>Shikkaku';
?>
    <?php foreach ($bloecke as ['klasse' => $klasse, 'phase' => $phase]):
        $kr = (int) $klasse['kampfrichter'];
        $istKata = str_starts_with($klasse['disziplin'], 'kata');
        $leerZeilen = $handschrift && $vorher === 'auftritte' ? (int) $klasse['finalisten'] : 0;
        $vorher = $phase['art'];
        ?>
        <h2><?= e($klasse['code']) ?> · <?= e($klasse['bezeichnung']) ?> – <?= e($phase['bezeichnung']) ?></h2>
        <?php if (!$legendeGezeigt && in_array($klasse['disziplin'], ['kumite', 'kumite_team'], true)): $legendeGezeigt = true; ?><?= $legende ?><?php endif; ?>
        <?php if ($phase['status'] === 'wartet' && $leerZeilen > 0): ?>
            <?= $this->teil('druck/phase', ['phase' => $phase, 'klasse' => $klasse, 'handschrift' => true, 'leerZeilen' => $leerZeilen]) ?>
            <?php continue; ?>
        <?php elseif ($phase['status'] === 'wartet'): ?>
            <p class="leise">Finalrunde: Teilnehmer stehen nach der Vorrunde fest – Liste dann erneut drucken (bei Technikausfall: Aufsteiger von Hand im Baum eintragen).</p>
            <?php continue; ?>
        <?php endif; ?>

        <?php if ($phase['art'] === 'auftritte'): ?>
            <?= $this->teil('druck/phase', ['phase' => $phase, 'klasse' => $klasse]) ?>
            <p class="klein">Kata: <?= $phase['auftritte'][0]['kata_liste'] ?? '' ? e($phase['auftritte'][0]['kata_liste']) : 'frei (Tokui)' ?><?= $kr >= 5 ? ' · höchste und niedrigste Wertung werden gestrichen' : '' ?></p>
            <?php continue; ?>
        <?php endif; ?>

        <?php $teamKaempfe = \Wettkampf\Durchfuehrung\ErgebnisService::teamKaempfe($klasse['optionen'] ?? null); ?>
        <?php foreach ($phase['begegnungen'] as $b):
            $istTeam = $klasse['disziplin'] === 'kumite_team' && $b['system'] === 'kumite';
            $katas = $b['kata_liste'] ? 'Kata (' . $b['kata_liste'] . ')' : 'Kata (frei)';
            ?>
            <div class="kampf">
                <div class="kampfkopf">
                    <span>Kampf <?= e($b['nr']) ?> · <?= e($b['runden_name']) ?></span>
                    <span><?= e(wertung_text($b['system'])) ?><?= $b['kumite_form'] ? ' · ' . e(kumite_form_text($b['kumite_form'])) : '' ?> · <?= $kr ?> KR</span>
                </div>
                <table>
                    <?php foreach (['aka' => 'AKA', 'shiro' => 'SHIRO'] as $seite => $kennung): ?>
                        <tr class="seite-<?= $seite ?>">
                            <td style="width:16mm"><span class="<?= $seite ?>-kennung"><?= $kennung ?></span></td>
                            <td style="width:62mm">
                                <?php if ($b["{$seite}_name"] !== ''): ?>
                                    <span class="name"><?= e($b["{$seite}_name"]) ?></span><br><span class="klein"><?= e($b["{$seite}_verein"]) ?></span>
                                <?php else: ?>
                                    <span class="leise"><?= e($b["{$seite}_platzhalter"]) ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if ($istKata): ?>
                                <td><span class="klein"><?= e($katas) ?></span><br><?= e($b["kata_$seite"] ?? '') ?>&nbsp;</td>
                            <?php endif; ?>
                            <?php if ($istTeam): ?>
                                <td class="klein">Einzelsiege: ____ &nbsp; Ippon-Siege: ____ &nbsp; Wazaari der Sieger: ____</td>
                            <?php elseif ($b['system'] === 'flaggen'): ?>
                                <td style="width:30mm" class="klein">Flaggen<br>&nbsp;</td>
                            <?php elseif ($b['system'] === 'punkte'): ?>
                                <?php for ($i = 1; $i <= $kr; $i++): ?><td style="width:11mm" class="klein">KR<?= $i ?><br>&nbsp;</td><?php endfor; ?>
                                <td style="width:15mm" class="klein">Summe<br>&nbsp;</td>
                            <?php else: ?>
                                <td style="width:46mm" class="klein">Wertung <span class="symbol">◯</span> <span class="symbol">△</span><br>&nbsp;</td>
                                <td style="width:30mm" class="klein">Strafen<br>&nbsp;</td>
                            <?php endif; ?>
                            <td style="width:16mm" class="klein">Sieger<br><span class="kasten"></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($istTeam): ?>
                        <tr><td colspan="6" class="klein">
                            Einzelkämpfe (◯/△ je Seite):
                            <?php for ($i = 1; $i <= $teamKaempfe; $i++): ?><?= $i ?>. ________ : ________ &nbsp; <?php endfor; ?>
                            Stichkampf: ________ : ________
                        </td></tr>
                    <?php endif; ?>
                    <tr><td colspan="<?= 3 + ($istKata ? 1 : 0) + ($b['system'] === 'punkte' ? $kr : (in_array($b['system'], ['kumite', 'sanbon'], true) && !$istTeam ? 2 : 0)) ?>" class="klein">
                        Entscheidung: <?= $entscheidungen ?>
                        <?= in_array($b['system'], ['kumite', 'sanbon'], true) && !$istTeam ? ' &nbsp; <span class="kasten"></span>Saishiai' : '' ?>
                        <?= $b['system'] !== 'flaggen' ? ' &nbsp; Hantei-Flaggen A ___ / S ___ / unentsch. ___' : ' &nbsp; Unentschieden-Flaggen ___' ?>
                        <?php if ($b['status'] === 'beendet'): ?>
                            &nbsp; <strong>Erfasst: <?= e((int) $b['sieger_start_id'] === (int) $b['aka_start_id'] ? $b['aka_name'] : $b['shiro_name']) ?> (<?= e($b['entscheidung']) ?>)</strong>
                        <?php endif; ?>
                    </td></tr>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
