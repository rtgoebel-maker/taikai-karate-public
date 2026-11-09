<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung\Http;

use Wettkampf\Durchfuehrung\NachrichtService;
use Wettkampf\Durchfuehrung\PlanService;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Controller;

final class PlanController extends Controller
{
    public function index(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $service = new PlanService($this->db);
        $tatamis = $this->db->alle('SELECT * FROM tatami WHERE turnier_id = ? ORDER BY nummer', [$id]);
        $einstellungen = json_decode((string) $turnier['einstellungen'], true) ?: [];
        return $this->seite('plan/index', [
            'turnier' => $turnier,
            'tatamis' => $tatamis,
            'plan' => $service->plan($id),
            'beginn' => $einstellungen['beginn'] ?? '10:00',
            'minuten' => $service->minutenJeEinheit($id),
            'offeneKlassen' => (int) $this->db->wert(
                "SELECT COUNT(*) FROM klasse k WHERE k.turnier_id = ? AND k.status = 'offen'
                    AND EXISTS (SELECT 1 FROM start s WHERE s.klasse_id = k.id AND s.status <> 'zurueckgezogen')",
                [$id],
            ),
            'verteilung' => $service->verteilung($id),
            'nachrichten' => (new NachrichtService($this->db))->liste($id, 12),
            'aktiv' => 'plan',
        ]);
    }

    public function verteilung(Request $request, int $id): Response
    {
        $this->turnier($id);
        $service = new PlanService($this->db);
        $service->verteilungSpeichern($id, $request->text('modus', 'dynamisch'), $request->post('getrennt') === '1');
        $this->erfolg($service->verteilung($id)['modus'] === 'dynamisch'
            ? 'Dynamische Verteilung: Jede Tatami bekommt die nächste Gruppe, sobald sie frei wird.'
            : 'Fester Plan: Gruppen bleiben auf der zugeordneten Tatami.');
        return $this->weiter("turnier/$id/plan");
    }

    /** Dynamisch: alle noch nicht begonnenen Gruppen zurück in die Warteschlange. */
    public function zurueckstellen(Request $request, int $id): Response
    {
        $this->turnier($id);
        $anzahl = (new PlanService($this->db))->nichtBegonneneZurueck($id);
        $this->erfolg("$anzahl nicht begonnene Gruppen in die Warteschlange gestellt – freie Tatamis haben sofort die nächste bekommen.");
        return $this->weiter("turnier/$id/plan");
    }

    public function sperre(Request $request, int $id, int $tatamiid): Response
    {
        $this->turnier($id);
        $sperren = $request->post('an') === '1';
        $zurueck = (new PlanService($this->db))->tatamiSperren($id, $tatamiid, $sperren, $request->text('grund'));
        $name = (string) $this->db->wert('SELECT bezeichnung FROM tatami WHERE id = ?', [$tatamiid]);
        $this->erfolg($sperren
            ? "$name gesperrt – keine neuen Gruppen" . ($zurueck > 0 ? "; $zurueck nicht begonnene Gruppen zurück in die Warteschlange." : '.')
            : "$name freigegeben.");
        return $this->weiter("turnier/$id/plan");
    }

    /** Nachricht an die Tischbesatzung einer bzw. aller Tatamis. */
    public function nachricht(Request $request, int $id): Response
    {
        $this->turnier($id);
        $tatami = $request->text('tatami_id');
        $anzahl = (new NachrichtService($this->db))->senden($id, $tatami === '' ? null : (int) $tatami, $request->text('text'), Session::akteur());
        $this->erfolg($anzahl === 1 ? 'Nachricht gesendet.' : "Nachricht an $anzahl Tatamis gesendet.");
        return $this->weiter("turnier/$id/plan");
    }

    /** Verschieben per Formular (ohne JavaScript) oder JSON (Drag & Drop). */
    public function verschieben(Request $request, int $id): Response
    {
        $this->turnier($id);
        $daten = $request->istJson() ? $request->json() : $request->post;
        $tatami = isset($daten['tatami_id']) && $daten['tatami_id'] !== '' && (int) $daten['tatami_id'] !== 0 ? (int) $daten['tatami_id'] : null;
        $position = isset($daten['position']) && $daten['position'] !== '' ? (int) $daten['position'] : null;
        (new PlanService($this->db))->verschieben($id, (int) $daten['phase_id'], $tatami, $position);
        return $request->istJson() ? Response::json(['ok' => true]) : $this->weiter("turnier/$id/plan");
    }

    public function automatisch(Request $request, int $id): Response
    {
        $this->turnier($id);
        $anzahl = (new PlanService($this->db))->automatischVerteilen($id, $request->post('getrennt') === '1');
        $this->erfolg("$anzahl Phasen automatisch verteilt.");
        return $this->weiter("turnier/$id/plan");
    }

    public function leeren(Request $request, int $id): Response
    {
        $this->turnier($id);
        $anzahl = (new PlanService($this->db))->leeren($id);
        $this->erfolg("$anzahl nicht begonnene Gruppen von den Tatamis genommen – laufende und beendete bleiben.");
        return $this->weiter("turnier/$id/plan");
    }

    public function schlangeSortieren(Request $request, int $id): Response
    {
        $this->turnier($id);
        (new PlanService($this->db))->schlangeSortieren($id);
        $this->erfolg('Warteschlange nach Klassenreihenfolge sortiert: Kata vor Kumite, jüngere bzw. niedrigere Klassen zuerst.');
        return $this->weiter("turnier/$id/plan");
    }

    public function einstellungen(Request $request, int $id): Response
    {
        $this->turnier($id);
        (new PlanService($this->db))->einstellungenSpeichern($id, $request->post);
        $this->erfolg('Zeitplan-Einstellungen gespeichert.');
        return $this->weiter("turnier/$id/plan");
    }
}
