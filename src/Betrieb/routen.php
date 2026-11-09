<?php

declare(strict_types=1);

use Wettkampf\Betrieb\Http\AssistentController;
use Wettkampf\Betrieb\Http\HilfeController;
use Wettkampf\Betrieb\Http\SicherungController;
use Wettkampf\Betrieb\Http\UeberController;
use Wettkampf\Betrieb\Http\VerlaufController;
use Wettkampf\Kern\Http\Router;

/*
 * Routen des Moduls Betrieb. Zugang siehe Router::ZUGANG (Standard 'admin').
 */
return static function (Router $r): void {
    $r->get('/hilfe', [HilfeController::class, 'index'], 'leitung');
    $r->get('/handout/{name}', [HilfeController::class, 'handout'], 'leitung');
    // Über diese Software, Changelog, Lizenz – öffentlich (AGPL: Hinweise und Quellcode-Angebot für alle Nutzer)
    $r->get('/ueber', [UeberController::class, 'index'], 'oeffentlich');
    $r->get('/ueber/changelog', [UeberController::class, 'changelog'], 'oeffentlich');
    $r->get('/ueber/lizenz', [UeberController::class, 'lizenz'], 'oeffentlich');
    // Einrichtungs-Assistent eines Turniers
    $r->get('/turnier/{id}/assistent', [AssistentController::class, 'index'], 'turnier');
    $r->get('/turnier/{id}/assistent/{schritt}', [AssistentController::class, 'schritt'], 'turnier');
    $r->get('/turnier/{id}/export', [SicherungController::class, 'export'], 'turnier');
    $r->get('/import-turnier', [SicherungController::class, 'importFormular']);
    $r->post('/import-turnier', [SicherungController::class, 'import']);
    $r->get('/sicherung/datenbank', [SicherungController::class, 'datenbank']);
    // automatische Sicherungen (Hallen-Laptop): Liste auf /import-turnier, Download, einzelnes Turnier wiederherstellen
    $r->post('/sicherung/auto', [SicherungController::class, 'autoJetzt']);
    $r->get('/sicherung/auto/{name}', [SicherungController::class, 'autoStand']);
    $r->get('/sicherung/auto/{name}/download', [SicherungController::class, 'autoDownload']);
    $r->post('/sicherung/auto/{name}/turnier/{turnierid}', [SicherungController::class, 'autoWiederherstellen']);
    $r->get('/turnier/{id}/verlauf', [VerlaufController::class, 'turnierVerlauf'], 'turnier');
    $r->get('/verlauf', [VerlaufController::class, 'globalerVerlauf']);
};
