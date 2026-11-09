<?php

declare(strict_types=1);

namespace Wettkampf\Meldung\Http;

use Wettkampf\Meldung\TeamService;
use Wettkampf\Meldung\ZuordnungService;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Controller;

final class TeamController extends Controller
{
    public function index(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $teams = $this->db->alle(
            "SELECT tm.*, v.name AS verein,
                    (SELECT GROUP_CONCAT(k.code) FROM start s JOIN klasse k ON k.id = s.klasse_id
                      WHERE s.team_id = tm.id AND s.status <> 'zurueckgezogen') AS klassen
               FROM team tm LEFT JOIN verein v ON v.id = tm.verein_id
              WHERE tm.turnier_id = ? ORDER BY tm.disziplin, tm.name",
            [$id],
        );
        $mitglieder = [];
        foreach ($this->db->alle(
            'SELECT m.*, a.vorname, a.nachname, a.geschlecht, a.graduierung, a.verein_id AS heimat_id, v.name AS verein
               FROM team_mitglied m JOIN teilnahme t ON t.id = m.teilnahme_id JOIN athlet a ON a.id = t.athlet_id
               LEFT JOIN verein v ON v.id = m.verein_id
               JOIN team tm ON tm.id = m.team_id WHERE tm.turnier_id = ? ORDER BY m.rolle DESC, a.nachname',
            [$id],
        ) as $m) {
            $mitglieder[$m['team_id']][] = $m;
        }
        $teilnehmer = $this->db->alle(
            "SELECT t.id, a.vorname, a.nachname, v.name AS verein FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id
               LEFT JOIN verein v ON v.id = a.verein_id
              WHERE t.turnier_id = ? AND t.status NOT IN ('abgemeldet', 'nicht_erschienen') ORDER BY v.name, a.nachname, a.vorname",
            [$id],
        );
        $vereine = $this->db->alle('SELECT id, name FROM verein ORDER BY name');
        return $this->seite('team/index', compact('turnier', 'teams', 'mitglieder', 'teilnehmer', 'vereine') + ['aktiv' => 'teams']);
    }

    public function speichern(Request $request, int $id): Response
    {
        $this->turnier($id);
        $service = new TeamService($this->db);
        $teamId = $service->teamSpeichern($id, $request->text('name'), $request->text('disziplin'), $request->zahl('verein_id'), $request->zahl('team_id'));
        $hinweise = (new ZuordnungService($this->db))->teamZuordnen($id, $teamId);
        $this->erfolg('Team gespeichert.' . ($hinweise ? ' ' . implode(' ', $hinweise) : ''));
        return $this->weiter("turnier/$id/teams");
    }

    public function mitglied(Request $request, int $id, int $teamid): Response
    {
        $this->turnier($id);
        (new TeamService($this->db))->mitgliedHinzufuegen($id, $teamid, (int) $request->zahl('teilnahme_id'), $request->text('rolle', 'stamm'), $request->zahl('verein_id'));
        $hinweise = (new ZuordnungService($this->db))->teamZuordnen($id, $teamid);
        $this->erfolg('Mitglied hinzugefügt.' . ($hinweise ? ' ' . implode(' ', $hinweise) : ''));
        return $this->weiter("turnier/$id/teams");
    }

    public function mitgliedEntfernen(Request $request, int $id, int $teamid, int $mitgliedid): Response
    {
        (new TeamService($this->db))->mitgliedEntfernen($id, $teamid, $mitgliedid);
        (new ZuordnungService($this->db))->teamZuordnen($id, $teamid);
        $this->erfolg('Mitglied entfernt.');
        return $this->weiter("turnier/$id/teams");
    }

    public function loeschen(Request $request, int $id, int $teamid): Response
    {
        (new TeamService($this->db))->teamLoeschen($id, $teamid);
        $this->erfolg('Team gelöscht.');
        return $this->weiter("turnier/$id/teams");
    }
}
