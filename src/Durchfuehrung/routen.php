<?php

declare(strict_types=1);

use Wettkampf\Durchfuehrung\Http\AuslosungController;
use Wettkampf\Durchfuehrung\Http\ErgebnisController;
use Wettkampf\Durchfuehrung\Http\GeraeteController;
use Wettkampf\Durchfuehrung\Http\KlassenController;
use Wettkampf\Durchfuehrung\Http\PlanController;
use Wettkampf\Durchfuehrung\Http\TischApiController;
use Wettkampf\Durchfuehrung\Http\TischController;
use Wettkampf\Kern\Http\Router;

/*
 * Routen des Moduls Durchfuehrung. Zugang siehe Router::ZUGANG (Standard 'admin').
 */
return static function (Router $r): void {
    $r->get('/tisch', [TischController::class, 'anmeldung'], 'oeffentlich');
    $r->post('/tisch', [TischController::class, 'anmelden'], 'oeffentlich');
    // Geräte-Kopplung per QR-Code (Freigabe durch die Leitung)
    $r->get('/tisch/koppeln/{tatamiid}/{rolle}/{token}', [TischController::class, 'koppelnFormular'], 'oeffentlich');
    $r->post('/tisch/koppeln/{tatamiid}/{rolle}/{token}', [TischController::class, 'koppeln'], 'oeffentlich');
    $r->get('/tisch/warten', [TischController::class, 'warten'], 'oeffentlich');
    $r->get('/turnier/{id}/geraete', [GeraeteController::class, 'index'], 'turnier');
    $r->get('/turnier/{id}/geraete/druck', [GeraeteController::class, 'druck'], 'turnier');
    $r->get('/turnier/{id}/geraete/stand', [GeraeteController::class, 'stand'], 'turnier');
    $r->post('/turnier/{id}/geraete/qr-erneuern', [GeraeteController::class, 'qrErneuern'], 'turnier');
    $r->post('/turnier/{id}/geraete/pin', [GeraeteController::class, 'pin'], 'turnier');
    $r->post('/turnier/{id}/geraete/aufraeumen', [GeraeteController::class, 'aufraeumen'], 'turnier');
    $r->post('/turnier/{id}/geraete/{geraetid}/freigeben', [GeraeteController::class, 'freigeben'], 'turnier');
    $r->post('/turnier/{id}/geraete/{geraetid}/sperren', [GeraeteController::class, 'sperren'], 'turnier');
    $r->get('/tisch/app', [TischController::class, 'app'], 'tisch');
    $r->get('/tisch/ausruf', [TischController::class, 'ausruf'], 'tisch');
    $r->get('/tisch/manifest.webmanifest', [TischController::class, 'manifest'], 'oeffentlich');
    $r->post('/tisch/abmelden', [TischController::class, 'abmelden'], 'oeffentlich');
    $r->get('/api/v1/tisch', [TischApiController::class, 'tischZustand'], 'tisch');
    $r->get('/api/v1/tisch/revision', [TischApiController::class, 'tischRevision'], 'tisch');
    $r->get('/api/v1/tisch/ausruf', [TischApiController::class, 'ausrufZustand'], 'tisch');
    $r->post('/api/v1/tisch/nachricht/{nachrichtid}/bestaetigen', [TischApiController::class, 'nachrichtBestaetigen'], 'tisch');
    $r->post('/api/v1/tisch/klasse/{klasseid}/ausruf-freigeben', [TischApiController::class, 'ausrufFreigeben'], 'tisch');
    $r->post('/api/v1/tisch/klasse/{klasseid}/abschliessen', [TischApiController::class, 'tischAbschliessen'], 'tisch');
    $r->post('/api/v1/tisch/ausruf/klasse/{klasseid}/starter/{startid}', [TischApiController::class, 'ausrufStarter'], 'tisch');
    $r->post('/api/v1/tisch/ausruf/klasse/{klasseid}/abschliessen', [TischApiController::class, 'ausrufGruppeAbschliessen'], 'tisch');
    $r->post('/api/v1/tisch/ausruf/klasse/{klasseid}/oeffnen', [TischApiController::class, 'ausrufGruppeOeffnen'], 'tisch');
    $r->post('/api/v1/tisch/ausruf/klasse/{klasseid}', [TischApiController::class, 'ausrufGruppe'], 'tisch');
    $r->post('/api/v1/tisch/ausruf/{typ}/{eintragid}/abschliessen', [TischApiController::class, 'ausrufAbschliessen'], 'tisch');
    $r->post('/api/v1/tisch/ausruf/{typ}/{eintragid}/oeffnen', [TischApiController::class, 'ausrufOeffnen'], 'tisch');
    $r->post('/api/v1/tisch/ausruf/{typ}/{eintragid}', [TischApiController::class, 'ausrufMarkieren'], 'tisch');
    $r->get('/api/v1/tisch/eintrag/{typ}/{eintragid}', [TischApiController::class, 'tischEintrag'], 'tisch');
    $r->post('/api/v1/tisch/begegnung/{begegnungid}/starten', [TischApiController::class, 'tischStarten'], 'tisch');
    $r->post('/api/v1/tisch/begegnung/{begegnungid}/unterbrechen', [TischApiController::class, 'tischUnterbrechen'], 'tisch');
    $r->post('/api/v1/tisch/begegnung/{begegnungid}/zuruecksetzen', [TischApiController::class, 'tischZuruecksetzen'], 'tisch');
    $r->post('/api/v1/tisch/begegnung/{begegnungid}', [TischApiController::class, 'tischBegegnung'], 'tisch');
    $r->post('/api/v1/tisch/auftritt/{auftrittid}', [TischApiController::class, 'tischAuftritt'], 'tisch');
    $r->get('/turnier/{id}/klassen', [KlassenController::class, 'index'], 'turnier');
    $r->post('/turnier/{id}/klassen', [KlassenController::class, 'speichern'], 'turnier');
    $r->post('/turnier/{id}/klassen/vorlage', [KlassenController::class, 'vorlage'], 'turnier');
    $r->post('/turnier/{id}/klassen/zuordnen', [KlassenController::class, 'zuordnen'], 'turnier');
    $r->post('/turnier/{id}/klassen/zusammenlegen', [KlassenController::class, 'zusammenlegen'], 'turnier');
    $r->get('/turnier/{id}/klasse/neu', [KlassenController::class, 'neu'], 'turnier');
    $r->get('/turnier/{id}/klasse/{klasseid}', [KlassenController::class, 'detail'], 'turnier');
    $r->get('/turnier/{id}/klasse/{klasseid}/bearbeiten', [KlassenController::class, 'bearbeiten'], 'turnier');
    $r->post('/turnier/{id}/klasse/{klasseid}/starter', [KlassenController::class, 'starterSetzen'], 'turnier');
    $r->post('/turnier/{id}/klasse/{klasseid}/starter/{startid}/entfernen', [KlassenController::class, 'starterEntfernen'], 'turnier');
    $r->post('/turnier/{id}/klasse/{klasseid}/aufheben', [KlassenController::class, 'aufheben'], 'turnier');
    $r->post('/turnier/{id}/klasse/{klasseid}/loeschen', [KlassenController::class, 'loeschen'], 'turnier');
    $r->post('/turnier/{id}/klasse/{klasseid}/auslosen', [AuslosungController::class, 'auslosen'], 'turnier');
    $r->post('/turnier/{id}/klasse/{klasseid}/zuruecknehmen', [AuslosungController::class, 'zuruecknehmen'], 'turnier');
    $r->get('/turnier/{id}/klasse/{klasseid}/protokoll', [AuslosungController::class, 'protokoll'], 'turnier');
    $r->post('/turnier/{id}/auslosung/alle', [AuslosungController::class, 'alle'], 'turnier');
    $r->get('/turnier/{id}/plan', [PlanController::class, 'index'], 'turnier');
    $r->post('/turnier/{id}/plan/verschieben', [PlanController::class, 'verschieben'], 'turnier');
    $r->post('/turnier/{id}/plan/automatisch', [PlanController::class, 'automatisch'], 'turnier');
    $r->post('/turnier/{id}/plan/leeren', [PlanController::class, 'leeren'], 'turnier');
    $r->post('/turnier/{id}/plan/einstellungen', [PlanController::class, 'einstellungen'], 'turnier');
    $r->post('/turnier/{id}/plan/verteilung', [PlanController::class, 'verteilung'], 'turnier');
    $r->post('/turnier/{id}/plan/zurueckstellen', [PlanController::class, 'zurueckstellen'], 'turnier');
    $r->post('/turnier/{id}/plan/schlange-sortieren', [PlanController::class, 'schlangeSortieren'], 'turnier');
    $r->post('/turnier/{id}/tatami/{tatamiid}/sperre', [PlanController::class, 'sperre'], 'turnier');
    $r->post('/turnier/{id}/nachricht', [PlanController::class, 'nachricht'], 'turnier');
    $r->get('/turnier/{id}/ergebnisse', [ErgebnisController::class, 'uebersicht'], 'turnier');
    $r->get('/turnier/{id}/begegnung/{begegnungid}', [ErgebnisController::class, 'begegnung'], 'turnier');
    $r->post('/turnier/{id}/begegnung/{begegnungid}', [ErgebnisController::class, 'begegnungSpeichern'], 'turnier');
    $r->post('/turnier/{id}/begegnung/{begegnungid}/starten', [ErgebnisController::class, 'begegnungStarten'], 'turnier');
    $r->post('/turnier/{id}/begegnung/{begegnungid}/zuruecksetzen', [ErgebnisController::class, 'begegnungZuruecksetzen'], 'turnier');
    $r->get('/turnier/{id}/auftritt/{auftrittid}', [ErgebnisController::class, 'auftritt'], 'turnier');
    $r->post('/turnier/{id}/auftritt/{auftrittid}', [ErgebnisController::class, 'auftrittSpeichern'], 'turnier');
    $r->post('/turnier/{id}/klasse/{klasseid}/stichentscheid', [ErgebnisController::class, 'stichentscheid'], 'turnier');
    $r->post('/turnier/{id}/klasse/{klasseid}/bestaetigen', [ErgebnisController::class, 'bestaetigen'], 'turnier');
    $r->post('/turnier/{id}/klasse/{klasseid}/bestaetigung-aufheben', [ErgebnisController::class, 'bestaetigungAufheben'], 'turnier');
};
