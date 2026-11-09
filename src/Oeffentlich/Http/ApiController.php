<?php

declare(strict_types=1);

namespace Wettkampf\Oeffentlich\Http;

use Wettkampf\Oeffentlich\EinblendungAnsicht;
use Wettkampf\Durchfuehrung\KlassenAnsicht;
use Wettkampf\Durchfuehrung\TatamiAnsicht;
use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Controller;

/**
 * Öffentliche REST-API (JSON), Version 1 – nur für die eigene Statusseite, nicht für Dritte.
 *
 * Öffentlich (nur wenn die Statusseite des Turniers aktiv ist):
 *   GET  /api/v1/turnier/{id}                Turnier-Kopfdaten + Revision
 *   GET  /api/v1/turnier/{id}/tatamis        laufende und nächste Begegnungen je Tatami
 *   GET  /api/v1/turnier/{id}/klassen        Klassen mit Status
 *   GET  /api/v1/turnier/{id}/klasse/{kid}   Baum/Tabelle einer Klasse
 *   GET  /api/v1/turnier/{id}/ergebnisse     bestätigte Platzierungen
 *   GET  /api/v1/turnier/{id}/tatami/{nr}/einblendung  laufender Kampf und frisches Ergebnis (Stream-Einblendung)
 *
 * (Tischbesatzung: siehe Durchfuehrung\Http\TischApiController; vollständiges Verzeichnis: docs/api/openapi.yaml)
 *
 * Datenschutz: Es werden nur Name und Verein ausgegeben, keine Geburtsdaten.
 */
final class ApiController extends Controller
{
    public function turnierInfo(Request $request, int $id): Response
    {
        $t = $this->oeffentlichesTurnier($id);
        return Response::json([
            'id' => (int) $t['id'],
            'name' => $t['name'],
            'datum' => $t['datum'],
            'ort' => $t['ort'],
            'halle' => $t['halle'],
            'revision' => (int) $t['revision'],
        ]);
    }

    public function tatamis(Request $request, int $id): Response
    {
        $t = $this->oeffentlichesTurnier($id);
        // vor dem Start keine Tatami-Belegung (wie auf der Statusseite)
        $tatamis = TurnierPhase::gestartet($t) ? array_map(fn($x) => $this->tatamiOeffentlich($x), (new TatamiAnsicht($this->db))->alle($id, 4)) : [];
        return Response::json([
            'revision' => (int) $t['revision'],
            'beendet' => (int) $this->db->wert(
                "SELECT COUNT(*) FROM begegnung b JOIN phase p ON p.id = b.phase_id JOIN klasse k ON k.id = p.klasse_id WHERE k.turnier_id = ? AND b.status = 'beendet'",
                [$id],
            ),
            'gesamt' => (int) $this->db->wert(
                "SELECT COUNT(*) FROM begegnung b JOIN phase p ON p.id = b.phase_id JOIN klasse k ON k.id = p.klasse_id WHERE k.turnier_id = ? AND b.status <> 'freilos'",
                [$id],
            ),
            'tatamis' => $tatamis,
        ]);
    }

    /** Einblendung einer Tatami (Livestream): läuft gerade, Ergebnis der letzten Sekunden. */
    public function einblendung(Request $request, int $id, string $nummer): Response
    {
        $t = $this->oeffentlichesTurnier($id);
        $tatamiId = $this->db->wert('SELECT id FROM tatami WHERE turnier_id = ? AND nummer = ?', [$id, (int) $nummer]) ?? throw new NichtGefunden('Tatami nicht gefunden.');
        if (!TurnierPhase::gestartet($t)) {
            return Response::json(['tatami' => null, 'jetzt' => null, 'ergebnis' => null]);
        }
        return Response::json((new EinblendungAnsicht($this->db))->fuerTatami((int) $tatamiId));
    }

    public function klassen(Request $request, int $id): Response
    {
        $this->oeffentlichesTurnier($id);
        return Response::json(array_map(static fn($k) => [
            'id' => (int) $k['id'],
            'code' => $k['code'],
            'bezeichnung' => $k['bezeichnung'],
            'disziplin' => $k['disziplin'],
            'status' => $k['status'],
            'starter' => (int) $k['anzahl'],
        ], $this->db->alle(
            "SELECT k.*, (SELECT COUNT(*) FROM start s WHERE s.klasse_id = k.id AND s.status <> 'zurueckgezogen') AS anzahl
               FROM klasse k WHERE k.turnier_id = ? AND k.status NOT IN ('zusammengelegt') ORDER BY k.sortierung, k.code",
            [$id],
        )));
    }

    public function klasse(Request $request, int $id, int $klasseid): Response
    {
        $this->oeffentlichesTurnier($id);
        $a = (new KlassenAnsicht($this->db))->laden($klasseid);
        if ((int) $a['klasse']['turnier_id'] !== $id) {
            throw new NichtGefunden('Klasse nicht gefunden.');
        }
        return Response::json(self::klasseOeffentlich($a));
    }

