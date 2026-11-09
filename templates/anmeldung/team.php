<?php
/**
 * Ein Team im Anmeldeformular (auch als Vorlage mit $i = '__I__').
 * @var int|string $i
 * @var array<string, mixed> $team
 * @var array<int|string, string> $namen Schlüssel der Teilnehmerzeile → Name
 * @var array<string, mixed> $einstellungen
 */
use Wettkampf\Meldung\AnmeldungService;

$mitglieder = array_values((array) ($team['mitglieder'] ?? []));
$auswahl = static function (string $name, string $gewaehlt, string $leer) use ($namen): string {
    $html = '<select name="' . $name . '" data-mitglied><option value="">' . $leer . '</option>';
    foreach ($namen as $k => $text) {
        $html .= '<option value="' . e($k) . '"' . sel((string) $k, $gewaehlt) . '>' . e($text) . '</option>';
    }
    return $html . '</select>';
};
?>
<fieldset class="team" data-team>
    <legend>Team <span class="team-preis klein" data-team-preis><?= isset($einstellungen['preis_' . ($team['disziplin'] ?? '')])
        ? '· Startgeld ' . e(AnmeldungService::euro($einstellungen['preis_' . $team['disziplin']])) : '' ?></span></legend>
    <div class="zeile">
        <label class="feld">Teamname <input name="teams[<?= $i ?>][name]" value="<?= e($team['name'] ?? '') ?>" maxlength="200" placeholder="z. B. Dojo 1"></label>
        <label class="feld">Disziplin
            <select name="teams[<?= $i ?>][disziplin]">
                <option value="">bitte wählen</option>
                <option value="kata_team"<?= sel('kata_team', $team['disziplin'] ?? '') ?>>Kata-Team</option>
                <option value="kumite_team"<?= sel('kumite_team', $team['disziplin'] ?? '') ?>>Kumite-Team</option>
            </select>
        </label>
    </div>
    <div class="zeile">
        <?php for ($m = 0; $m < 3; $m++): ?>
            <label class="feld">Mitglied <?= $m + 1 ?> <?= $auswahl("teams[$i][mitglieder][]", (string) ($mitglieder[$m] ?? ''), '– wählen –') ?></label>
        <?php endfor; ?>
        <label class="feld">Ersatz <span class="hilfe">optional</span> <?= $auswahl("teams[$i][ersatz]", (string) ($team['ersatz'] ?? ''), '– kein Ersatz –') ?></label>
    </div>
    <button type="button" class="knopf klein gefahr" data-aktion="team-weg">Team entfernen</button>
</fieldset>
