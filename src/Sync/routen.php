<?php

declare(strict_types=1);

use Wettkampf\Kern\Http\Router;
use Wettkampf\Sync\Http\EmpfangController;
use Wettkampf\Sync\Http\HalleController;
use Wettkampf\Sync\Http\UebergabeController;

/*
 * Routen des Moduls Sync (Halle ↔ Online, Schritt 9; ZIELARCHITEKTUR Kap. 6.2). Zugang siehe Router::ZUGANG.
 */
return static function (Router $r): void {
    // Online: Übergabe an einen Hallen-Laptop (Turnierleitung)
    $r->get('/turnier/{id}/halle', [UebergabeController::class, 'anzeigen'], 'turnier');
    $r->post('/turnier/{id}/halle/uebergeben', [UebergabeController::class, 'uebergeben'], 'turnier');
    $r->get('/turnier/{id}/halle/datei', [UebergabeController::class, 'datei'], 'turnier');
    $r->post('/turnier/{id}/halle/zuruecknehmen', [UebergabeController::class, 'zuruecknehmen'], 'turnier');
    // Hallen-Laptop: zurückgeben, danach löschen
    $r->post('/turnier/{id}/halle/zurueckgeben', [UebergabeController::class, 'zurueckgeben'], 'turnier');
    $r->post('/turnier/{id}/halle/loeschen', [UebergabeController::class, 'loeschen'], 'turnier');
    $r->post('/api/v1/sync/uebergabe', [UebergabeController::class, 'abholen'], 'maschine');
    $r->post('/api/v1/sync/stand', [EmpfangController::class, 'stand'], 'maschine');
    $r->post('/api/v1/sync/antraege', [EmpfangController::class, 'antraege'], 'maschine');
    $r->post('/api/v1/sync/rueckgabe', [EmpfangController::class, 'rueckgabe'], 'maschine');

    // Hallen-Laptop: Übergabe einspielen (Administration des Laptops)
    $r->get('/halle', [HalleController::class, 'index']);
    $r->post('/halle/datei', [HalleController::class, 'datei']);
    $r->post('/halle/abholen', [HalleController::class, 'abholen']);
};
