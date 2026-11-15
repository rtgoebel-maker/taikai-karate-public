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
    <legend><?= te('Team') ?> <span class="team-preis klein" data-team-preis><?= isset($einstellungen['preis_' . ($team['disziplin'] ?? '')])
        ? te('· Startgeld {betrag}', ['betrag' => AnmeldungService::euro($einstellungen['preis_' . $team['disziplin']])]) : '' ?></span></legend>
    <div class="zeile">
        <label class="feld"><?= te('Teamname') ?> <input name="teams[<?= $i ?>][name]" value="<?= e($team['name'] ?? '') ?>" maxlength="200" placeholder="<?= te('z. B. Dojo 1') ?>"></label>
        <label class="feld"><?= te('Disziplin') ?>
            <select name="teams[<?= $i ?>][disziplin]">
                <option value=""><?= te('bitte wählen') ?></option>
                <option value="kata_team"<?= sel('kata_team', $team['disziplin'] ?? '') ?>><?= te('Kata-Team') ?></option>
                <option value="kumite_team"<?= sel('kumite_team', $team['disziplin'] ?? '') ?>><?= te('Kumite-Team') ?></option>
            </select>
        </label>
    </div>
    <div class="zeile">
        <?php for ($m = 0; $m < 3; $m++): ?>
            <label class="feld"><?= te('Mitglied {nr}', ['nr' => $m + 1]) ?> <?= $auswahl("teams[$i][mitglieder][]", (string) ($mitglieder[$m] ?? ''), te('– wählen –')) ?></label>
        <?php endfor; ?>
        <label class="feld"><?= te('Ersatz') ?> <span class="hilfe"><?= te('optional') ?></span> <?= $auswahl("teams[$i][ersatz]", (string) ($team['ersatz'] ?? ''), te('– kein Ersatz –')) ?></label>
    </div>
    <button type="button" class="knopf klein gefahr" data-aktion="team-weg"><?= te('Team entfernen') ?></button>
</fieldset>
