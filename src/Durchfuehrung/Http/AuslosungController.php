<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung\Http;

use Wettkampf\Durchfuehrung\AuslosungService;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Controller;

final class AuslosungController extends Controller
{
    public function auslosen(Request $request, int $id, int $klasseid): Response
    {
        $this->turnier($id);
        $seed = $request->text('seed');
        (new AuslosungService($this->db))->auslosen($id, $klasseid, $seed !== '' ? $seed : null);
        $this->erfolg('Klasse ausgelost.');
        return $this->weiter("turnier/$id/klasse/$klasseid");
    }

    public function zuruecknehmen(Request $request, int $id, int $klasseid): Response
    {
        $this->turnier($id);
        (new AuslosungService($this->db))->zuruecknehmen($id, $klasseid);
        $this->erfolg('Auslosung zurückgenommen. Die Klasse ist wieder offen.');
        return $this->weiter("turnier/$id/klasse/$klasseid");
    }

    public function alle(Request $request, int $id): Response
    {
        $this->turnier($id);
        $anzahl = (new AuslosungService($this->db))->alleAuslosen($id);
        $this->erfolg("$anzahl Klassen ausgelost.");
        return $this->weiter("turnier/$id/klassen");
    }

    /** Protokoll aller Auslosungen einer Klasse (Seed, Zeitpunkt, Ergebnis). */
    public function protokoll(Request $request, int $id, int $klasseid): Response
    {
        $turnier = $this->turnier($id);
        $klasse = $this->db->eine('SELECT * FROM klasse WHERE id = ? AND turnier_id = ?', [$klasseid, $id])
            ?? throw new NichtGefunden('Klasse nicht gefunden.');
        $auslosungen = $this->db->alle('SELECT * FROM auslosung WHERE klasse_id = ? ORDER BY id DESC', [$klasseid]);
        return $this->seite('klasse/protokoll', compact('turnier', 'klasse', 'auslosungen') + ['aktiv' => 'klassen']);
    }
}
