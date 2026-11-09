<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung\Http;

use Wettkampf\Domain\Wertung\Wertung;
use Wettkampf\Durchfuehrung\AuslosungService;
use Wettkampf\Durchfuehrung\KlassenAnsicht;
use Wettkampf\Durchfuehrung\KlassenService;
use Wettkampf\Meldung\ZuordnungService;
use Wettkampf\Domain\Alter;
use Wettkampf\Domain\Klasse\Vorlagen;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Controller;

final class KlassenController extends Controller
{
    public function index(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $service = new KlassenService($this->db);
        $klassen = $service->klassenMitAnzahl($id);
        $alle = count($klassen);
        $filter = $request->text('disziplin');
        if ($filter !== '') {
            $klassen = array_values(array_filter($klassen, static fn($k) => $k['disziplin'] === $filter));
        }
        // Besetzung: findet statt / fällt aus (unter Mindestanzahl) / leer
        $besetzung = $request->text('besetzung');
        $klassen = array_values(array_filter($klassen, static function ($k) use ($besetzung) {
            $n = (int) $k['anzahl'];
            return match ($besetzung) {
                'besetzt' => $n > 0,
                'statt' => $k['status'] !== 'zusammengelegt' && KlassenService::findetStatt($k, $n),
                'faellt_aus' => $k['status'] !== 'zusammengelegt' && $n > 0 && !KlassenService::findetStatt($k, $n),
                'leer' => $k['status'] !== 'zusammengelegt' && $n === 0,
                default => true,
            };
        }));
        $ansicht = $request->text('ansicht') === 'tabelle' ? 'tabelle' : 'karten';
        $tatamis = [];
        foreach ($this->db->alle(
            'SELECT p.klasse_id, GROUP_CONCAT(DISTINCT t.nummer) AS nummern FROM phase p JOIN tatami t ON t.id = p.tatami_id
              JOIN klasse k ON k.id = p.klasse_id WHERE k.turnier_id = ? GROUP BY p.klasse_id',
            [$id],
        ) as $z) {
            $tatamis[(int) $z['klasse_id']] = $z['nummern'];
        }
        return $this->seite('klasse/index', [
            'turnier' => $turnier,
            'klassen' => $klassen,
            'alle' => $alle,
            'filter' => $filter,
            'besetzung' => $besetzung,
            'ansicht' => $ansicht,
            'tatamis' => $tatamis,
            'vorschlaege' => $service->vorschlaege($id),
            'vorlagen' => Vorlagen::liste(),
            'aktiv' => 'klassen',
        ]);
    }

    public function vorlage(Request $request, int $id): Response
    {
        $this->turnier($id);
        $anzahl = (new KlassenService($this->db))->vorlageLaden($id, $request->text('vorlage'));
        $zuordnung = (new ZuordnungService($this->db))->alleZuordnen($id);
        $this->erfolg("$anzahl Klassen aus der Vorlage angelegt. {$zuordnung['starts']} Starts zugeordnet.");
        return $this->weiterNach($request, "turnier/$id/klassen");
    }

    public function zuordnen(Request $request, int $id): Response
    {
        $this->turnier($id);
        $ergebnis = (new ZuordnungService($this->db))->alleZuordnen($id);
        $this->erfolg("Zuordnung aktualisiert: {$ergebnis['starts']} Starts." . ($ergebnis['hinweise'] ? ' ' . count($ergebnis['hinweise']) . ' Hinweise – siehe Übersicht.' : ''));
        return $this->weiter("turnier/$id/klassen");
    }

    public function neu(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $werte = [
            'code' => '', 'bezeichnung' => '', 'disziplin' => $request->text('disziplin', 'kata'), 'kumite_form' => '',
            'geschlecht' => 'w', 'alter_min' => '', 'alter_max' => '', 'grad_min' => '', 'grad_max' => '', 'erlaubte_kata' => '',
            'exklusiv_gruppe' => '', 'modus' => 'auto', 'anzahl_pools' => 1, 'aufsteiger_pro_pool' => 2, 'finale_art' => 'ko',
            'finalisten' => 4, 'wertung' => 'flaggen', 'wertung_finale' => '', 'kampfrichter' => 5, 'dojo_trennung' => 1,
            'gleiche_flaeche' => 0, 'optionen' => null, 'status' => 'offen',
            'sortierung' => 10 + (int) $this->db->wert('SELECT MAX(sortierung) FROM klasse WHERE turnier_id = ?', [$id]),
        ];
        return $this->seite('klasse/formular', ['turnier' => $turnier, 'klasse' => null, 'werte' => $werte, 'regeln' => [], 'aktiv' => 'klassen']);
    }

