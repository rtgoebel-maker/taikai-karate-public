<?php

declare(strict_types=1);

/*
 * Hallen-Laptop: sendet den Stand der übernommenen Turniere im Hintergrund an Online (Schritt 9e).
 * Wird von start-lokal.bat in einem eigenen Fenster gestartet – getrennt vom PHP-Server, damit eine langsame
 * Internetverbindung nie die Tablets aufhält. Ohne übernommenes Turnier tut es nichts.
 *
 *   php bin/sync.php           läuft, bis das Fenster geschlossen wird
 *   php bin/sync.php --einmal  ein Durchgang (zum Testen)
 */

use Wettkampf\App;
use Wettkampf\Sync\HttpTransport;
use Wettkampf\Sync\SendeService;

require dirname(__DIR__) . '/vendor/autoload.php';

$einmal = in_array('--einmal', $argv, true);
ini_set('default_socket_timeout', '10');
echo 'Sync Hallen-Laptop → Online (alle ' . SendeService::ABSTAND_SEKUNDEN . " s). Bitte laufen lassen (Fenster bzw. Dienst).\n";
if (!extension_loaded('openssl')) {
    echo "Hinweis: PHP-Erweiterung OpenSSL fehlt – Online-Adressen mit https:// sind so nicht erreichbar.\n";
}

do {
    try {
        $app = App::erstellen(dirname(__DIR__));
        $sende = new SendeService($app->db, $app->instanz());
        foreach ($sende->alleSenden(new HttpTransport(10)) as $turnierId => $ergebnis) {
            if (in_array($ergebnis, [SendeService::GESENDET, SendeService::FEHLER], true)) {
                $stand = $sende->stand($turnierId);
                echo date('H:i:s') . " Turnier $turnierId: "
                    . ($ergebnis === SendeService::GESENDET ? 'Stand ' . ($stand['bestaetigt'] ?? '?') . ' übertragen' : 'Fehler – ' . ($stand['fehler'] ?? '')) . "\n";
            }
        }
    } catch (Throwable $e) {
        echo date('H:i:s') . ' Fehler: ' . $e->getMessage() . "\n";
    }
    if (!$einmal) {
        sleep(5); // häufiger prüfen als senden: SendeService hält den Abstand selbst ein
    }
} while (!$einmal);
