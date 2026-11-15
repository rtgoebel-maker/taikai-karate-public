<?php
/**
 * Umschalter Deutsch/English (je Person, Cookie). Führt zur aktuellen Seite zurück.
 * @var string|null $klasse zusätzliche CSS-Klasse
 */
$zurueck = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$query = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
$zurueck .= $query !== '' ? '?' . $query : '';
$andere = sprache() === 'en' ? 'de' : 'en';
?><a class="sprache-umschalter<?= isset($klasse) ? ' ' . e($klasse) : '' ?>" href="<?= e(url('sprache/wechseln/' . $andere, ['zurueck' => $zurueck])) ?>" hreflang="<?= e($andere) ?>" lang="<?= e($andere) ?>"><?= $andere === 'en' ? 'English' : 'Deutsch' ?></a>