    public function bearbeiten(Request $request, int $id, int $klasseid): Response
    {
        $turnier = $this->turnier($id);
        $klasse = $this->klasse($id, $klasseid);
        $regeln = $this->db->alle('SELECT * FROM klasse_regel WHERE klasse_id = ? ORDER BY id', [$klasseid]);
        return $this->seite('klasse/formular', ['turnier' => $turnier, 'klasse' => $klasse, 'werte' => $klasse, 'regeln' => $regeln, 'aktiv' => 'klassen']);
    }

    public function speichern(Request $request, int $id): Response
    {
        $this->turnier($id);
        $daten = $request->post;
        $daten['optionen'] = [
            'punkte_min' => (float) str_replace(',', '.', (string) ($daten['punkte_min'] ?? Wertung::PUNKTE_MIN)),
            'punkte_max' => (float) str_replace(',', '.', (string) ($daten['punkte_max'] ?? Wertung::PUNKTE_MAX)),
            'punkte_schritt' => (float) str_replace(',', '.', (string) ($daten['punkte_schritt'] ?? '0.1')),
            'streichung' => !empty($daten['streichung']),
            'freilos_platz' => !empty($daten['freilos_platz']),
            'team_kaempfe' => in_array((int) ($daten['team_kaempfe'] ?? 3), [3, 5, 7], true) ? (int) $daten['team_kaempfe'] : 3,
        ];
        $daten['regeln'] = array_values(array_filter((array) ($daten['regeln'] ?? []), static fn($r) => ($r['ab_runde'] ?? '') !== ''));
        $klasseId = $request->zahl('klasse_id');
        $neueId = (new KlassenService($this->db))->klasseSpeichern($id, $daten, $klasseId);
        (new ZuordnungService($this->db))->alleZuordnen($id);
        $this->erfolg('Klasse gespeichert.');
        return $this->weiter("turnier/$id/klasse/$neueId");
    }

    public function detail(Request $request, int $id, int $klasseid): Response
    {
        $turnier = $this->turnier($id);
        $klasse = $this->klasse($id, $klasseid);
        $starter = $this->starter($klasseid, $turnier['datum']);
        $regeln = $this->db->alle('SELECT * FROM klasse_regel WHERE klasse_id = ? ORDER BY id', [$klasseid]);
        $quellen = $this->db->alle(
            "SELECT k.*, z.grund, z.zustimmung, z.erstellt_am AS zusammengelegt_am, z.erstellt_von
               FROM klasse k LEFT JOIN zusammenlegung z ON z.quell_klasse_id = k.id AND z.aufgehoben_am IS NULL
              WHERE k.zusammengelegt_in = ?",
            [$klasseid],
        );
        $ziel = $klasse['zusammengelegt_in'] ? $this->klasse($id, (int) $klasse['zusammengelegt_in']) : null;

        $istTeam = str_ends_with($klasse['disziplin'], '_team');
        $auswahl = $istTeam
            ? $this->db->alle("SELECT id, name AS anzeige FROM team WHERE turnier_id = ? AND disziplin = ? ORDER BY name", [$id, $klasse['disziplin']])
            : array_map(
                // Texte in PHP verketten: „||“ ist in MySQL ein logisches ODER
                static fn($t) => ['id' => $t['id'], 'anzeige' => "{$t['nachname']}, {$t['vorname']} (" . ($t['verein'] ?? '–') . ')'],
                $this->db->alle(
                    "SELECT t.id, a.nachname, a.vorname, v.name AS verein
                       FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id LEFT JOIN verein v ON v.id = a.verein_id
                      WHERE t.turnier_id = ? AND t.status NOT IN ('abgemeldet', 'nicht_erschienen') ORDER BY a.nachname, a.vorname",
                    [$id],
                ),
            );
        $andere = array_values(array_filter(
            (new KlassenService($this->db))->klassenMitAnzahl($id),
            static fn($k) => $k['status'] === 'offen' && (int) $k['id'] !== $klasseid && $k['disziplin'] === $klasse['disziplin'],
        ));

        $ansicht = in_array($klasse['status'], ['offen', 'zusammengelegt'], true) ? null : (new KlassenAnsicht($this->db))->laden($klasseid);
        $hatErgebnisse = $ansicht !== null && (new AuslosungService($this->db))->hatErgebnisse($klasseid);

        return $this->seite('klasse/detail', compact('turnier', 'klasse', 'starter', 'regeln', 'quellen', 'ziel', 'auswahl', 'andere', 'istTeam', 'ansicht', 'hatErgebnisse')
            + ['aktiv' => 'klassen']);
    }

