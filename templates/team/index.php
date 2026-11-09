<?php
/**
 * @var array<string, mixed> $turnier
 * @var list<array<string, mixed>> $teams
 * @var array<int, list<array<string, mixed>>> $mitglieder
 * @var list<array<string, mixed>> $teilnehmer
 * @var list<array<string, mixed>> $vereine
 */
$this->layout('layout', ['titel' => 'Teams', 'turnier' => $turnier, 'aktiv' => 'teams']);
$tid = $turnier['id'];
?>
<h1>Teams <?= info('teams') ?></h1>
<p class="leise">Kata-Teams starten in der Altersklasse des ältesten Mitglieds. Nach der 2:1-Regel darf ein Mitglied für einen anderen Verein starten als im Einzel – dazu beim Hinzufügen den Verein wählen.</p>
<div class="zwei-spalten">
    <div>
        <?php if ($teams): ?>
            <div class="karte knopfleiste team-suche">
                <input type="search" id="team-suche" placeholder="Suchen: Team, Verein, Mitglied oder Klasse …" aria-label="Teams durchsuchen" autocomplete="off">
                <select id="team-disziplin" aria-label="Disziplin" style="max-width:180px">
                    <option value="">Alle Teams</option>
                    <option value="kata_team">Kata-Teams</option>
                    <option value="kumite_team">Kumite-Teams</option>
                </select>
                <span class="klein" id="team-treffer"><?= count($teams) ?> Teams</span>
            </div>
        <?php endif; ?>
        <?php foreach ($teams as $team):
            $suchtext = mb_strtolower(implode(' ', array_merge(
                [$team['name'], $team['verein'] ?? '', str_replace(',', ' ', (string) $team['klassen']), disziplin_text($team['disziplin'])],
                array_map(static fn ($m) => $m['vorname'] . ' ' . $m['nachname'] . ' ' . $m['verein'], $mitglieder[$team['id']] ?? []),
            )));
            ?>
            <div class="karte" data-team data-disziplin="<?= e($team['disziplin']) ?>" data-suche="<?= e($suchtext) ?>">
                <div class="titelzeile">
                    <h3 style="margin:0;margin-right:auto"><?= e($team['name']) ?></h3>
                    <span class="tag info"><?= e(disziplin_text($team['disziplin'])) ?></span>
                    <?php foreach (array_filter(explode(',', (string) $team['klassen'])) as $code): ?>
                        <span class="tag ok"><?= e($code) ?></span>
                    <?php endforeach; ?>
                    <?php if (!$team['klassen']): ?><span class="tag warn">keine Klasse</span><?php endif; ?>
                </div>
                <p class="klein">Verein: <?= e($team['verein'] ?? '–') ?></p>
                <table class="tabelle">
                    <tbody>
                    <?php foreach ($mitglieder[$team['id']] ?? [] as $m): ?>
                        <tr>
                            <td><?= e($m['nachname'] . ', ' . $m['vorname']) ?></td>
                            <td><?= e(grad((int) $m['graduierung'])) ?></td>
                            <td><?= e($m['verein']) ?><?= (int) $m['verein_id'] !== (int) $team['verein_id'] ? ' <span class="tag warn">2:1</span>' : '' ?></td>
                            <td><?= $m['rolle'] === 'ersatz' ? '<span class="tag">Ersatz</span>' : '' ?></td>
                            <td class="rechts">
                                <form method="post" action="<?= e(url("turnier/$tid/team/{$team['id']}/mitglied/{$m['id']}/entfernen")) ?>" class="inline">
                                    <?= csrf_feld() ?><button class="knopf klein" type="submit">Entfernen</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <form method="post" action="<?= e(url("turnier/$tid/team/{$team['id']}/mitglied")) ?>" class="knopfleiste" style="margin-top:.5rem">
                    <?= csrf_feld() ?>
                    <select name="teilnahme_id" required style="max-width:280px">
                        <option value="">Mitglied wählen …</option>
                        <?php foreach ($teilnehmer as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= e($t['nachname'] . ', ' . $t['vorname'] . ' (' . ($t['verein'] ?? '–') . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="rolle" style="max-width:120px"><option value="stamm">Stamm</option><option value="ersatz">Ersatz</option></select>
                    <select name="verein_id" style="max-width:220px" title="Verein, für den das Mitglied im Team startet">
                        <option value="">startet für Heimatverein</option>
                        <?php foreach ($vereine as $v): ?><option value="<?= $v['id'] ?>"><?= e($v['name']) ?></option><?php endforeach; ?>
                    </select>
                    <button class="knopf klein" type="submit">Hinzufügen</button>
                </form>
                <form method="post" action="<?= e(url("turnier/$tid/team/{$team['id']}/loeschen")) ?>" data-bestaetigen="Team löschen?" style="margin-top:.5rem">
                    <?= csrf_feld() ?><button class="link" type="submit">Team löschen</button>
                </form>
            </div>
        <?php endforeach; ?>
        <div class="karte leer" id="team-keine" hidden>Kein Team passt zur Suche.</div>
        <?php if (!$teams): ?><div class="karte leer">Noch keine Teams. Teams werden auch beim Import über die Spalten „Kata Team“/„Kumite Team“ angelegt.</div><?php endif; ?>
    </div>
    <form method="post" action="<?= e(url("turnier/$tid/teams")) ?>" class="karte formular" style="align-self:start">
        <?= csrf_feld() ?>
        <h2>Neues Team</h2>
        <label>Name <input name="name" required placeholder="z. B. Karate Dojo Musterstadt 1"></label>
        <label>Disziplin
            <select name="disziplin"><option value="kata_team">Kata-Team</option><option value="kumite_team">Kumite-Team</option></select>
        </label>
        <label>Verein
            <select name="verein_id"><option value="">–</option><?php foreach ($vereine as $v): ?><option value="<?= $v['id'] ?>"><?= e($v['name']) ?></option><?php endforeach; ?></select>
        </label>
        <div><button class="knopf primaer" type="submit">Anlegen</button></div>
    </form>
</div>
<script src="<?= e(asset('team.js')) ?>" defer></script>
