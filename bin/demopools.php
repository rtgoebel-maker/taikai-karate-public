<?php

declare(strict_types=1);

/*
 * Demo-Turnier zur Pool-Bildung (Turnier noch nicht gestartet, ausgelost und verteilt).
 *   php bin/demopools.php [--datum=JJJJ-MM-TT] [--name="…"] [--logos-von=ID] [--teams] [--phase=vorbereitung|laeuft|abgeschlossen]
 * Inhalt siehe Application\DemoTurnier – dasselbe wie „Demo-Turnier anlegen“ unter System.
 */

use Wettkampf\App;
use Wettkampf\Betrieb\DemoTurnier;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = App::erstellen(dirname(__DIR__));
$datum = date('Y') . '-11-21';
$name = null;
$logosVon = null;
$mitTeams = false;
$phase = 'vorbereitung';
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--datum=(\d{4}-\d{2}-\d{2})$/', $argument, $m)) {
        $datum = $m[1];
    } elseif (preg_match('/^--name=(.+)$/', $argument, $m)) {
        $name = $m[1];
    } elseif (preg_match('/^--logos-von=(\d+)$/', $argument, $m)) {
        $logosVon = (int) $m[1];
    } elseif (preg_match('/^--phase=(\w+)$/', $argument, $m)) {
        $phase = $m[1];
    } elseif ($argument === '--teams') {
        $mitTeams = true;
    }
}

$demo = (new DemoTurnier($app->db))->anlegen($datum, $name, $mitTeams, $logosVon, $phase);

printf(
    "Turnier #%d „%s“ (%s): %d Teilnehmer%s, %d Klassen ausgelost, %d Ergebnisse – %s.\n",
    $demo['turnier'],
    $demo['name'],
    $datum,
    $demo['teilnehmer'],
    $demo['teams'] ? ", {$demo['teams']} Teams" : '',
    $demo['ausgelost'],
    $demo['ergebnisse'],
    DemoTurnier::PHASEN[$phase],
);
foreach ($demo['klassen'] as $k) {
    printf("  %-3s %-38s %2d Starter  %s\n", $k['code'], $k['bezeichnung'], $k['starter'], $k['pools'] ? implode(' · ', $k['pools']) : 'ohne Pools');
}
echo 'PINs: ' . DemoTurnier::PINS . ".\n";
