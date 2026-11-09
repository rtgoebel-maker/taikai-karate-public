<?php

declare(strict_types=1);

use Wettkampf\Kern\Http\Router;
use Wettkampf\Meldung\Http\AnmeldungController;
use Wettkampf\Meldung\Http\CoachController;
use Wettkampf\Meldung\Http\ImportController;
use Wettkampf\Meldung\Http\RechnungController;
use Wettkampf\Meldung\Http\TeamController;
use Wettkampf\Meldung\Http\TeilnehmerController;

/*
 * Routen des Moduls Meldung. Zugang siehe Router::ZUGANG (Standard 'admin').
 */
return static function (Router $r): void {
    // Anmeldeformular nur über den Zufallsschlüssel; /anmeldung/{nr} leitet nur die Turnierleitung weiter
    $r->get('/melden/{schluessel}', [AnmeldungController::class, 'melden'], 'oeffentlich');
    $r->post('/melden/{schluessel}', [AnmeldungController::class, 'absenden'], 'oeffentlich');
    $r->get('/anmeldung/{id}', [AnmeldungController::class, 'formular'], 'oeffentlich');
    $r->get('/anmeldung/{id}/{code}/rechnung', [AnmeldungController::class, 'rechnungDojo'], 'oeffentlich');
    $r->get('/anmeldung/{id}/{code}/uebersicht', [AnmeldungController::class, 'uebersicht'], 'oeffentlich');
    $r->get('/anmeldung/{id}/{code}', [AnmeldungController::class, 'bearbeiten'], 'oeffentlich');
    $r->post('/anmeldung/{id}/{code}', [AnmeldungController::class, 'aendern'], 'oeffentlich');
    // „Meine Meldungen“: jedes angemeldete Konto (auch ein selbst angelegter Zugang vor der Zuordnung zum Dojo)
    $r->get('/coach', [CoachController::class, 'index'], 'konto');
    // Coaching-Portal je Turnier (nur eigenes Dojo; Freigabe durch die Turnierleitung)
    $r->get('/coach/turnier/{id}', [CoachController::class, 'portal'], 'coach');
    $r->get('/coach/turnier/{id}/starter', [CoachController::class, 'starter'], 'coach');
    $r->get('/coach/turnier/{id}/revision', [CoachController::class, 'revision'], 'coach');
    $r->get('/coach/turnier/{id}/ergebnisse', [CoachController::class, 'ergebnisse'], 'coach');
    $r->get('/coach/turnier/{id}/antraege', [CoachController::class, 'antraege'], 'coach');
    $r->post('/coach/turnier/{id}/antraege', [CoachController::class, 'antragStellen'], 'coach');
    $r->get('/coach/turnier/{id}/startgeld', [CoachController::class, 'startgeld'], 'coach');
    $r->post('/turnier/{id}/coach-einstellungen', [CoachController::class, 'einstellungen'], 'turnier');
    $r->get('/turnier/{id}/korrekturen', [CoachController::class, 'korrekturen'], 'turnier');
    $r->post('/turnier/{id}/korrektur/{antragid}', [CoachController::class, 'korrekturBearbeiten'], 'turnier');
    $r->get('/turnier/{id}/anmeldungen', [AnmeldungController::class, 'index'], 'turnier');
    $r->post('/turnier/{id}/anmeldungen/link-erneuern', [AnmeldungController::class, 'linkErneuern'], 'turnier');
    $r->post('/turnier/{id}/anmeldungen/einstellungen', [AnmeldungController::class, 'einstellungen'], 'turnier');
    $r->get('/turnier/{id}/rechnungen', [RechnungController::class, 'index'], 'turnier');
    $r->post('/turnier/{id}/rechnungen/einstellungen', [RechnungController::class, 'einstellungen'], 'turnier');
    $r->get('/turnier/{id}/rechnungen/druck', [RechnungController::class, 'druck'], 'turnier');
    $r->get('/turnier/{id}/anmeldung/{anmeldungid}', [AnmeldungController::class, 'detail'], 'turnier');
    $r->get('/turnier/{id}/anmeldung/{anmeldungid}/druck', [AnmeldungController::class, 'druck'], 'turnier');
    $r->get('/turnier/{id}/anmeldung/{anmeldungid}/rechnung', [AnmeldungController::class, 'rechnung'], 'turnier');
    $r->post('/turnier/{id}/anmeldung/{anmeldungid}/zahlung', [AnmeldungController::class, 'zahlung'], 'turnier');
    $r->post('/turnier/{id}/anmeldung/{anmeldungid}/zahlung/zuruecknehmen', [AnmeldungController::class, 'zahlungZuruecknehmen'], 'turnier');
    $r->post('/turnier/{id}/anmeldung/{anmeldungid}/freigeben', [AnmeldungController::class, 'freigeben'], 'turnier');
    $r->post('/turnier/{id}/anmeldung/{anmeldungid}/ablehnen', [AnmeldungController::class, 'ablehnen'], 'turnier');
    $r->post('/turnier/{id}/anmeldung/{anmeldungid}/oeffnen', [AnmeldungController::class, 'oeffnen'], 'turnier');
    $r->get('/turnier/{id}/teilnehmer', [TeilnehmerController::class, 'index'], 'turnier');
    $r->get('/turnier/{id}/teilnehmer/neu', [TeilnehmerController::class, 'neu'], 'turnier');
    $r->post('/turnier/{id}/teilnehmer', [TeilnehmerController::class, 'speichern'], 'turnier');
    $r->get('/turnier/{id}/teilnehmer/{teilnahmeid}', [TeilnehmerController::class, 'bearbeiten'], 'turnier');
    $r->post('/turnier/{id}/teilnehmer/{teilnahmeid}/loeschen', [TeilnehmerController::class, 'loeschen'], 'turnier');
    $r->post('/turnier/{id}/teilnehmer/{teilnahmeid}/nicht-erschienen', [TeilnehmerController::class, 'nichtErschienen'], 'turnier');
    $r->get('/turnier/{id}/import', [ImportController::class, 'formular'], 'turnier');
    $r->post('/turnier/{id}/import', [ImportController::class, 'hochladen'], 'turnier');
    $r->get('/turnier/{id}/import/vorlage', [ImportController::class, 'vorlage'], 'turnier');
    $r->post('/turnier/{id}/import/uebernehmen', [ImportController::class, 'uebernehmen'], 'turnier');
    $r->get('/turnier/{id}/teams', [TeamController::class, 'index'], 'turnier');
    $r->post('/turnier/{id}/teams', [TeamController::class, 'speichern'], 'turnier');
    $r->post('/turnier/{id}/team/{teamid}/mitglied', [TeamController::class, 'mitglied'], 'turnier');
    $r->post('/turnier/{id}/team/{teamid}/mitglied/{mitgliedid}/entfernen', [TeamController::class, 'mitgliedEntfernen'], 'turnier');
    $r->post('/turnier/{id}/team/{teamid}/loeschen', [TeamController::class, 'loeschen'], 'turnier');
};
