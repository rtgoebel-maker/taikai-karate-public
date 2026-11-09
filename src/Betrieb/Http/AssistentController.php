<?php

declare(strict_types=1);

namespace Wettkampf\Betrieb\Http;

use Wettkampf\Betrieb\TurnierAssistent;
use Wettkampf\Domain\Klasse\Vorlagen;
use Wettkampf\Durchfuehrung\AusrufService;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Meldung\AnmeldungService;
use Wettkampf\Meldung\CoachService;
use Wettkampf\Meldung\RechnungService;

/** Einrichtungs-Assistent eines Turniers (Turnierleitung): ein Schritt je Seite, speichert über die üblichen Formulare. */
final class AssistentController extends Controller
{
    /** Assistent fortsetzen: erster noch offener Schritt. */
    public function index(Request $request, int $id): Response
    {
        $this->turnier($id);
        return $this->weiter("turnier/$id/assistent/" . (new TurnierAssistent($this->db))->ersterOffener($id));
    }

    public function schritt(Request $request, int $id, string $schritt): Response
    {
        $turnier = $this->turnier($id);
        if (!TurnierAssistent::gueltig($schritt)) {
            throw new NichtGefunden('Diesen Schritt gibt es nicht.');
        }
        $daten = [
            'turnier' => $turnier,
            'schritt' => $schritt,
            'stand' => (new TurnierAssistent($this->db))->stand($id),
            'naechster' => TurnierAssistent::naechster($schritt),
            'vorheriger' => TurnierAssistent::vorheriger($schritt),
            'aktiv' => 'uebersicht',
        ];
        $daten += match ($schritt) {
            'klassen' => [
                'vorlagen' => Vorlagen::liste(),
                'klassen' => (int) $this->db->wert("SELECT COUNT(*) FROM klasse WHERE turnier_id = ? AND status <> 'zusammengelegt'", [$id]),
            ],
            'tatamis' => [
                'tatamis' => $this->db->alle('SELECT * FROM tatami WHERE turnier_id = ? ORDER BY nummer', [$id]),
                'akaSeite' => AusrufService::akaSeite($turnier),
                'uhr' => AusrufService::kampfuhr($turnier),
            ],
            'anmeldung' => ['einstellungen' => (new AnmeldungService($this->db))->einstellungen($turnier)],
            'zahlung' => ['zahlung' => (new RechnungService($this->db))->einstellungen($turnier)],
            'portal' => ['coach' => CoachService::einstellungen($turnier)],
            default => [],
        };
        return $this->seite('assistent/schritt', $daten);
    }
}
