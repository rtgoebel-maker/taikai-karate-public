<?php

declare(strict_types=1);

use Wettkampf\Ausgaben\Http\DruckController;
use Wettkampf\Kern\Http\Router;

/*
 * Routen des Moduls Ausgaben. Zugang siehe Router::ZUGANG (Standard 'admin').
 */
return static function (Router $r): void {
    $r->get('/turnier/{id}/druck', [DruckController::class, 'center'], 'turnier');
    $r->get('/turnier/{id}/druck/klasse/{klasseid}', [DruckController::class, 'klasse'], 'turnier');
    $r->get('/turnier/{id}/druck/baeume', [DruckController::class, 'baeume'], 'turnier');
    $r->get('/turnier/{id}/druck/notfall', [DruckController::class, 'notfall'], 'turnier');
    $r->get('/turnier/{id}/druck/aushang', [DruckController::class, 'aushang'], 'turnier');
    $r->get('/turnier/{id}/druck/kampfliste/{tatamiid}', [DruckController::class, 'kampfliste'], 'turnier');
    $r->get('/turnier/{id}/druck/ergebnisse', [DruckController::class, 'ergebnisse'], 'turnier');
    $r->get('/turnier/{id}/druck/siegerliste', [DruckController::class, 'siegerliste'], 'turnier');
    $r->get('/turnier/{id}/druck/vereine', [DruckController::class, 'vereine'], 'turnier');
    $r->get('/turnier/{id}/druck/urkunden', [DruckController::class, 'urkunden'], 'turnier');
    $r->get('/turnier/{id}/logo/{art}', [DruckController::class, 'logo'], 'turnier');
    $r->post('/turnier/{id}/druck/urkunden', [DruckController::class, 'urkundenSpeichern'], 'turnier');
};
