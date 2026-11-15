<?php
/**
 * Übersetzungen für die Skripte (Wettkampf.t in app.js) – nur nötig, wenn nicht Deutsch. VOR app.js einbinden.
 */
if (sprache() !== \Wettkampf\Kern\Sprache::STANDARD): ?>
    <script src="<?= e(url('sprache/texte.js', ['s' => sprache(), 'v' => \Wettkampf\Kern\Sprache::katalogVersion(sprache())])) ?>"></script>
<?php endif;
