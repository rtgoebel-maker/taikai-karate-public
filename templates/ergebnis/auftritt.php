<?php
/**
 * Punktwertung eines Kata-Vortrags.
 * @var array<string, mixed> $turnier
 * @var array<string, mixed> $klasse
 * @var array<string, mixed> $a
 */
use Wettkampf\Durchfuehrung\PhasenErzeugung;

$tid = $turnier['id'];
$this->layout('layout', ['titel' => t('Wertung {name}', ['name' => $a['name']]), 'turnier' => $turnier, 'aktiv' => 'ergebnisse']);
$kr = (int) $klasse['kampfrichter'];
$opt = json_decode((string) $klasse['optionen'], true) ?: [];
$katas = array_filter(array_map('trim', explode(',', (string) $a['kata_liste'])));
$werte = $a['wertung_daten']['werte'] ?? [];
?>
<p class="obertitel"><a href="<?= e(url("turnier/$tid/klasse/{$klasse['id']}")) ?>"><?= e($klasse['code']) ?></a> · <?= e(PhasenErzeugung::bezeichnungText((string) $a['phase'])) ?> · <?= te('Startplatz {nr}', ['nr' => $a['reihenfolge']]) ?></p>
<h1><?= e($a['name']) ?> <span class="leise" style="font-size:1rem"><?= e($a['verein']) ?></span></h1>
<form method="post" action="<?= e(url("turnier/$tid/auftritt/{$a['id']}")) ?>" class="karte formular" style="max-width:720px">
    <?= csrf_feld() ?>
    <input type="hidden" name="version" value="<?= e($a['version']) ?>">
    <?php if ($a['status'] === 'beendet'): ?>
        <div class="meldung meldung-hinweis"><?= te('Wertung liegt vor ({punkte} Punkte). Speichern überschreibt sie (wird protokolliert).', ['punkte' => number_format((float) $a['gesamt'], 1, ',', '')]) ?></div>
    <?php endif; ?>
    <label><?= te('Vorgetragene Kata') ?>
        <input name="kata" list="katas" value="<?= e($a['kata']) ?>" placeholder="<?= $katas ? te('Auswahl laut Klasse') : te('frei (Tokui)') ?>" required>
    </label>
    <div class="zeile">
        <?php for ($i = 0; $i < $kr; $i++): ?>
            <label><?= te('KR {nr}', ['nr' => $i + 1]) ?> <input name="werte[]" inputmode="decimal" required value="<?= e(isset($werte[$i]) ? str_replace('.', ',', (string) $werte[$i]) : '') ?>"></label>
        <?php endfor; ?>
    </div>
    <p class="hilfe"><?= te('Bereich {min}–{max}, Schritt {schritt}.', ['min' => $opt['punkte_min'] ?? 5, 'max' => $opt['punkte_max'] ?? 10, 'schritt' => $opt['punkte_schritt'] ?? 0.1]) ?><?= ($opt['streichung'] ?? true) && $kr >= 5 ? ' ' . te('Höchste und niedrigste Wertung werden gestrichen.') : '' ?></p>
    <div class="knopfleiste">
        <button class="knopf primaer" type="submit"><?= te('Wertung speichern') ?></button>
        <button class="knopf" type="submit" name="entscheidung" value="kiken" formnovalidate><?= te('Kiken (nicht angetreten)') ?></button>
        <a class="knopf" href="<?= e(url("turnier/$tid/klasse/{$klasse['id']}")) ?>"><?= te('Abbrechen') ?></a>
    </div>
</form>
<datalist id="katas"><?php foreach ($katas as $k): ?><option value="<?= e($k) ?>"><?php endforeach; ?></datalist>
