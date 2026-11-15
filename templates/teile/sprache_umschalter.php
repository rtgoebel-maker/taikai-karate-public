<?php
/**
 * Umschalter „🌐 DE | EN“ (je Person, Cookie): aktive Sprache hervorgehoben, die andere als Link zur aktuellen Seite.
 * Weltkugel als eingebettete Grafik (Flaggen stehen für Länder, nicht Sprachen, und fehlen als Emoji unter Windows).
 * Beschriftung bewusst zweisprachig, damit man sie in beiden Sprachen findet.
 * @var string|null $klasse zusätzliche CSS-Klasse
 */
$zurueck = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$query = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
$zurueck .= $query !== '' ? '?' . $query : '';
$aktuell = sprache();
?><span class="sprache-umschalter<?= isset($klasse) ? ' ' . e($klasse) : '' ?>" role="group" aria-label="Sprache / Language" title="Sprache / Language"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M2.5 12h19M12 2.5c2.6 2.7 3.9 5.9 3.9 9.5s-1.3 6.8-3.9 9.5M12 2.5C9.4 5.2 8.1 8.4 8.1 12s1.3 6.8 3.9 9.5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg><?php foreach (\Wettkampf\Kern\Sprache::SPRACHEN as $code => $name): ?><?php if ($code === $aktuell): ?><span class="aktiv" lang="<?= e($code) ?>" aria-current="true" title="<?= e($name) ?>"><?= e(strtoupper($code)) ?></span><?php else: ?><a href="<?= e(url('sprache/wechseln/' . $code, ['zurueck' => $zurueck])) ?>" hreflang="<?= e($code) ?>" lang="<?= e($code) ?>" title="<?= e($name) ?>"><?= e(strtoupper($code)) ?></a><?php endif; ?><?php endforeach; ?></span>
