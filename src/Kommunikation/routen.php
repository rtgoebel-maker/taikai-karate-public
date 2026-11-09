<?php

declare(strict_types=1);

use Wettkampf\Kern\Http\Router;
use Wettkampf\Kommunikation\Http\FeedbackController;
use Wettkampf\Kommunikation\Http\MailController;

/*
 * Routen des Moduls Kommunikation. Zugang siehe Router::ZUGANG (Standard 'admin').
 */
return static function (Router $r): void {
    $r->post('/api/v1/feedback', [FeedbackController::class, 'einreichen'], 'tisch');
    $r->get('/system', [FeedbackController::class, 'system']);
    $r->post('/system', [FeedbackController::class, 'systemSpeichern']);
    // E-Mail-Versand (Postfach in der Oberfläche; Änderungen mit Passwortbestätigung)
    $r->post('/system/mail', [MailController::class, 'speichern']);
    $r->post('/system/mail/test', [MailController::class, 'test']);
    $r->post('/system/mail/diagnose', [MailController::class, 'diagnose']);
    $r->post('/system/mail/warteschlange', [MailController::class, 'warteschlange']);
    $r->post('/system/demo-modus', [FeedbackController::class, 'demoModus']);
    $r->post('/system/demo-turnier', [FeedbackController::class, 'demoTurnier']);
    $r->get('/feedback', [FeedbackController::class, 'index']);
    $r->get('/feedback/export', [FeedbackController::class, 'export']);
    $r->get('/feedback/{id}/screenshot', [FeedbackController::class, 'screenshot']);
    $r->post('/feedback/{id}/loeschen', [FeedbackController::class, 'loeschen']);
    $r->post('/feedback/{id}', [FeedbackController::class, 'bearbeiten']);
};
