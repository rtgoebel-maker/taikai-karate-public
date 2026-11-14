<?php

declare(strict_types=1);

use Wettkampf\Datenschutz\Http\AuskunftController;
use Wettkampf\Datenschutz\Http\DatenschutzController;
use Wettkampf\Kern\Http\Router;

/*
 * Routen des Moduls Datenschutz. Zugang siehe Router::ZUGANG (Standard 'admin').
 */
return static function (Router $r): void {
    // Datenschutzhinweise des Turniers: über den Anmeldelink bzw. von der Statusseite
    $r->get('/melden/{schluessel}/datenschutz', [DatenschutzController::class, 'hinweiseAnmeldung'], 'oeffentlich');
    $r->get('/live/{id}/datenschutz', [DatenschutzController::class, 'hinweiseLive'], 'oeffentlich');
    // je Turnier: Angaben, Buchhaltungsexport, Pseudonymisierung (Turnierleitung)
    $r->get('/turnier/{id}/datenschutz', [DatenschutzController::class, 'index'], 'turnier');
    $r->post('/turnier/{id}/datenschutz', [DatenschutzController::class, 'speichern'], 'turnier');
    $r->post('/turnier/{id}/datenschutz/uebernehmen', [DatenschutzController::class, 'uebernehmen'], 'turnier');
    $r->post('/turnier/{id}/datenschutz/buchhaltung', [DatenschutzController::class, 'buchhaltung'], 'turnier');
    $r->post('/turnier/{id}/datenschutz/pseudonymisieren', [DatenschutzController::class, 'pseudonymisieren'], 'turnier');
    // Auskunft und Löschung über alle Turniere (nur Administration)
    $r->get('/datenschutz/auskunft', [AuskunftController::class, 'index']);
    $r->get('/datenschutz/auskunft/starter', [AuskunftController::class, 'starter']);
    $r->get('/datenschutz/auskunft/kontakt', [AuskunftController::class, 'kontakt']);
    $r->post('/datenschutz/auskunft/loeschen', [AuskunftController::class, 'loeschen']);
};
