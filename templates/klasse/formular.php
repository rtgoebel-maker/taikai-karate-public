<?php
/**
 * @var array<string, mixed> $turnier
 * @var array<string, mixed>|null $klasse
 * @var array<string, mixed> $werte
 * @var list<array<string, mixed>> $regeln
 */
use Wettkampf\Domain\Graduierung;

$tid = $turnier['id'];
$neu = $klasse === null;
$this->layout('layout', ['titel' => $neu ? 'Neue Klasse' : 'Klasse ' . $klasse['code'], 'turnier' => $turnier, 'aktiv' => 'klassen']);
$opt = json_decode((string) ($werte['optionen'] ?? ''), true) ?: [];
$ausgelost = !$neu && !in_array($werte['status'], ['offen', 'zusammengelegt'], true);
$regeln = array_pad($regeln, 2, ['ab_runde' => '', 'kumite_form' => '', 'erlaubte_kata' => '', 'wertung' => '']);
?>
<h1><?= $neu ? 'Neue Klasse' : 'Klasse ' . e($klasse['code']) . ' bearbeiten' ?></h1>
<?php if ($ausgelost): ?>
    <div class="meldung meldung-hinweis">Die Klasse ist bereits ausgelost. Modus und Wertung sind gesperrt, bis die Auslosung zurückgenommen wird.</div>
