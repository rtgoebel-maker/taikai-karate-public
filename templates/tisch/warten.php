<?php
/**
 * Gekoppeltes Gerät wartet auf Freigabe durch die Leitung; die Seite lädt sich alle 4 Sekunden neu.
 * @var array<string, mixed> $geraet
 * @var array<string, string> $rollen
 */
$this->layout('layout_einfach', ['titel' => t('Warten auf Freigabe'), 'neuLaden' => 4]);
?>
<p class="obertitel"><?= e($geraet['turnier']) ?> · <?= e($geraet['tatami']) ?> · <?= e(t($rollen[$geraet['rolle']] ?? '')) ?></p>
<h1><?= te('Warten auf Freigabe') ?></h1>
<p><?= th('Bitte der Wettkampfleitung dieses Prüfwort nennen. Sie gibt das Gerät unter <em>Einstellungen → Geräte koppeln</em> frei.') ?></p>
<p class="pruefwort" aria-label="<?= te('Prüfwort') ?>"><?= e($geraet['pruefwort']) ?></p>
<p class="leise"><?= te('Gerät: {name} – diese Seite aktualisiert sich selbst.', ['name' => $geraet['name']]) ?></p>
<p><a class="knopf klein" href="<?= e(url('tisch/warten')) ?>"><?= te('Jetzt prüfen') ?></a></p>
