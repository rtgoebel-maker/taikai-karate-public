<?php

declare(strict_types=1);

/*
 * Router-Skript für den integrierten PHP-Server:
 *   php -S localhost:8080 -t public public/router.php
 * Vorhandene Dateien (CSS/JS) werden direkt ausgeliefert, alles andere geht an index.php.
 */
$pfad = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($pfad !== __DIR__ . '/' && is_file($pfad)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
