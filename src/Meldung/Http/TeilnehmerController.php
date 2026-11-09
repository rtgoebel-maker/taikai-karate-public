<?php

declare(strict_types=1);

namespace Wettkampf\Meldung\Http;

use Wettkampf\Durchfuehrung\AusrufService;
use Wettkampf\Durchfuehrung\ErgebnisService;
use Wettkampf\Turnier\StammdatenService;
use Wettkampf\Meldung\ZuordnungService;
use Wettkampf\Domain\Alter;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Controller;

final class TeilnehmerController extends Controller
{
    public function index(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $suche = $request->text('q');
        $vereinId = $request->zahl('verein');

        $sql = "SELECT t.*, a.vorname, a.nachname, a.geschlecht, a.geburtsdatum, a.graduierung, a.verein_id, v.name AS verein
                  FROM teilnahme t
                  JOIN athlet a ON a.id = t.athlet_id
                  LEFT JOIN verein v ON v.id = a.verein_id
                 WHERE t.turnier_id = :turnier";
        $params = ['turnier' => $id];
        if ($suche !== '') {
            $sql .= ' AND (LOWER(a.vorname) LIKE :q OR LOWER(a.nachname) LIKE :q OR LOWER(v.name) LIKE :q)';
            $params['q'] = '%' . mb_strtolower($suche) . '%';
        }
        if ($vereinId) {
            $sql .= ' AND a.verein_id = :verein';
            $params['verein'] = $vereinId;
        }
        $status = $request->text('status');
        if (isset(StammdatenService::STATUS[$status])) {
            $sql .= ' AND t.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY a.nachname, a.vorname';
        $teilnehmer = $this->db->alle($sql, $params);

        $klassen = [];
        foreach ($this->db->alle(
            "SELECT s.teilnahme_id, k.id, k.code FROM start s JOIN klasse k ON k.id = s.klasse_id
              WHERE k.turnier_id = ? AND s.teilnahme_id IS NOT NULL AND s.status <> 'zurueckgezogen' ORDER BY k.sortierung, k.code",
            [$id],
        ) as $s) {
            $klassen[$s['teilnahme_id']][] = $s;
        }

        foreach ($teilnehmer as &$t) {
            $t['alter'] = $t['geburtsdatum'] ? Alter::amStichtag($t['geburtsdatum'], $turnier['datum']) : $t['alter_manuell'];
            $t['klassen'] = $klassen[$t['id']] ?? [];
        }
        unset($t);

        $vereine = $this->db->alle(
            'SELECT DISTINCT v.id, v.name FROM verein v JOIN athlet a ON a.verein_id = v.id JOIN teilnahme t ON t.athlet_id = a.id
              WHERE t.turnier_id = ? ORDER BY v.name',
            [$id],
        );
        return $this->seite('teilnehmer/index', compact('turnier', 'teilnehmer', 'vereine', 'suche', 'vereinId', 'status') + ['aktiv' => 'teilnehmer']);
    }

    /** Leitung kennzeichnet einen Teilnehmer als „nicht erschienen“ (optional Klassen ohne Ergebnis neu auslosen). */
    public function nichtErschienen(Request $request, int $id, int $teilnahmeid): Response
    {
        $this->turnier($id);
        $neuAuslosen = array_map('intval', (array) $request->post('neu_auslosen', []));
        $this->erfolg((new AusrufService($this->db))->teilnehmerNichtErschienen($id, $teilnahmeid, 'Leitung', $neuAuslosen));
        return $request->text('zurueck') === 'liste'
            ? $this->weiter("turnier/$id/teilnehmer", array_filter(['q' => $request->text('q'), 'status' => $request->text('status')]))
            : $this->weiter("turnier/$id/teilnehmer/$teilnahmeid");
    }

    public function neu(Request $request, int $id): Response
    {
        return $this->formular($id, null, $request->zahl('verein'));
    }

    public function bearbeiten(Request $request, int $id, int $teilnahmeid): Response
    {
        return $this->formular($id, $teilnahmeid);
    }

    public function speichern(Request $request, int $id): Response
    {
        $this->turnier($id);
        $daten = $request->post;
        $daten['disziplinen'] = (array) $request->post('disziplinen', []);
        $teilnahmeId = $request->zahl('teilnahme_id');
        $neueId = (new StammdatenService($this->db))->teilnehmerSpeichern($id, $daten, $teilnahmeId);
        $ergebnis = (new ZuordnungService($this->db))->teilnehmerZuordnen($id, $neueId);
        if (in_array($daten['status'] ?? '', StammdatenService::AUSGEFALLEN, true)) {
            $kampflos = (new ErgebnisService($this->db))->ausfaelleVerarbeiten($id, $neueId);
            if ($kampflos > 0) {
                $ergebnis[] = "$kampflos offene Kämpfe/Vorträge wurden kampflos (Kiken) gewertet.";
            }
        }
        $this->erfolg('Teilnehmer gespeichert.' . ($ergebnis ? ' ' . implode(' ', $ergebnis) : ''));
        if ($request->post('weiter') === 'neu') {
            return $this->weiter("turnier/$id/teilnehmer/neu", ['verein' => $daten['verein_id'] ?? null]);
        }
        return $this->weiter("turnier/$id/teilnehmer");
    }

    public function loeschen(Request $request, int $id, int $teilnahmeid): Response
    {
        (new StammdatenService($this->db))->teilnehmerLoeschen($id, $teilnahmeid);
        $this->erfolg('Teilnehmer gelöscht.');
        return $this->weiter("turnier/$id/teilnehmer");
    }

    private function formular(int $turnierId, ?int $teilnahmeId, ?int $vorgabeVerein = null): Response
    {
        $turnier = $this->turnier($turnierId);
        $werte = [
            'vorname' => '', 'nachname' => '', 'geschlecht' => '', 'geburtsdatum' => '', 'alter_manuell' => '',
            'graduierung' => '', 'verein_id' => $vorgabeVerein ?? '', 'disziplinen' => '', 'status' => 'gemeldet',
            'ausweis_geprueft' => 0, 'nachmeldung' => 0, 'vermerk' => '',
        ];
        $starts = [];
        if ($teilnahmeId !== null) {
            $werte = $this->db->eine(
                'SELECT t.*, a.vorname, a.nachname, a.geschlecht, a.geburtsdatum, a.graduierung, a.verein_id
                   FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id WHERE t.id = ? AND t.turnier_id = ?',
                [$teilnahmeId, $turnierId],
            ) ?? throw new \Wettkampf\Kern\Http\NichtGefunden('Teilnehmer nicht gefunden.');
            $starts = $this->db->alle(
                'SELECT s.*, k.code, k.bezeichnung, k.status AS klasse_status FROM start s JOIN klasse k ON k.id = s.klasse_id WHERE s.teilnahme_id = ? ORDER BY k.code',
                [$teilnahmeId],
            );
            $auslosung = new \Wettkampf\Durchfuehrung\AuslosungService($this->db);
            foreach ($starts as &$s) {
                // ausgelost, aber noch ohne Ergebnis → kann ohne den Starter neu ausgelost werden
                $s['neu_auslosbar'] = !in_array($s['klasse_status'], ['offen', 'zusammengelegt'], true) && !$auslosung->hatErgebnisse((int) $s['klasse_id']);
            }
            unset($s);
        }
        $vereine = $this->db->alle('SELECT id, name FROM verein ORDER BY name');
        return $this->seite('teilnehmer/formular', [
            'turnier' => $turnier,
            'teilnahmeId' => $teilnahmeId,
            'werte' => $werte,
            'vereine' => $vereine,
            'starts' => $starts,
            'aktiv' => 'teilnehmer',
        ]);
    }
}
