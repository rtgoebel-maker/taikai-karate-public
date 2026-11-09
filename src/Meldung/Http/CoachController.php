<?php

declare(strict_types=1);

namespace Wettkampf\Meldung\Http;

use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Meldung\AnmeldungService;
use Wettkampf\Meldung\CoachService;
use Wettkampf\Meldung\DojoZugangBindung;

/**
 * Coach-Bereich: „Meine Meldungen“ und das Coaching-Portal je Turnier (Starter live, Ergebnisse,
 * Korrekturanträge, Startgeld). OWASP A01: alles nur für das Dojo des Kontos; was die
 * Turnierleitung nicht freigibt, gilt als nicht gefunden.
 */
final class CoachController extends Controller
{
    public function index(Request $request): Response
    {
        // Coach-Konto mit Dojo bzw. selbst angelegter Zugang (noch ohne Dojo): eigene Meldungen
        $vereinId = (int) Session::coachVerein();
        $kontoId = (int) (Session::benutzer()['id'] ?? 0);
        $konto = $this->db->eine('SELECT email, ablauf_am FROM benutzer WHERE id = ?', [$kontoId]) ?? ['email' => '', 'ablauf_am' => null];
        $service = new AnmeldungService($this->db);
        $bindung = new DojoZugangBindung($this->db);
        $nur = $bindung->turniere($kontoId); // null = alle Turniere
        $imRahmen = static fn(int $turnierId) => $nur === null || $nur === [] || in_array($turnierId, $nur, true);
        $offen = array_values(array_filter(
            $this->db->alle("SELECT * FROM turnier WHERE status = 'meldephase' ORDER BY datum, id"),
            static fn(array $t) => $service->geschlossenGrund($t) === null && $imRahmen((int) $t['id']),
        ));
        foreach ($offen as &$t) {
            $t['anmeldelink'] = url('melden/' . $service->anmeldeSchluessel((int) $t['id']));
        }
        unset($t);
        $meldungen = $this->db->alle(
            'SELECT a.*, t.name AS turnier, t.datum, t.status AS phase, t.oeffentlich, t.einstellungen
               FROM anmeldung a JOIN turnier t ON t.id = a.turnier_id
              WHERE a.verein_id = ? OR a.uuid IN (SELECT anmeldung_uuid FROM konto_anmeldung WHERE benutzer_id = ?) OR LOWER(a.email) = ?
              ORDER BY t.datum DESC, a.eingereicht_am DESC',
            [$vereinId, $kontoId, (string) $konto['email']],
        );
        $meldungen = array_values(array_filter($meldungen, static fn(array $m) => $imRahmen((int) $m['turnier_id'])));
        foreach ($meldungen as &$m) {
            $daten = json_decode((string) $m['daten'], true);
            $m['teilnehmer'] = is_array($daten) ? count($daten['teilnehmer'] ?? []) : 0;
            $m['teams'] = is_array($daten) ? count($daten['teams'] ?? []) : 0;
            $m['stand'] = $service->stand($m, ['einstellungen' => $m['einstellungen']]);
            unset($m['daten'], $m['einstellungen'], $m['uebernahme']);
        }
        unset($m);
        return $this->seite('coach/index', [
            'verein' => $vereinId > 0 ? $this->vereinName($vereinId) : null,
            'ablauf' => $konto['ablauf_am'],
            'offen' => $offen,
            'meldungen' => $meldungen,
            'portal' => $vereinId > 0 ? array_values(array_filter((new CoachService($this->db))->turniere($vereinId), static fn(array $t) => $imRahmen((int) $t['id']))) : [],
            'gebundenAn' => $nur !== null && $nur !== [] ? $bindung->namen($kontoId) : null,
        ]);
    }

    /** Einstieg ins Portal eines Turniers: der erste freigegebene Bereich. */
    public function portal(Request $request, int $id): Response
    {
        $t = $this->portalTurnier($id, (int) Session::coachVerein());
        $bereich = array_key_first(array_filter($t['portal']['bereiche'])) ?? 'starter';
        return $this->weiter("coach/turnier/$id/$bereich");
    }

    public function starter(Request $request, int $id): Response
    {
        $vereinId = (int) Session::coachVerein();
        $t = $this->portalTurnier($id, $vereinId, 'starter');
        return $this->seite('coach/starter', $this->rahmen($t, 'starter') + ['starter' => $this->coach()->starterLive($id, $vereinId)]);
    }