    public function ergebnisse(Request $request, int $id): Response
    {
        $this->oeffentlichesTurnier($id);
        $ansicht = new KlassenAnsicht($this->db);
        $liste = [];
        foreach ($this->db->alle("SELECT * FROM klasse WHERE turnier_id = ? AND status = 'bestaetigt' ORDER BY sortierung, code", [$id]) as $k) {
            $liste[] = [
                'klasse' => ['id' => (int) $k['id'], 'code' => $k['code'], 'bezeichnung' => $k['bezeichnung']],
                'plaetze' => array_map(
                    static fn($p) => ['platz' => (int) $p['platz']] + $ansicht->person((int) $p['start_id']),
                    $this->db->alle('SELECT * FROM platzierung WHERE klasse_id = ? ORDER BY platz', [$k['id']]),
                ),
            ];
        }
        return Response::json($liste);
    }

    /**
     * Öffentliche Sicht einer Klasse (nur Name/Verein, Ergebnisse).
     *
     * @param array<string, mixed> $a Lesemodell
     * @return array<string, mixed>
     */
    public static function klasseOeffentlich(array $a): array
    {
        $k = $a['klasse'];
        $begegnung = static fn($b) => [
            'id' => (int) $b['id'], 'nr' => $b['nr'], 'runde' => (int) $b['runde'], 'runden_name' => $b['runden_name'], 'art' => $b['art'],
            'status' => $b['status'], 'system' => $b['system'],
            'aka' => ['name' => $b['aka_name'] ?: $b['aka_platzhalter'], 'verein' => $b['aka_verein']],
            'shiro' => ['name' => $b['shiro_name'] ?: $b['shiro_platzhalter'], 'verein' => $b['shiro_verein']],
            'sieger' => $b['sieger_start_id'] === null ? null : ((int) $b['sieger_start_id'] === (int) $b['aka_start_id'] ? 'aka' : 'shiro'),
            'entscheidung' => $b['entscheidung'],
        ];
        return [
            'klasse' => ['id' => (int) $k['id'], 'code' => $k['code'], 'bezeichnung' => $k['bezeichnung'], 'disziplin' => $k['disziplin'], 'status' => $k['status']],
            'phasen' => array_map(static fn($p) => [
                'bezeichnung' => $p['bezeichnung'], 'typ' => $p['typ'], 'art' => $p['art'], 'status' => $p['status'], 'tatami' => $p['tatami'],
                'begegnungen' => array_map($begegnung, $p['begegnungen']),
                'auftritte' => array_map(static fn($x) => [
                    'reihenfolge' => (int) $x['reihenfolge'], 'name' => $x['name'], 'verein' => $x['verein'], 'kata' => $x['kata'],
                    'gesamt' => $x['status'] === 'beendet' ? (float) $x['gesamt'] : null,
                ], $p['auftritte']),
                'tabelle' => array_map(static fn($z) => array_intersect_key($z, array_flip(['platz', 'name', 'verein', 'kaempfe', 'siege', 'ippon', 'wazaari'])), $p['tabelle'] ?? []),
            ], $a['phasen']),
            'platzierungen' => $k['status'] === 'bestaetigt'
                ? array_map(static fn($p) => ['platz' => (int) $p['platz'], 'name' => $p['name'], 'verein' => $p['verein']], $a['platzierungen'])
                : [],
        ];
    }

    /** @param array<string, mixed> $t @return array<string, mixed> */
    private function tatamiOeffentlich(array $t): array
    {
        $eintrag = static fn(?array $e) => $e === null ? null : [
            'typ' => $e['typ'], 'nr' => $e['nr'], 'klasse' => $e['klasse'], 'klasse_id' => $e['klasse_id'], 'klasse_name' => $e['klasse_name'],
            'disziplin' => $e['disziplin'], 'kumite_form' => $e['kumite_form'] ?? null,
            'runde' => $e['runde'], 'status' => $e['status'], 'unterbrochen' => $e['unterbrochen'] ?? false,
            'aka' => $e['aka'] ?? null, 'shiro' => $e['shiro'] ?? null, 'starter' => $e['starter'] ?? null,
        ];
        return [
            'tatami' => ['id' => (int) $t['tatami']['id'], 'nummer' => (int) $t['tatami']['nummer'], 'bezeichnung' => $t['tatami']['bezeichnung'], 'stream_url' => $t['tatami']['stream_url']],
            'laufend' => $eintrag($t['laufend']),
            'naechste' => array_map($eintrag, $t['naechste']),
            'offen' => $t['offen'],
            'beendet' => $t['beendet'],
        ];
    }

    /** @return array<string, mixed> */
    private function oeffentlichesTurnier(int $id): array
    {
        $t = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$id]);
        if ($t === null || (!(int) $t['oeffentlich'] && !Session::leitet($id))) {
            throw new NichtGefunden('Turnier nicht gefunden oder nicht öffentlich.');
        }
        return $t;
    }
}