    public function starterSetzen(Request $request, int $id, int $klasseid): Response
    {
        $klasse = $this->klasse($id, $klasseid);
        $istTeam = str_ends_with($klasse['disziplin'], '_team');
        (new KlassenService($this->db))->startSetzen($id, $klasseid, $istTeam ? null : $request->zahl('wer'), $istTeam ? $request->zahl('wer') : null);
        $this->erfolg('Starter hinzugefügt.');
        return $this->weiter("turnier/$id/klasse/$klasseid");
    }

    public function starterEntfernen(Request $request, int $id, int $klasseid, int $startid): Response
    {
        (new KlassenService($this->db))->startEntfernen($id, $startid);
        $this->erfolg('Starter aus der Klasse entfernt. Hinweis: Bei erneuter automatischer Zuordnung wird er wieder eingeteilt, solange die Meldung besteht.');
        return $this->weiter("turnier/$id/klasse/$klasseid");
    }

    public function zusammenlegen(Request $request, int $id): Response
    {
        $this->turnier($id);
        $ziel = (int) $request->zahl('ziel');
        $quellen = array_map('intval', (array) $request->post('quellen', []));
        (new KlassenService($this->db))->zusammenlegen($id, $ziel, $quellen, $request->text('grund'), $request->text('zustimmung'));
        $this->erfolg('Klassen zusammengelegt.');
        return $this->weiter("turnier/$id/klasse/$ziel");
    }

    public function aufheben(Request $request, int $id, int $klasseid): Response
    {
        $klasse = $this->klasse($id, $klasseid);
        (new KlassenService($this->db))->zusammenlegungAufheben($id, $klasseid);
        $this->erfolg('Zusammenlegung aufgehoben.');
        return $this->weiter("turnier/$id/klasse/" . ($klasse['zusammengelegt_in'] ?? $klasseid));
    }

    public function loeschen(Request $request, int $id, int $klasseid): Response
    {
        (new KlassenService($this->db))->klasseLoeschen($id, $klasseid);
        $this->erfolg('Klasse gelöscht.');
        return $this->weiter("turnier/$id/klassen");
    }

    /** @return array<string, mixed> */
    private function klasse(int $turnierId, int $klasseId): array
    {
        $klasse = $this->db->eine('SELECT * FROM klasse WHERE id = ? AND turnier_id = ?', [$klasseId, $turnierId]);
        return $klasse ?? throw new NichtGefunden('Klasse nicht gefunden.');
    }

    /** @return list<array<string, mixed>> */
    private function starter(int $klasseId, string $datum): array
    {
        $liste = $this->db->alle(
            "SELECT s.*, a.vorname, a.nachname, a.geschlecht, a.geburtsdatum, a.graduierung, t.alter_manuell, t.status AS teilnahme_status,
                    tm.name AS team_name, COALESCE(v.name, vt.name) AS verein, h.code AS herkunft_code
               FROM start s
               LEFT JOIN teilnahme t ON t.id = s.teilnahme_id
               LEFT JOIN athlet a ON a.id = t.athlet_id
               LEFT JOIN verein v ON v.id = a.verein_id
               LEFT JOIN team tm ON tm.id = s.team_id
               LEFT JOIN verein vt ON vt.id = tm.verein_id
               LEFT JOIN klasse h ON h.id = s.herkunft_klasse_id
              WHERE s.klasse_id = ?
              ORDER BY COALESCE(v.name, vt.name), a.nachname, tm.name",
            [$klasseId],
        );
        foreach ($liste as &$s) {
            $s['alter'] = $s['geburtsdatum'] ? Alter::amStichtag($s['geburtsdatum'], $datum) : $s['alter_manuell'];
            $s['name'] = $s['team_name'] ?? trim(($s['vorname'] ?? '') . ' ' . ($s['nachname'] ?? ''));
        }
        return $liste;
    }
}
