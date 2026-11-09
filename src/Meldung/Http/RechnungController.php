<?php

declare(strict_types=1);

namespace Wettkampf\Meldung\Http;

use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Meldung\RechnungService;

/**
 * Startgeld-Rechnungen an die Dojos (Turnierleitung): Übersicht mit Einstellungen und
 * druckbare Rechnungen (alle oder ein Dojo, Drucken bzw. als PDF speichern).
 */
final class RechnungController extends Controller
{
    public function index(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $service = new RechnungService($this->db);
        return $this->seite('rechnung/index', [
            'turnier' => $turnier,
            'einstellungen' => $service->einstellungen($turnier),
            'uebersicht' => $service->uebersicht($id),
            'aktiv' => 'anmeldungen',
        ]);
    }

    public function einstellungen(Request $request, int $id): Response
    {
        $this->turnier($id);
        (new RechnungService($this->db))->einstellungenSpeichern($id, $request->post);
        $this->erfolg('Zahlungs- und Rechnungsangaben gespeichert.');
        return $this->weiterNach($request, "turnier/$id/rechnungen");
    }

    /** Alle Rechnungen bzw. ?verein=ID nur die eines Dojos. */
    public function druck(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $service = new RechnungService($this->db);
        $verein = $request->get('verein');
        return $this->seite('rechnung/druck', [
            'turnier' => $turnier,
            'einstellungen' => $service->einstellungen($turnier),
            'rechnungen' => $service->rechnungen($id, is_string($verein) && ctype_digit($verein) ? (int) $verein : null),
        ]);
    }
}
