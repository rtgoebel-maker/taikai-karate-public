<?php

declare(strict_types=1);

use Wettkampf\Kern\Http\Router;
use Wettkampf\Turnier\Http\StartController;
use Wettkampf\Turnier\Http\TurnierController;
use Wettkampf\Turnier\Http\VereinController;

/*
 * Routen des Moduls Turnier. Zugang siehe Router::ZUGANG (Standard 'admin').
 */
return static function (Router $r): void {
    $r->get('/', [StartController::class, 'index'], 'leitung');
    $r->get('/turnier/neu', [TurnierController::class, 'neu']);
    $r->post('/turnier/neu', [TurnierController::class, 'anlegen']);
    $r->get('/turnier/{id}', [TurnierController::class, 'uebersicht'], 'turnier');
    $r->post('/turnier/{id}/phase', [TurnierController::class, 'phase'], 'turnier');
    $r->post('/turnier/{id}/nicht-erschienen', [TurnierController::class, 'nichtErschienen'], 'turnier');
    $r->post('/turnier/{id}/kata-beendet', [TurnierController::class, 'kataBeendet'], 'turnier');
    $r->get('/turnier/{id}/einstellungen', [TurnierController::class, 'einstellungen'], 'turnier');
    $r->post('/turnier/{id}/einstellungen', [TurnierController::class, 'speichern'], 'turnier');
    $r->post('/turnier/{id}/zugaenge', [TurnierController::class, 'zugaengeSpeichern'], 'turnier');
    $r->post('/turnier/{id}/loeschen', [TurnierController::class, 'loeschen']);
    $r->get('/vereine', [VereinController::class, 'index']);
    $r->post('/vereine', [VereinController::class, 'speichern']);
    $r->get('/vereine/zusammenfuehren', [VereinController::class, 'zusammenfuehrenVorschau']);
    $r->post('/vereine/zusammenfuehren', [VereinController::class, 'zusammenfuehren']);
    $r->post('/vereine/leere-loeschen', [VereinController::class, 'leereLoeschen']);
    $r->post('/verein/{id}/loeschen', [VereinController::class, 'loeschen']);
    $r->get('/verein/{id}', [VereinController::class, 'zeigen']);
};
