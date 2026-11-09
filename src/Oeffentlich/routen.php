<?php

declare(strict_types=1);

use Wettkampf\Kern\Http\Router;
use Wettkampf\Oeffentlich\Http\ApiController;
use Wettkampf\Oeffentlich\Http\LiveController;

/*
 * Routen des Moduls Oeffentlich. Zugang siehe Router::ZUGANG (Standard 'admin').
 */
return static function (Router $r): void {
    $r->get('/live/{id}', [LiveController::class, 'tatamis'], 'oeffentlich');
    $r->get('/live/{id}/tatami/{nummer}', [LiveController::class, 'tatami'], 'oeffentlich');
    $r->get('/live/{id}/starterlisten', [LiveController::class, 'starterlisten'], 'oeffentlich');
    $r->get('/live/{id}/starterlisten/offline', [LiveController::class, 'starterlistenOffline'], 'oeffentlich');
    $r->get('/live/{id}/klassen', [LiveController::class, 'klassen'], 'oeffentlich');
    $r->get('/live/{id}/klasse/{klasseid}', [LiveController::class, 'klasse'], 'oeffentlich');
    $r->get('/live/{id}/ergebnisse', [LiveController::class, 'ergebnisse'], 'oeffentlich');
    $r->get('/live/{id}/logo/{art}', [LiveController::class, 'logo'], 'oeffentlich');
    // Einblendung für Livestreams (OBS-Browserquelle): transparenter Hintergrund, aktueller Kampf und Ergebnis
    $r->get('/live/{id}/einblendung/{nummer}', [LiveController::class, 'einblendung'], 'oeffentlich');
    $r->get('/api/v1/turnier/{id}/tatami/{nummer}/einblendung', [ApiController::class, 'einblendung'], 'oeffentlich');
    $r->get('/live/{id}/suche', [LiveController::class, 'suche'], 'oeffentlich');
    $r->get('/api/v1/turnier/{id}', [ApiController::class, 'turnierInfo'], 'oeffentlich');
    $r->get('/api/v1/turnier/{id}/tatamis', [ApiController::class, 'tatamis'], 'oeffentlich');
    $r->get('/api/v1/turnier/{id}/klassen', [ApiController::class, 'klassen'], 'oeffentlich');
    $r->get('/api/v1/turnier/{id}/klasse/{klasseid}', [ApiController::class, 'klasse'], 'oeffentlich');
    $r->get('/api/v1/turnier/{id}/ergebnisse', [ApiController::class, 'ergebnisse'], 'oeffentlich');
};
