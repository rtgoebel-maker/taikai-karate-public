<?php

declare(strict_types=1);

use Wettkampf\App;

// PHP-Fehler nie im Browser zeigen, unabhängig von der Hoster-Einstellung (sie landen im Error-Log)
ini_set('display_errors', '0');

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Der Ordner vendor/ fehlt. Bitte das fertige Paket (mit vendor/) verwenden oder im Projektordner\n"
        . "„composer install --no-dev“ ausführen – siehe README, Abschnitt Installation.\n";
    exit;
}
require $autoload;

App::erstellen(dirname(__DIR__))->slim()->run();
