<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung\Http;

use Wettkampf\Durchfuehrung\ErgebnisService;
use Wettkampf\Durchfuehrung\KlassenAnsicht;
use Wettkampf\Turnier\TurnierPhaseService;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Controller;

/**
 * Ergebniseingabe am PC der Wettkampfleitung und Ergebnisübersicht.
 */
final class ErgebnisController extends Controller
{
    public function uebersicht(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $ansicht = new KlassenAnsicht($this->db);
        $klassen = [];
        foreach ($this->db->alle(
            "SELECT k.*,
                    (SELECT COUNT(*) FROM begegnung b JOIN phase p ON p.id = b.phase_id WHERE p.klasse_id = k.id AND b.status NOT IN ('freilos')) AS kaempfe,
                    (SELECT COUNT(*) FROM begegnung b JOIN phase p ON p.id = b.phase_id WHERE p.klasse_id = k.id AND b.status = 'beendet') AS beendet
               FROM klasse k WHERE k.turnier_id = ? AND k.status NOT IN ('offen', 'zusammengelegt') ORDER BY k.sortierung, k.code",
            [$id],
        ) as $k) {
            $k['platzierungen'] = array_map(
                static fn($p) => $p + $ansicht->person((int) $p['start_id']),
                $this->db->alle('SELECT * FROM platzierung WHERE klasse_id = ? ORDER BY platz', [$k['id']]),
            );
            $klassen[] = $k;
        }
        return $this->seite('ergebnis/uebersicht', ['turnier' => $turnier, 'klassen' => $klassen, 'aktiv' => 'ergebnisse']);
    }

    public function begegnung(Request $request, int $id, int $begegnungid): Response
    {
        $turnier = $this->turnier($id);
        $b = $this->db->eine(
            'SELECT b.*, p.klasse_id FROM begegnung b JOIN phase p ON p.id = b.phase_id JOIN klasse k ON k.id = p.klasse_id WHERE b.id = ? AND k.turnier_id = ?',
            [$begegnungid, $id],
        ) ?? throw new NichtGefunden('Begegnung nicht gefunden.');
        $ansicht = (new KlassenAnsicht($this->db))->laden((int) $b['klasse_id']);
        $begegnung = null;
        foreach ($ansicht['phasen'] as $phase) {
            foreach ($phase['begegnungen'] as $x) {
                if ((int) $x['id'] === $begegnungid) {
                    $begegnung = $x + ['phase' => $phase];
                }
            }
        }
        return $this->seite('ergebnis/begegnung', [
            'turnier' => $turnier,
            'klasse' => $ansicht['klasse'],
            'b' => $begegnung,
            'aktiv' => 'ergebnisse',
        ]);
    }

    public function begegnungSpeichern(Request $request, int $id, int $begegnungid): Response
    {
        $this->turnier($id);
        (new TurnierPhaseService($this->db))->ergebnisseErlaubt($id);
        (new ErgebnisService($this->db))->begegnungEintragen($id, $begegnungid, $request->post, true);
        $this->erfolg('Ergebnis gespeichert.');
        return $this->zurKlasse($id, $begegnungid);
    }

    public function begegnungStarten(Request $request, int $id, int $begegnungid): Response
    {
        (new TurnierPhaseService($this->db))->ergebnisseErlaubt($id);
        (new ErgebnisService($this->db))->begegnungStarten($id, $begegnungid);
        return $this->weiter("turnier/$id/begegnung/$begegnungid");
    }

    public function begegnungZuruecksetzen(Request $request, int $id, int $begegnungid): Response
    {
        (new TurnierPhaseService($this->db))->ergebnisseErlaubt($id);
        (new ErgebnisService($this->db))->begegnungZuruecksetzen($id, $begegnungid);
        $this->erfolg('Ergebnis zurückgesetzt.');
        return $this->zurKlasse($id, $begegnungid);
    }

    public function auftritt(Request $request, int $id, int $auftrittid): Response
    {
        $turnier = $this->turnier($id);
        $a = $this->db->eine(
            'SELECT a.*, p.klasse_id, p.bezeichnung AS phase FROM auftritt a JOIN phase p ON p.id = a.phase_id JOIN klasse k ON k.id = p.klasse_id WHERE a.id = ? AND k.turnier_id = ?',
            [$auftrittid, $id],
        ) ?? throw new NichtGefunden('Auftritt nicht gefunden.');
        $ansicht = new KlassenAnsicht($this->db);
        $klasse = $this->db->eine('SELECT * FROM klasse WHERE id = ?', [$a['klasse_id']]);
        return $this->seite('ergebnis/auftritt', [
            'turnier' => $turnier,
            'klasse' => $klasse,
            'a' => $a + $ansicht->person((int) $a['start_id']) + ['wertung_daten' => json_decode((string) $a['wertung'], true) ?: []],
            'aktiv' => 'ergebnisse',
        ]);
    }

    public function auftrittSpeichern(Request $request, int $id, int $auftrittid): Response
    {
        $this->turnier($id);
        (new TurnierPhaseService($this->db))->ergebnisseErlaubt($id);
        (new ErgebnisService($this->db))->auftrittEintragen($id, $auftrittid, $request->post, true);
        $klasseId = (int) $this->db->wert('SELECT p.klasse_id FROM auftritt a JOIN phase p ON p.id = a.phase_id WHERE a.id = ?', [$auftrittid]);
        $this->erfolg('Wertung gespeichert.');
        return $this->weiter("turnier/$id/klasse/$klasseId");
    }

    public function stichentscheid(Request $request, int $id, int $klasseid): Response
    {
        (new TurnierPhaseService($this->db))->ergebnisseErlaubt($id);
        (new ErgebnisService($this->db))->stichentscheid($id, $klasseid, $request->text('art'), (array) $request->post('rang', []));
        $this->erfolg('Stichentscheid gespeichert.');
        return $this->weiter("turnier/$id/klasse/$klasseid");
    }

    public function bestaetigen(Request $request, int $id, int $klasseid): Response
    {
        (new ErgebnisService($this->db))->bestaetigen($id, $klasseid);
        $this->erfolg('Platzierungen bestätigt.');
        return $this->weiter($request->text('zurueck') === 'klasse' ? "turnier/$id/klasse/$klasseid" : "turnier/$id/ergebnisse");
    }

    public function bestaetigungAufheben(Request $request, int $id, int $klasseid): Response
    {
        (new ErgebnisService($this->db))->bestaetigungAufheben($id, $klasseid);
        $this->erfolg('Bestätigung aufgehoben.');
        return $this->weiter("turnier/$id/klasse/$klasseid");
    }

    private function zurKlasse(int $turnierId, int $begegnungId): Response
    {
        $klasseId = (int) $this->db->wert('SELECT p.klasse_id FROM begegnung b JOIN phase p ON p.id = b.phase_id WHERE b.id = ?', [$begegnungId]);
        return $this->weiter("turnier/$turnierId/klasse/$klasseId");
    }
}
