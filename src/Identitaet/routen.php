<?php

declare(strict_types=1);

use Wettkampf\Identitaet\Http\AuthController;
use Wettkampf\Identitaet\Http\BenutzerController;
use Wettkampf\Identitaet\Http\KontoController;
use Wettkampf\Identitaet\Http\SicherheitController;
use Wettkampf\Identitaet\Http\SperrenController;
use Wettkampf\Identitaet\Http\TurnierleitungController;
use Wettkampf\Kern\Http\Router;

/*
 * Routen des Moduls Identitaet. Zugang siehe Router::ZUGANG (Standard 'admin').
 */
return static function (Router $r): void {
    $r->get('/setup', [AuthController::class, 'setupFormular'], 'oeffentlich');
    $r->post('/setup', [AuthController::class, 'setup'], 'oeffentlich');
    $r->get('/login', [AuthController::class, 'loginFormular'], 'oeffentlich');
    $r->post('/login', [AuthController::class, 'login'], 'oeffentlich');
    // zweiter Faktor: Code per E-Mail
    $r->get('/login/code', [AuthController::class, 'codeFormular'], 'oeffentlich');
    $r->post('/login/code', [AuthController::class, 'code'], 'oeffentlich');
    $r->post('/login/code/neu', [AuthController::class, 'codeNeu'], 'oeffentlich');
    $r->get('/login/app-einrichten', [AuthController::class, 'appEinrichtenFormular'], 'oeffentlich');
    $r->post('/login/app-einrichten', [AuthController::class, 'appEinrichten'], 'oeffentlich');
    // Konto & Sicherheit (jedes angemeldete Konto): Authenticator-App, Wiederherstellungscodes, vertraute Geräte
    $r->get('/konto/sicherheit', [SicherheitController::class, 'index'], 'konto');
    $r->post('/konto/sicherheit/angaben', [SicherheitController::class, 'angaben'], 'konto');
    $r->post('/konto/sicherheit/passwort', [SicherheitController::class, 'passwort'], 'konto');
    $r->post('/konto/sicherheit/app', [SicherheitController::class, 'appEinrichten'], 'konto');
    $r->post('/konto/sicherheit/app/entfernen', [SicherheitController::class, 'appEntfernen'], 'konto');
    $r->post('/konto/sicherheit/codes', [SicherheitController::class, 'codesNeu'], 'konto');
    $r->post('/konto/sicherheit/vertrauen', [SicherheitController::class, 'vertrauenWiderrufen'], 'konto');
    $r->post('/logout', [AuthController::class, 'logout'], 'oeffentlich');
    // selbst angelegte Dojo-Konten und „Passwort vergessen“ (nur mit E-Mail-Versand)
    $r->get('/konto/bestaetigen/{token}', [KontoController::class, 'bestaetigen'], 'oeffentlich');
    $r->get('/passwort-vergessen', [KontoController::class, 'vergessenFormular'], 'oeffentlich');
    $r->post('/passwort-vergessen', [KontoController::class, 'vergessen'], 'oeffentlich');
    $r->get('/passwort-neu/{token}', [KontoController::class, 'neuFormular'], 'oeffentlich');
    $r->post('/passwort-neu/{token}', [KontoController::class, 'neu'], 'oeffentlich');
    $r->get('/benutzer', [BenutzerController::class, 'index']);
    $r->post('/benutzer', [BenutzerController::class, 'anlegen']);
    $r->post('/benutzer/{id}/passwort', [BenutzerController::class, 'passwort']);
    $r->post('/benutzer/{id}/app-zuruecksetzen', [BenutzerController::class, 'appZuruecksetzen']);
    $r->post('/benutzer/{id}/vertrauen-zuruecksetzen', [BenutzerController::class, 'vertrauenZuruecksetzen']);
    $r->post('/benutzer/{id}/aktiv', [BenutzerController::class, 'aktivUmschalten']);
    $r->post('/benutzer/{id}/loeschen', [BenutzerController::class, 'loeschen']);
    $r->post('/benutzer/{id}/admin', [BenutzerController::class, 'adminUmschalten']);
    $r->post('/benutzer/{id}', [BenutzerController::class, 'bearbeiten']);
    // System → Gesperrte Anmeldungen (AnmeldeSchutz)
    $r->get('/system/sperren', [SperrenController::class, 'index']);
    $r->post('/system/sperren/aufheben', [SperrenController::class, 'aufheben']);
    $r->get('/turnier/{id}/leitung', [TurnierleitungController::class, 'index'], 'turnier');
    $r->post('/turnier/{id}/leitung', [TurnierleitungController::class, 'hinzufuegen'], 'turnier');
    $r->post('/turnier/{id}/leitung/{benutzerid}/entfernen', [TurnierleitungController::class, 'entfernen'], 'turnier');
};