<?php endif; ?>
<form method="post" action="<?= e(url("turnier/$tid/klassen")) ?>" class="formular">
    <?= csrf_feld() ?>
    <?php if (!$neu): ?><input type="hidden" name="klasse_id" value="<?= $klasse['id'] ?>"><?php endif; ?>
    <input type="hidden" name="sortierung" value="<?= e($werte['sortierung']) ?>">
    <div class="zwei-spalten">
        <fieldset>
            <legend>Zulassung <?= info('zulassung') ?></legend>
            <div class="formular">
                <div class="zeile">
                    <label>Code <input name="code" value="<?= e($werte['code']) ?>" required placeholder="z. B. C1"></label>
                    <label>Disziplin
                        <select name="disziplin">
                            <?php foreach (['kata', 'kumite', 'kata_team', 'kumite_team'] as $d): ?>
                                <option value="<?= $d ?>"<?= sel($d, $werte['disziplin']) ?>><?= e(disziplin_text($d)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label>Bezeichnung <input name="bezeichnung" value="<?= e($werte['bezeichnung']) ?>" placeholder="z. B. Kata Mädchen 12–14 Jahre"></label>
                <div class="zeile">
                    <label>Geschlecht
                        <select name="geschlecht">
                            <option value="w"<?= sel('w', $werte['geschlecht']) ?>>weiblich</option>
                            <option value="m"<?= sel('m', $werte['geschlecht']) ?>>männlich</option>
                            <option value="mixed"<?= sel('mixed', $werte['geschlecht']) ?>>mixed / gemischt</option>
                        </select>
                    </label>
                    <label>Kumite-Form
                        <select name="kumite_form">
                            <option value="">– (Kata)</option>
                            <?php foreach (['kihon_ippon', 'jiyu_ippon', 'jiyu'] as $f): ?>
                                <option value="<?= $f ?>"<?= sel($f, $werte['kumite_form']) ?>><?= e(kumite_form_text($f)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="zeile">
                    <label>Alter von <input type="number" name="alter_min" min="3" max="99" value="<?= e($werte['alter_min']) ?>"></label>
                    <label>Alter bis <input type="number" name="alter_max" min="3" max="99" value="<?= e($werte['alter_max']) ?>"></label>
                </div>
                <div class="zeile">
                    <label>Graduierung von (niedrigster Grad)
                        <select name="grad_min">
                            <option value="">beliebig</option>
                            <?php foreach (Graduierung::auswahl() as $g => $t): ?><option value="<?= $g ?>"<?= sel($g, $werte['grad_min']) ?>><?= e($t) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label>bis (höchster Grad)
                        <select name="grad_max">
                            <option value="">beliebig</option>
                            <?php foreach (Graduierung::auswahl() as $g => $t): ?><option value="<?= $g ?>"<?= sel($g, $werte['grad_max']) ?>><?= e($t) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label>Erlaubte Kata (Vorrunde, durch Komma getrennt; leer = frei)
                    <input name="erlaubte_kata" value="<?= e($werte['erlaubte_kata']) ?>" placeholder="Heian Shodan, Heian Nidan, Heian Sandan">
                </label>
                <label>Exklusiv-Gruppe <span class="hilfe">Klassen mit gleicher Gruppe schließen sich gegenseitig aus (z. B. „D2“ für D2A/D2B).</span>
                    <input name="exklusiv_gruppe" value="<?= e($werte['exklusiv_gruppe']) ?>">
                </label>
            </div>
        </fieldset>

        <fieldset<?= $ausgelost ? ' disabled' : '' ?>>
            <legend>Modus &amp; Wertung <?= info('modus') ?></legend>
            <div class="formular">
                <label>Modus
                    <select name="modus">
                        <option value="auto"<?= sel('auto', $werte['modus']) ?>>K.-o.-System (2 Starter: 1 Kampf · 3: jeder gegen jeden · ab 4: K.-o. mit Kampf um Platz 3)</option>
                        <option value="pools"<?= sel('pools', $werte['modus']) ?>>Pools (K.-o.) + Finalrunde</option>
                        <option value="punkte"<?= sel('punkte', $werte['modus']) ?>>Punkte-Vorrunde + Finale (ab 9 Startern Pools zu höchstens 8)</option>
                        <option value="jgj"<?= sel('jgj', $werte['modus']) ?>>Jeder gegen jeden</option>
                    </select>
                </label>
                <div class="zeile">
                    <label>Wertung Vorrunde
                        <select name="wertung">
                            <?php foreach (\Wettkampf\Durchfuehrung\KlassenService::WERTUNGEN as $w): ?><option value="<?= $w ?>"<?= sel($w, $werte['wertung']) ?>><?= e(wertung_text($w)) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label>Wertung Finale
                        <select name="wertung_finale">
                            <option value="">wie Vorrunde</option>
                            <?php foreach (\Wettkampf\Durchfuehrung\KlassenService::WERTUNGEN as $w): ?><option value="<?= $w ?>"<?= sel($w, $werte['wertung_finale']) ?>><?= e(wertung_text($w)) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="zeile">
                    <label>Pools <span class="hilfe">Richtwert – gewählt wird ±1 so, dass alle Pools gleich groß (möglichst gerade) sind</span> <input type="number" name="anzahl_pools" min="1" max="16" value="<?= e($werte['anzahl_pools']) ?>"></label>
                    <label>Aufsteiger je Pool
                        <select name="aufsteiger_pro_pool"><?php foreach ([1, 2, 4, 8] as $a): ?><option<?= sel($a, $werte['aufsteiger_pro_pool']) ?>><?= $a ?></option><?php endforeach; ?></select>
                    </label>
                    <label>Finalrunde
                        <select name="finale_art">
                            <option value="ko"<?= sel('ko', $werte['finale_art']) ?>>K.-o. (Halbfinale, Platz 3, Finale)</option>
                            <option value="punkte"<?= sel('punkte', $werte['finale_art']) ?>>Punkte (alle Finalisten tragen vor)</option>
                        </select>
                    </label>
                </div>
                <div class="zeile">
                    <label>Finalisten (Punkte-Vorrunde) <span class="hilfe">bei mehreren Pools immer die besten zwei je Pool</span> <input type="number" name="finalisten" min="2" max="16" value="<?= e($werte['finalisten']) ?>"></label>
                    <label>Kampfrichter
                        <select name="kampfrichter"><?php foreach ([3, 5, 7] as $kr): ?><option<?= sel($kr, $werte['kampfrichter']) ?>><?= $kr ?></option><?php endforeach; ?></select>
                    </label>
                    <label>Mindestanzahl Starter <span class="hilfe">darunter findet die Klasse nicht statt (mind. 2)</span>
                        <input type="number" name="min_starter" min="2" max="64" value="<?= e($werte['min_starter'] ?? 2) ?>"></label>
                </div>
                <div class="zeile">
                    <label>Punkte min <input name="punkte_min" inputmode="decimal" value="<?= e($opt['punkte_min'] ?? '0.0') ?>"></label>
                    <label>Punkte max <input name="punkte_max" inputmode="decimal" value="<?= e($opt['punkte_max'] ?? '9.9') ?>"></label>
                    <label>Schritt <input name="punkte_schritt" inputmode="decimal" value="<?= e($opt['punkte_schritt'] ?? '0.1') ?>"></label>
                    <label>Kumite-Team: Einzelkämpfe <span class="hilfe">laut Ausschreibung, ungerade</span>
                        <select name="team_kaempfe"><?php foreach ([3, 5, 7] as $n): ?><option<?= sel($n, $opt['team_kaempfe'] ?? 3) ?>><?= $n ?></option><?php endforeach; ?></select></label>
                </div>
                <p class="hilfe">Kata-Punkte laut Wettkampfordnung 0,0 bis 9,9. „Kumite Shobu Sanbon“ (zwei volle Punkte) z. B. als Wertung im Finale.</p>
                <label class="check"><input type="checkbox" name="streichung" value="1"<?= chk($opt['streichung'] ?? true) ?>> Punkte: höchste und niedrigste Wertung streichen (ab 5 Kampfrichtern)</label>
                <label class="check"><input type="hidden" name="dojo_trennung" value="0"><input type="checkbox" name="dojo_trennung" value="1"<?= chk($werte['dojo_trennung']) ?>> Auslosung: Starter desselben Dojos möglichst spät gegeneinander</label>
                <label class="check"><input type="checkbox" name="gleiche_flaeche" value="1"<?= chk($werte['gleiche_flaeche']) ?>> Klasse bleibt bis zum Finale auf derselben Tatami <span class="hilfe">bei Kata (Punkte) nur mit einem Block – ab 9 Startern laufen die Pools getrennt</span></label>
            </div>
        </fieldset>
    </div>

    <fieldset<?= $ausgelost ? ' disabled' : '' ?>>
        <legend>Abweichungen ab einer Runde <?= info('abweichungen') ?></legend>
        <p class="hilfe">Z. B. Kata-Finale frei (Tokui) oder laut Sportordnung „letzte 8 Kämpfer: Jiyu-Kumite“.</p>
        <?php foreach ($regeln as $i => $r): ?>
            <div class="zeile">
                <label>ab
                    <select name="regeln[<?= $i ?>][ab_runde]">
                        <option value="">–</option>
                        <option value="letzte8"<?= sel('letzte8', $r['ab_runde']) ?>>letzte 8 (Viertelfinale)</option>
                        <option value="letzte4"<?= sel('letzte4', $r['ab_runde']) ?>>letzte 4 (Halbfinale)</option>
                        <option value="finale"<?= sel('finale', $r['ab_runde']) ?>>Finale / Finalrunde</option>
                    </select>
                </label>
                <label>Kumite-Form
                    <select name="regeln[<?= $i ?>][kumite_form]">
                        <option value="">unverändert</option>
                        <?php foreach (['kihon_ippon', 'jiyu_ippon', 'jiyu'] as $f): ?><option value="<?= $f ?>"<?= sel($f, $r['kumite_form']) ?>><?= e(kumite_form_text($f)) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Erlaubte Kata (leer = frei)
                    <input name="regeln[<?= $i ?>][erlaubte_kata]" value="<?= e($r['erlaubte_kata']) ?>">
                </label>
                <label>Wertung
                    <select name="regeln[<?= $i ?>][wertung]">
                        <option value="">unverändert</option>
                        <?php foreach (\Wettkampf\Durchfuehrung\KlassenService::WERTUNGEN as $w): ?><option value="<?= $w ?>"<?= sel($w, $r['wertung']) ?>><?= e(wertung_text($w)) ?></option><?php endforeach; ?>
                    </select>
                </label>
            </div>
        <?php endforeach; ?>
    </fieldset>
    <?php if ($ausgelost): ?>
        <?php // gesperrte Felder werden vom Browser nicht gesendet – Werte als hidden mitschicken ?>
        <?php foreach (['modus', 'wertung', 'wertung_finale', 'anzahl_pools', 'aufsteiger_pro_pool', 'finale_art', 'finalisten', 'kampfrichter', 'min_starter', 'dojo_trennung', 'gleiche_flaeche'] as $f): ?>
            <input type="hidden" name="<?= $f ?>" value="<?= e($werte[$f]) ?>">
        <?php endforeach; ?>
        <?php foreach (['punkte_min', 'punkte_max', 'punkte_schritt', 'team_kaempfe'] as $f): ?>
            <input type="hidden" name="<?= $f ?>" value="<?= e($opt[$f] ?? '') ?>">
        <?php endforeach; ?>
        <?php if ($opt['streichung'] ?? true): ?><input type="hidden" name="streichung" value="1"><?php endif; ?>
    <?php endif; ?>
    <div class="knopfleiste">
        <button class="knopf primaer" type="submit">Speichern</button>
        <a class="knopf" href="<?= e(url($neu ? "turnier/$tid/klassen" : "turnier/$tid/klasse/{$klasse['id']}")) ?>">Abbrechen</a>
    </div>
</form>
