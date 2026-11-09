<?php
/**
 * Ergebniseingabe einer Begegnung (PC der Wettkampfleitung).
 * @var array<string, mixed> $turnier
 * @var array<string, mixed> $klasse
 * @var array<string, mixed> $b  Begegnung aus KlassenAnsicht (+ phase)
 */
$tid = $turnier['id'];
$this->layout('layout', ['titel' => 'Kampf ' . $b['nr'], 'turnier' => $turnier, 'aktiv' => 'ergebnisse']);
$w = $b['wertung_daten'];
$kr = (int) $klasse['kampfrichter'];
$istKata = str_starts_with($klasse['disziplin'], 'kata');
$istTeamKumite = $klasse['disziplin'] === 'kumite_team' && $b['system'] === 'kumite';
$istKumite = in_array($b['system'], ['kumite', 'sanbon'], true) && !$istTeamKumite;
$teamKaempfe = \Wettkampf\Durchfuehrung\ErgebnisService::teamKaempfe($klasse['optionen'] ?? null);
$stufen = ['–', 'Keikoku', 'Hansoku Chui', 'Hansoku'];
$katas = array_filter(array_map('trim', explode(',', (string) $b['kata_liste'])));
$beendet = $b['status'] === 'beendet';
?>
<p class="obertitel"><a href="<?= e(url("turnier/$tid/klasse/{$klasse['id']}")) ?>"><?= e($klasse['code']) ?></a> · <?= e($b['phase']['bezeichnung']) ?> · <?= e($b['runden_name']) ?></p>
<div class="titelzeile">
    <h1>Kampf <?= e($b['nr']) ?> <?= info('korrektur') ?></h1>
    <span class="tag info"><?= e(wertung_text($b['system'])) ?></span>
    <?php if ($b['kumite_form']): ?><span class="tag"><?= e(kumite_form_text($b['kumite_form'])) ?></span><?php endif; ?>
    <span class="tag"><?= e(status_text($b['status'])) ?></span>
    <?php if ($b['status'] === 'bereit'): ?>
        <form method="post" action="<?= e(url("turnier/$tid/begegnung/{$b['id']}/starten")) ?>" class="inline"><?= csrf_feld() ?><button class="knopf" type="submit">Als „läuft“ markieren</button></form>
    <?php endif; ?>
</div>

<?php if ($b['aka_start_id'] === null || $b['shiro_start_id'] === null): ?>
    <div class="meldung meldung-hinweis">Die Begegnung ist noch nicht besetzt (<?= e($b['aka_name'] ?: $b['aka_platzhalter']) ?> gegen <?= e($b['shiro_name'] ?: $b['shiro_platzhalter']) ?>).</div>
