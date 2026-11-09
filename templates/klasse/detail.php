<?php
/**
 * @var array<string, mixed> $turnier
 * @var array<string, mixed> $klasse
 * @var list<array<string, mixed>> $starter
 * @var list<array<string, mixed>> $regeln
 * @var list<array<string, mixed>> $quellen
 * @var array<string, mixed>|null $ziel
 * @var list<array{id: int, anzeige: string}> $auswahl
 * @var list<array<string, mixed>> $andere
 * @var bool $istTeam
 * @var array<string, mixed>|null $ansicht  Lesemodell nach der Auslosung
 * @var bool $hatErgebnisse
 */
use Wettkampf\Domain\Graduierung;

$tid = $turnier['id'];
$kid = $klasse['id'];
$this->layout('layout', ['titel' => 'Klasse ' . $klasse['code'], 'turnier' => $turnier, 'aktiv' => 'klassen']);
$offen = $klasse['status'] === 'offen';
$aktiveStarter = array_values(array_filter($starter, static fn ($s) => $s['status'] !== 'zurueckgezogen'));
$modusText = ['auto' => 'K.-o.-System mit Kampf um Platz 3', 'pools' => 'Pools + Finalrunde', 'punkte' => 'Punkte-Vorrunde + Finale', 'jgj' => 'Jeder gegen jeden'];
?>
<p class="obertitel"><?= e(disziplin_text($klasse['disziplin'])) ?><?= $klasse['kumite_form'] ? ' · ' . e(kumite_form_text($klasse['kumite_form'])) : '' ?></p>
<div class="titelzeile">
    <h1><?= e($klasse['code']) ?> · <?= e($klasse['bezeichnung']) ?></h1>
    <span class="tag info"><?= e(status_text($klasse['status'])) ?></span>
    <a class="knopf" href="<?= e(url("turnier/$tid/klasse/$kid/bearbeiten")) ?>">Bearbeiten</a>
</div>

<?php if ($ziel): ?>
    <div class="meldung meldung-hinweis">
        Diese Klasse ist in <a href="<?= e(url("turnier/$tid/klasse/{$ziel['id']}")) ?>"><strong><?= e($ziel['code']) ?></strong></a> zusammengelegt.
        <form method="post" action="<?= e(url("turnier/$tid/klasse/$kid/aufheben")) ?>" class="inline">
            <?= csrf_feld() ?><button class="link" type="submit">Zusammenlegung aufheben</button>
        </form>
    </div>
<?php endif; ?>

