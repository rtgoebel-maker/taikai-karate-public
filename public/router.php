<?php

declare(strict_types=1);

/*
 * Router-Skript für den integrierten PHP-Server:
 *   php -S localhost:8080 -t public public/router.php
 * Vorhandene Dateien (CSS/JS) werden direkt ausgeliefert, alles andere geht an index.php.
 * Nur Dateien innerhalb von public/: realpath() löst „..“ auf, sonst ließe sich die Existenz von Dateien außerhalb
 * (z. B. config/config.php) abfragen.
 */
$datei = realpath(__DIR__ . rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)));
// nosemgrep: php.lang.security.injection.tainted-filename.tainted-filename – Pfad auf public/ beschränkt (realpath + Präfix)
if ($datei !== false && str_starts_with($datei, __DIR__ . DIRECTORY_SEPARATOR) && is_file($datei)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
