<?php
/**
 * Vollbild-Anzeige einer Tatami (z. B. Bildschirm an der Matte).
 * @var array<string, mixed> $turnier
 * @var array<string, mixed> $t
 */
$this->layout('live/layout', ['turnier' => $turnier, 'aktiv' => 'tatamis']);
?>
<?= $this->teil('live/tatami_anzeige', ['turnier' => $turnier, 't' => $t, 'vollbild' => true]) ?>