<div class="karte">
    <div class="titelzeile">
        <h2 style="margin:0;margin-right:auto">Auslosung &amp; Ablauf <?= info('auslosung') ?></h2>
        <?php if ($offen && $aktiveStarter): ?>
            <form method="post" action="<?= e(url("turnier/$tid/klasse/$kid/auslosen")) ?>" class="knopfleiste">
                <?= csrf_feld() ?>
                <input name="seed" placeholder="Seed (optional, zum Reproduzieren)" style="max-width:240px" pattern="[A-Za-z0-9_\-]{0,64}">
                <button class="knopf primaer" type="submit">Jetzt auslosen</button>
            </form>
        <?php elseif ($ansicht && !$hatErgebnisse): ?>
            <form method="post" action="<?= e(url("turnier/$tid/klasse/$kid/auslosen")) ?>" class="inline" data-bestaetigen="Klasse neu auslosen? Der bisherige Baum wird ersetzt.">
                <?= csrf_feld() ?><button class="knopf" type="submit">Neu auslosen</button>
            </form>
            <form method="post" action="<?= e(url("turnier/$tid/klasse/$kid/zuruecknehmen")) ?>" class="inline" data-bestaetigen="Auslosung zurücknehmen? Die Klasse wird wieder offen.">
                <?= csrf_feld() ?><button class="knopf" type="submit">Zurücknehmen</button>
            </form>
        <?php endif; ?>
        <?php if ($ansicht): ?>
            <a class="knopf" href="<?= e(url("turnier/$tid/klasse/$kid/protokoll")) ?>">Protokoll</a>
            <a class="knopf" href="<?= e(url("turnier/$tid/druck/klasse/$kid")) ?>" target="_blank">Drucken</a>
        <?php endif; ?>
    </div>
    <?php if (!$ansicht): ?>
        <p class="leise"><?= $offen ? 'Noch nicht ausgelost.' : 'Zusammengelegt – siehe Zielklasse.' ?></p>
    <?php else: ?>
        <p class="klein">
            Ausgelost am <?= e(datum_de(substr($ansicht['auslosung']['erstellt_am'] ?? '', 0, 10))) ?> <?= e(substr($ansicht['auslosung']['erstellt_am'] ?? '', 11, 5)) ?>
            von <?= e($ansicht['auslosung']['erstellt_von'] ?? '') ?> · Seed <code><?= e($ansicht['auslosung']['seed'] ?? '') ?></code>
            <?= $hatErgebnisse ? ' · Ergebnisse vorhanden – Neuauslosung gesperrt' : '' ?>
        </p>
        <?php foreach ($ansicht['phasen'] as $phase): ?>
            <h3><?= e($phase['bezeichnung']) ?>
                <span class="tag"><?= e(status_text($phase['status'])) ?></span>
                <?= $phase['tatami'] ? '<span class="tag info">' . e($phase['tatami']) . '</span>' : '' ?>
                <span class="klein"><?= e(wertung_text($phase['wertung'])) ?><?= $phase['aufsteiger'] && $phase['typ'] === 'pool' ? " · {$phase['aufsteiger']} Aufsteiger" : '' ?></span>
            </h3>
            <?php if ($phase['status'] === 'wartet'): ?>
                <p class="leise">Wird nach Abschluss der Vorrunde automatisch befüllt.</p>
            <?php else: ?>
                <?= $this->teil('teile/phase', ['phase' => $phase, 'eingabeUrl' => "turnier/$tid/begegnung/"]) ?>
            <?php endif; ?>
            <?php
            // Stichentscheid bei Gleichstand (jeder gegen jeden bzw. Punkte)
            $gleich = array_values(array_filter($phase['tabelle'] ?? $phase['rangliste'] ?? [], static fn ($z) => $z['gleichstand']));
            if ($gleich && $klasse['status'] !== 'bestaetigt'):
                $art = isset($phase['rangliste']) ? 'auftritt' : 'start';
                ?>
                <form method="post" action="<?= e(url("turnier/$tid/klasse/$kid/stichentscheid")) ?>" class="meldung meldung-hinweis formular">
                    <?= csrf_feld() ?>
                    <input type="hidden" name="art" value="<?= $art ?>">
                    <strong>Gleichstand – Stichentscheid der Wettkampfleitung</strong>
                    <span class="hilfe">Reihenfolge nach Entscheidungskampf bzw. Kampfrichterentscheid eintragen (1 = vorne).</span>
                    <?php foreach ($gleich as $z): ?>
                        <label class="check">
                            <input type="number" min="1" max="20" style="width:5rem" name="rang[<?= $art === 'auftritt' ? $z['auftritt_id'] : $z['start_id'] ?>]" value="<?= e($z['stichentscheid'] ?? '') ?>">
                            <?= e($z['name']) ?> <span class="leise">(<?= e($z['verein']) ?>)</span>
                        </label>
                    <?php endforeach; ?>
                    <div><button class="knopf" type="submit">Stichentscheid speichern</button></div>
                </form>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($ansicht['platzierungen']): ?>
            <h3>Platzierungen <?= $klasse['status'] === 'bestaetigt' ? '<span class="tag ok">bestätigt</span>' : '<span class="tag warn">vorläufig</span>' ?>
                <?php if (!empty($klasse['tisch_abschluss_am']) && $klasse['tisch_abschluss_am'] > '2000-01-01 00:00:00'): ?>
                    <span class="tag info" title="<?= e($klasse['tisch_abschluss_von'] ?? '') ?>">vom Tisch abgeschlossen <?= e(substr($klasse['tisch_abschluss_am'], 11, 5)) ?></span>
                <?php elseif (in_array($klasse['status'], ['beendet', 'bestaetigt'], true) && empty($klasse['tisch_abschluss_am'])): ?>
                    <span class="tag">Tisch-Abschluss offen</span>
                <?php endif; ?></h3>
            <table class="tabelle podest" style="max-width:600px">
                <?php foreach ($ansicht['platzierungen'] as $pl): ?>
                    <tr><td class="platz"><?= $pl['platz'] ?>.</td><td><?= e($pl['name']) ?></td><td class="leise"><?= e($pl['verein']) ?></td></tr>
                <?php endforeach; ?>
            </table>
            <div class="knopfleiste" style="margin-top:.5rem">
                <?php if ($klasse['status'] === 'beendet'): ?>
                    <form method="post" action="<?= e(url("turnier/$tid/klasse/$kid/bestaetigen")) ?>"><?= csrf_feld() ?><input type="hidden" name="zurueck" value="klasse"><button class="knopf primaer" type="submit">Platzierungen bestätigen</button></form>
                <?php elseif ($klasse['status'] === 'bestaetigt'): ?>
                    <p class="knopfleiste">
                        <a class="knopf primaer" href="<?= e(url("turnier/$tid/druck/urkunden", ['klasse' => $kid])) ?>" target="_blank">🏅 Urkunden drucken</a>
                        <a class="klein" href="<?= e(url("turnier/$tid/druck/urkunden", ['klasse' => $kid, 'vordruck' => 1])) ?>" target="_blank">nur Text (Vordruck)</a>
                    </p>
                    <form method="post" action="<?= e(url("turnier/$tid/klasse/$kid/bestaetigung-aufheben")) ?>" data-bestaetigen="Bestätigung aufheben? Danach sind Korrekturen wieder möglich."><?= csrf_feld() ?><button class="knopf" type="submit">Bestätigung aufheben</button></form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="zwei-spalten">
    <div class="karte">
        <h2>Regeln <?= info('abweichungen') ?></h2>
        <table class="tabelle">
            <tr><th>Zulassung</th><td><?= e(geschlecht_text($klasse['geschlecht'])) ?>, <?= e($klasse['alter_min'] ?? '…') ?>–<?= e($klasse['alter_max'] ?? '…') ?> Jahre, <?= e(Graduierung::bereichText($klasse['grad_min'] === null ? null : (int) $klasse['grad_min'], $klasse['grad_max'] === null ? null : (int) $klasse['grad_max'])) ?></td></tr>
            <tr><th>Modus</th><td><?= e($modusText[$klasse['modus']] ?? $klasse['modus']) ?><?= $klasse['modus'] === 'pools' ? " ({$klasse['anzahl_pools']} Pools, je {$klasse['aufsteiger_pro_pool']} Aufsteiger, Finalrunde " . ($klasse['finale_art'] === 'punkte' ? 'Punkte' : 'K.-o.') . ')' : '' ?><?= $klasse['modus'] === 'punkte' ? " ({$klasse['finalisten']} Finalisten)" : '' ?></td></tr>
            <tr><th>Wertung</th><td><?= e(wertung_text($klasse['wertung'])) ?><?= $klasse['wertung_finale'] ? ' · Finale: ' . e(wertung_text($klasse['wertung_finale'])) : '' ?> · <?= e($klasse['kampfrichter']) ?> Kampfrichter</td></tr>
            <?php if ($klasse['erlaubte_kata']): ?><tr><th>Kata (Vorrunde)</th><td><?= e($klasse['erlaubte_kata']) ?></td></tr><?php endif; ?>
            <?php foreach ($regeln as $r): ?>
                <tr><th>ab <?= e(['letzte8' => 'letzte 8', 'letzte4' => 'letzte 4', 'finale' => 'Finale'][$r['ab_runde']] ?? $r['ab_runde']) ?></th>
                    <td><?= e(implode(' · ', array_filter([
                        $r['kumite_form'] ? kumite_form_text($r['kumite_form']) : null,
                        $r['erlaubte_kata'] !== null ? ('Kata: ' . ($r['erlaubte_kata'] === '' ? 'frei (Tokui)' : $r['erlaubte_kata'])) : null,
                        $r['wertung'] ? wertung_text($r['wertung']) : null,
                    ]))) ?></td></tr>
            <?php endforeach; ?>
            <tr><th>Optionen</th><td><?= $klasse['dojo_trennung'] ? 'Dojo-Trennung' : 'keine Dojo-Trennung' ?><?= $klasse['gleiche_flaeche'] ? ' · bleibt bis zum Finale auf einer Tatami (Kata-Pools ab 9 Startern getrennt)' : '' ?></td></tr>
        </table>
    </div>

    <div class="karte">
        <h2>Zusammenlegung <?= info('zusammenlegung') ?></h2>
        <?php if ($quellen): ?>
            <p>Enthält:</p>
            <ul>
                <?php foreach ($quellen as $q): ?>
                    <li><a href="<?= e(url("turnier/$tid/klasse/{$q['id']}")) ?>"><strong><?= e($q['code']) ?></strong></a> <?= e($q['bezeichnung']) ?>
                        <?php if ($q['grund']): ?><br><span class="klein">Grund: <?= e($q['grund']) ?></span><?php endif; ?>
                        <?php if ($q['zustimmung']): ?><br><span class="klein">Zustimmung: <?= e($q['zustimmung']) ?></span><?php endif; ?>
                        <?php if ($offen): ?>
                            <form method="post" action="<?= e(url("turnier/$tid/klasse/{$q['id']}/aufheben")) ?>" class="inline"><?= csrf_feld() ?><button class="link" type="submit">aufheben</button></form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($offen && $andere): ?>
            <form method="post" action="<?= e(url("turnier/$tid/klassen/zusammenlegen")) ?>" class="formular">
                <?= csrf_feld() ?>
                <input type="hidden" name="ziel" value="<?= $kid ?>">
                <p class="hilfe">Andere Klassen in <?= e($klasse['code']) ?> aufnehmen. Die Regeln von <?= e($klasse['code']) ?> gelten dann für alle.</p>
                <div style="max-height:180px;overflow:auto">
                    <?php foreach ($andere as $a): ?>
                        <label class="check"><input type="checkbox" name="quellen[]" value="<?= $a['id'] ?>"> <strong><?= e($a['code']) ?></strong> <?= e($a['bezeichnung']) ?> <span class="leise">(<?= (int) $a['anzahl'] ?>)</span></label>
                    <?php endforeach; ?>
                </div>
                <label>Grund <input name="grund" placeholder="z. B. zu wenige Meldungen"></label>
                <label>Zustimmung dokumentiert <input name="zustimmung" placeholder="z. B. Trainer und Erziehungsberechtigte mündlich am 15.02."></label>
                <div><button class="knopf" type="submit">Zusammenlegen</button></div>
            </form>
        <?php elseif (!$quellen): ?>
            <p class="leise">Keine Zusammenlegung.</p>
        <?php endif; ?>
    </div>
