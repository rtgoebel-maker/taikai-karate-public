<?php
/**
 * Feedback-Knopf (unten rechts) – nur wenn in den Systemeinstellungen aktiviert.
 * Der Screenshot entsteht im Browser (html2canvas, erst beim Klick geladen).
 * @var bool|null $feedbackAktiv  geteilt von App
 * @var bool|null $kompakt        Tablets: kleiner runder Knopf unten links
 * @var string|null $feedbackAdresse Empfänger der Meldungen (Taikai-Team), geteilt von App
 */
if (!($feedbackAktiv ?? false)) {
    return;
}
?>
<link rel="stylesheet" href="<?= e(asset('feedback.css')) ?>">
<script src="<?= e(asset('feedback.js')) ?>" defer
        data-senden="<?= e(url('api/v1/feedback')) ?>"
        data-bibliothek="<?= e(asset('vendor/html2canvas.min.js')) ?>"
        data-kompakt="<?= !empty($kompakt) ? '1' : '0' ?>"
        data-adresse="<?= e($feedbackAdresse ?? 'feedback@taikai-karate.de') ?>"></script>
