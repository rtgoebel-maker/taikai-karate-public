<?php
/**
 * Gekoppeltes Gerät wartet auf Freigabe durch die Leitung; die Seite lädt sich alle 4 Sekunden neu.
 * @var array<string, mixed> $geraet
 * @var array<string, string> $rollen
 */
$this->layout('layout_einfach', ['titel' => 'Warten auf Freigabe', 'neuLaden' => 4]);
?>
<p class="obertitel"><?= e($geraet['turnier']) ?> · <?= e($geraet['tatami']) ?> · <?= e($rollen[$geraet['rolle']] ?? '') ?></p>
<h1>Warten auf Freigabe</h1>
<p>Bitte der Wettkampfleitung dieses Prüfwort nennen. Sie gibt das Gerät unter <em>Einstellungen → Geräte koppeln</em> frei.</p>
<p class="pruefwort" aria-label="Prüfwort"><?= e($geraet['pruefwort']) ?></p>
<p class="leise">Gerät: <?= e($geraet['name']) ?> – diese Seite aktualisiert sich selbst.</p>
<p><a class="knopf klein" href="<?= e(url('tisch/warten')) ?>">Jetzt prüfen</a></p>