</div>

<div class="karte">
    <div class="titelzeile">
        <h2 style="margin:0;margin-right:auto">Starter (<?= count($aktiveStarter) ?>)</h2>
        <?php if ($offen): ?>
            <form method="post" action="<?= e(url("turnier/$tid/klasse/$kid/starter")) ?>" class="knopfleiste">
                <?= csrf_feld() ?>
                <select name="wer" required style="max-width:340px">
                    <option value=""><?= $istTeam ? 'Team' : 'Teilnehmer' ?> manuell hinzufügen …</option>
                    <?php foreach ($auswahl as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['anzeige']) ?></option><?php endforeach; ?>
                </select>
                <button class="knopf" type="submit">Hinzufügen</button>
            </form>
        <?php endif; ?>
    </div>
    <table class="tabelle">
        <thead><tr><th>Name</th><th>Verein</th><?php if (!$istTeam): ?><th>Alter</th><th>Grad</th><?php endif; ?><th>Herkunft</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($starter as $s): ?>
            <tr<?= $s['status'] === 'zurueckgezogen' ? ' class="inaktiv"' : '' ?>>
                <td><?= e($s['name']) ?>
                    <?= $s['manuell'] ? ' <span class="tag info">manuell</span>' : '' ?>
                    <?= ($s['teilnahme_status'] ?? '') === 'gesperrt' ? ' <span class="tag fehler">gesperrt</span>' : '' ?></td>
                <td><?= e($s['verein']) ?></td>
                <?php if (!$istTeam): ?><td><?= e($s['alter']) ?></td><td class="nowrap"><?= e(grad($s['graduierung'] === null ? null : (int) $s['graduierung'])) ?></td><?php endif; ?>
                <td><?= $s['herkunft_code'] ? e($s['herkunft_code']) : '' ?></td>
                <td class="rechts">
                    <?php if ($offen): ?>
                        <form method="post" action="<?= e(url("turnier/$tid/klasse/$kid/starter/{$s['id']}/entfernen")) ?>" class="inline">
                            <?= csrf_feld() ?><button class="knopf klein" type="submit">Entfernen</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$starter): ?><tr><td colspan="6" class="leise">Keine Starter.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($offen && !$aktiveStarter && !$quellen): ?>
    <form method="post" action="<?= e(url("turnier/$tid/klasse/$kid/loeschen")) ?>" data-bestaetigen="Klasse löschen?">
        <?= csrf_feld() ?><button class="knopf gefahr" type="submit">Klasse löschen</button>
    </form>
<?php endif; ?>