    /** Revision des Turniers (automatisches Aktualisieren von „Meine Starter live“). */
    public function revision(Request $request, int $id): Response
    {
        $t = $this->portalTurnier($id, (int) Session::coachVerein(), 'starter');
        return Response::json(['revision' => (int) $t['revision']]);
    }

    public function ergebnisse(Request $request, int $id): Response
    {
        $vereinId = (int) Session::coachVerein();
        $t = $this->portalTurnier($id, $vereinId, 'ergebnisse');
        return $this->seite('coach/ergebnisse', $this->rahmen($t, 'ergebnisse') + $this->coach()->ergebnisse($id, $vereinId));
    }

    public function antraege(Request $request, int $id): Response
    {
        $vereinId = (int) Session::coachVerein();
        $t = $this->portalTurnier($id, $vereinId, 'antraege');
        return $this->seite('coach/antraege', $this->rahmen($t, 'antraege') + [
            'antraege' => $this->coach()->antraege($id, $vereinId),
            'teilnehmer' => $this->coach()->teilnehmer($id, $vereinId),
        ]);
    }

    public function antragStellen(Request $request, int $id): Response
    {
        $vereinId = (int) Session::coachVerein();
        $this->portalTurnier($id, $vereinId, 'antraege');
        $this->coach()->antragStellen($id, $vereinId, $request->post, Session::akteur());
        $this->erfolg('Korrekturantrag gestellt – die Turnierleitung meldet sich hier mit ihrer Antwort.');
        return $this->weiter("coach/turnier/$id/antraege");
    }

    /** Druckbare Startgeld-Übersicht (Drucken bzw. als PDF speichern). */
    public function startgeld(Request $request, int $id): Response
    {
        $vereinId = (int) Session::coachVerein();
        $t = $this->portalTurnier($id, $vereinId, 'startgeld');
        return $this->seite('coach/startgeld', [
            'turnier' => $t,
            'verein' => $this->vereinName($vereinId),
            'startgeld' => $this->coach()->startgeld($id, $vereinId),
            'hinweis' => (new AnmeldungService($this->db))->einstellungen($t)['hinweis'],
        ]);
    }

    // --- Turnierleitung ---------------------------------------------------------------

    /** Einstellung „Coaching-Portal“ (Turnier → Einstellungen). */
    public function einstellungen(Request $request, int $id): Response
    {
        $this->turnier($id);
        $this->coach()->einstellungenSpeichern($id, $request->post);
        $this->erfolg('Coaching-Portal gespeichert.');
        return $this->weiterNach($request, "turnier/$id/einstellungen");
    }

    public function korrekturen(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        return $this->seite('coach/korrekturen', [
            'turnier' => $turnier,
            'antraege' => $this->coach()->antraege($id),
            'portal' => CoachService::einstellungen($turnier),
            'aktiv' => 'anmeldungen',
        ]);
    }

    public function korrekturBearbeiten(Request $request, int $id, int $antragid): Response
    {
        $this->turnier($id);
        $this->coach()->antragBearbeiten($id, $antragid, $request->text('status'), $request->text('antwort'), Session::akteur());
        $this->erfolg('Korrekturantrag gespeichert.');
        return $this->weiter("turnier/$id/korrekturen");
    }

    /**
     * @param array<string, mixed> $t
     * @return array<string, mixed>
     */
    private function rahmen(array $t, string $bereich): array
    {
        // bewusst nicht „turnier“: das Layout zeigt sonst das Turniermenü der Leitung
        return ['portal' => $t, 'bereich' => $bereich, 'verein' => $this->vereinName((int) Session::coachVerein())];
    }

    /**
     * Portal eines Turniers – nur, wenn der Zugang für dieses Turnier gilt (DojoZugangBindung).
     *
     * @return array<string, mixed>
     */
    private function portalTurnier(int $turnierId, int $vereinId, ?string $bereich = null): array
    {
        if (!(new DojoZugangBindung($this->db))->erlaubt((int) (Session::benutzer()['id'] ?? 0), $turnierId)) {
            throw new NichtGefunden('Dieser Zugang gilt nur für das Turnier, für das er angelegt wurde.');
        }
        return $this->coach()->turnier($turnierId, $vereinId, $bereich);
    }

    private function vereinName(int $vereinId): string
    {
        return (string) $this->db->wert('SELECT name FROM verein WHERE id = ?', [$vereinId]);
    }

    private function coach(): CoachService
    {
        return new CoachService($this->db);
    }
}