<?php else: ?>
<form method="post" action="<?= e(url("turnier/$tid/begegnung/{$b['id']}")) ?>" class="formular" id="ergebnis" data-kampfrichter="<?= $kr ?>">
    <?= csrf_feld() ?>
    <input type="hidden" name="version" value="<?= e($b['version']) ?>">
    <?php if ($beendet): ?>
        <div class="meldung meldung-hinweis">Ergebnis liegt vor (Sieger: <strong><?= e((int) $b['sieger_start_id'] === (int) $b['aka_start_id'] ? $b['aka_name'] : $b['shiro_name']) ?></strong>, <?= e($b['entscheidung']) ?>). Speichern überschreibt es (Korrektur, wird protokolliert).</div>
    <?php endif; ?>

    <div class="zwei-spalten">
        <?php foreach (['aka' => 'Aka', 'shiro' => 'Shiro'] as $seite => $titel): ?>
            <fieldset style="border-left:6px solid <?= $seite === 'aka' ? 'var(--aka)' : '#bbb' ?>">
                <legend><?= $titel ?></legend>
                <p style="font-size:1.3rem;margin:.2rem 0"><strong><?= e($b["{$seite}_name"]) ?></strong></p>
                <p class="leise" style="margin-top:0"><?= e($b["{$seite}_verein"]) ?></p>
                <div class="formular">
                    <?php if ($istKata): ?>
                        <label>Vorgetragene Kata
                            <input name="kata_<?= $seite ?>" list="katas" value="<?= e($b["kata_$seite"]) ?>" placeholder="<?= $katas ? 'Auswahl laut Klasse' : 'frei (Tokui)' ?>">
                        </label>
                    <?php endif; ?>
                    <?php if ($b['system'] === 'flaggen' && !$istTeamKumite): ?>
                        <label>Flaggen <span class="hilfe">Kampfrichter, die <?= $seite === 'aka' ? 'Rot (AKA)' : 'Weiß (SHIRO)' ?> zeigen</span>
                            <input type="number" name="flaggen_<?= $seite ?>" min="0" max="<?= $kr ?>" value="<?= e($w['flaggen'][$seite] ?? '') ?>" data-flaggen="flaggen"></label>
                    <?php elseif ($b['system'] === 'punkte'): ?>
                        <div class="zeile">
                            <?php for ($i = 0; $i < $kr; $i++): ?>
                                <label>KR <?= $i + 1 ?> <input name="<?= $seite ?>_werte[]" inputmode="decimal" value="<?= e(isset($w[$seite]['werte'][$i]) ? str_replace('.', ',', (string) $w[$seite]['werte'][$i]) : '') ?>"></label>
                            <?php endfor; ?>
                        </div>
                    <?php elseif ($istKumite): ?>
                        <div class="zeile">
                            <label>Ippon <span class="hilfe">voller Punkt</span><input type="number" name="<?= $seite ?>_ippon" min="0" max="5" value="<?= e($w[$seite]['ippon'] ?? 0) ?>"></label>
                            <label>Wazaari <span class="hilfe">halber Punkt, 2 = Ippon</span><input type="number" name="<?= $seite ?>_wazaari" min="0" max="10" value="<?= e($w[$seite]['wazaari'] ?? 0) ?>"></label>
                        </div>
                        <div class="zeile">
                            <?php foreach (['regel' => 'Strafe Regelverstoß', 'jogai' => 'Jogai', 'mubobi' => 'Mubobi'] as $art => $name): ?>
                                <label><?= e($name) ?>
                                    <select name="<?= $seite ?>_strafen[<?= $art ?>]">
                                        <?php foreach ($stufen as $stufe => $text): ?><option value="<?= $stufe ?>"<?= sel($stufe, $w[$seite]['strafen'][$art] ?? 0) ?>><?= e($text) ?></option><?php endforeach; ?>
                                    </select></label>
                            <?php endforeach; ?>
                        </div>
                        <p class="hilfe">Strafen geben keine Punkte, sie zählen beim Hantei. Hansoku (3. Stufe) disqualifiziert für diesen Kampf – der Gegner gewinnt.</p>
                    <?php endif; ?>
                </div>
            </fieldset>
        <?php endforeach; ?>
    </div>

    <?php if ($b['system'] === 'flaggen' && !$istTeamKumite): ?>
        <div class="zeile">
            <label class="feld">Unentschieden (Hikiwake) <span class="hilfe">ergibt sich aus <?= $kr ?> Kampfrichtern minus Aka- und Shiro-Flaggen</span>
                <input type="number" name="flaggen_unentschieden" readonly tabindex="-1" value="<?= e($w['flaggen']['unentschieden'] ?? '') ?>" style="max-width:8rem"></label>
        </div>
        <p class="hilfe">Es gewinnt die Seite mit mehr Flaggen. Bei gleich vielen Flaggen gibt es keinen Sieger (Verlängerung bzw. erneute Entscheidung).</p>
    <?php endif; ?>

    <?php if ($istTeamKumite): ?>
        <fieldset>
            <legend>Einzelkämpfe</legend>
            <table class="tabelle">
                <thead><tr><th>#</th><th>Sieger</th><th>Hansoku-Sieg</th><th>Aka Ippon</th><th>Aka Wazaari</th><th>Shiro Ippon</th><th>Shiro Wazaari</th></tr></thead>
                <tbody>
                <?php $kaempfe = $w['kaempfe'] ?? []; ?>
                <?php for ($i = 0; $i <= $teamKaempfe; $i++): $k = $kaempfe[$i] ?? []; $stich = $i === $teamKaempfe; ?>
                    <tr>
                        <td><?= $stich ? 'Stichkampf' : $i + 1 ?><?php if ($stich): ?><input type="hidden" name="kaempfe[<?= $i ?>][entscheidungskampf]" value="1"><?php endif; ?></td>
                        <td><select name="kaempfe[<?= $i ?>][sieger]">
                            <option value="">–</option>
                            <option value="aka"<?= sel('aka', $k['sieger'] ?? '') ?>>Aka</option>
                            <option value="shiro"<?= sel('shiro', $k['sieger'] ?? '') ?>>Shiro</option>
                            <?php if (!$stich): ?><option value="hikiwake"<?= sel('hikiwake', $k['sieger'] ?? '') ?>>Hikiwake</option><?php endif; ?>
                        </select></td>
                        <td><?php if (!$stich): ?><input type="checkbox" name="kaempfe[<?= $i ?>][hansoku]" value="1"<?= !empty($k['hansoku']) ? ' checked' : '' ?> aria-label="Sieg durch Hansoku"><?php endif; ?></td>
                        <?php foreach (['aka_ippon', 'aka_wazaari', 'shiro_ippon', 'shiro_wazaari'] as $f): ?>
                            <td><input type="number" min="0" max="10" name="kaempfe[<?= $i ?>][<?= $f ?>]" value="<?= e($k[$f] ?? 0) ?>"></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </fieldset>
    <?php endif; ?>

    <?php if ($istTeamKumite): ?>
        <p class="hilfe"><?= $teamKaempfe ?> Einzelkämpfe – alle austragen, auch wenn der Mannschaftskampf schon entschieden ist. Bei gleich vielen Siegen zählen nur die Wertungen der Sieger: erst Ippon-Siege (Hansoku-Sieg = Ippon), dann ihre Wazaari; danach der Stichkampf.</p>
    <?php endif; ?>

    <?php if ($b['system'] === 'punkte' || $istKumite): ?>
        <fieldset>
            <legend><?= $istKumite ? 'Kampfzeit, Saishiai und Hantei' : 'Hantei (nur bei Gleichstand)' ?></legend>
            <?php if ($istKumite): ?>
                <label class="check"><input type="checkbox" name="zeit_abgelaufen" value="1"<?= !empty($w['zeit_abgelaufen']) || isset($w['flaggen']) ? ' checked' : '' ?>> Kampfzeit abgelaufen <span class="hilfe">ohne <?= $b['system'] === 'sanbon' ? 'zwei volle Punkte' : 'Ippon' ?> gewinnt dann die höhere Wertung (z. B. ein Wazaari), bei Gleichstand Hantei</span></label>
                <label class="check"><input type="checkbox" name="saishiai" value="1"<?= !empty($w['saishiai']) ? ' checked' : '' ?>> Saishiai (Verlängerung) <span class="hilfe">Ippon/Wazaari und Strafen oben sind dann die der Verlängerung</span></label>
                <?php foreach ((array) ($w['hauptkampf'] ?? []) as $s => $h): ?>
                    <?php foreach (['ippon', 'wazaari'] as $f): ?><input type="hidden" name="hauptkampf[<?= e($s) ?>_<?= $f ?>]" value="<?= (int) ($h[$f] ?? 0) ?>"><?php endforeach; ?>
                    <?php foreach ((array) ($h['strafen'] ?? []) as $art => $stufe): ?><input type="hidden" name="hauptkampf[<?= e($s) ?>_strafen][<?= e($art) ?>]" value="<?= (int) $stufe ?>"><?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <p class="hilfe">Kampfrichterentscheid per Flagge, wenn nach Punkten Gleichstand herrscht. Nicht vergebene Flaggen zählen als unentschieden (Hikiwake).</p>
            <div class="zeile">
                <label>Flaggen Aka <input type="number" name="hantei_aka" min="0" max="<?= $kr ?>" value="<?= e($w['flaggen']['aka'] ?? '') ?>" data-flaggen="hantei"></label>
                <label>Flaggen Shiro <input type="number" name="hantei_shiro" min="0" max="<?= $kr ?>" value="<?= e($w['flaggen']['shiro'] ?? '') ?>" data-flaggen="hantei"></label>
                <label>Unentschieden <input type="number" name="hantei_unentschieden" readonly tabindex="-1" value="<?= e($w['flaggen']['unentschieden'] ?? '') ?>"></label>
            </div>
        </fieldset>
    <?php endif; ?>

    <fieldset>
        <legend>Entscheidung</legend>
        <div class="zeile">
            <label>Art
                <select name="entscheidung">
                    <option value="wertung">nach Wertung</option>
                    <option value="kiken"<?= sel('kiken', $b['entscheidung']) ?>>Kiken – nicht angetreten / aufgegeben</option>
                    <option value="hansoku"<?= sel('hansoku', $b['entscheidung']) ?>>Hansoku – Disqualifikation in diesem Kampf</option>
                    <option value="shikkaku"<?= sel('shikkaku', $b['entscheidung']) ?>>Shikkaku – Ausschluss vom ganzen Turnier</option>
                </select>
            </label>
            <label>gegen (verliert)
                <select name="gegen">
                    <option value="">–</option>
                    <option value="aka"<?= sel('aka', $w['gegen'] ?? '') ?>>Aka</option>
                    <option value="shiro"<?= sel('shiro', $w['gegen'] ?? '') ?>>Shiro</option>
                </select>
            </label>
            <label>Grund <input name="grund" value="<?= e($w['grund'] ?? '') ?>"></label>
        </div>
    </fieldset>

    <div class="knopfleiste">
        <button class="knopf primaer" type="submit"><?= $beendet ? 'Korrektur speichern' : 'Ergebnis speichern' ?></button>
        <a class="knopf" href="<?= e(url("turnier/$tid/klasse/{$klasse['id']}")) ?>">Abbrechen</a>
    </div>
</form>
<?php if ($beendet): ?>
    <form method="post" action="<?= e(url("turnier/$tid/begegnung/{$b['id']}/zuruecksetzen")) ?>" style="margin-top:1rem" data-bestaetigen="Ergebnis wirklich zurücksetzen?">
        <?= csrf_feld() ?><button class="knopf gefahr" type="submit">Ergebnis zurücksetzen</button>
    </form>
<?php endif; ?>
<datalist id="katas"><?php foreach ($katas as $k): ?><option value="<?= e($k) ?>"><?php endforeach; ?></datalist>
<script src="<?= e(asset('ergebnis.js')) ?>" defer></script>
<?php endif; ?>
