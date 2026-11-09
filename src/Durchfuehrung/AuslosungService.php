<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung;

use Wettkampf\Domain\Auslosung\Zufall;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;

/**
 * Auslosung einer Klasse. Jede Auslosung wird mit Seed, Zeitpunkt, Person,
 * Optionen und Ergebnis protokolliert und ist über den Seed reproduzierbar.
 * Neu auslosen ist möglich, solange in der Klasse kein Ergebnis vorliegt.
 */
final class AuslosungService
{
    public const ALGORITHMUS = 'xoshiro128ss-v1';

    private readonly Protokoll $protokoll;
    private readonly PhasenErzeugung $erzeugung;
    private readonly Fortschreibung $fortschreibung;

    public function __construct(private readonly Database $db)
    {
        $this->protokoll = new Protokoll($db);
        $this->erzeugung = new PhasenErzeugung($db);
        $this->fortschreibung = new Fortschreibung($db, $this->erzeugung);
    }

    /** @return int ID der Auslosung */
    public function auslosen(int $turnierId, int $klasseId, ?string $seed = null): int
    {
        return $this->db->transaktion(function () use ($turnierId, $klasseId, $seed) {
            $klasse = $this->klasse($turnierId, $klasseId);
            if ($klasse['status'] === 'zusammengelegt') {
                throw new FachFehler("Klasse {$klasse['code']} ist zusammengelegt – bitte die Zielklasse auslosen.");
            }
            $tatamis = [];
            if ($klasse['status'] !== 'offen') {
                $tatamis = $this->tatamiZuordnung($klasseId);
                $this->zuruecknehmen($turnierId, $klasseId);
                $klasse = $this->klasse($turnierId, $klasseId);
            }

            $starter = $this->starter($klasseId);
            if (!KlassenService::findetStatt($klasse, count($starter))) {
                throw new FachFehler("Klasse {$klasse['code']} hat nur " . count($starter) . ' von mindestens ' . KlassenService::mindestens($klasse)
                    . ' Startern und findet nicht statt. Bitte zusammenlegen oder die Mindestanzahl der Klasse anpassen.');
            }
            $seed ??= Zufall::neuerSeed();
            $ergebnis = $this->erzeugung->klasseErzeugen($klasse, $starter, new Zufall($seed));

            // Freilose fortschreiben und Phasen ohne Begegnungen (z. B. 1 Starter) abschließen
            foreach ($this->db->spalte(
                "SELECT b.id FROM begegnung b JOIN phase p ON p.id = b.phase_id WHERE p.klasse_id = ? AND b.status = 'freilos'",
                [$klasseId],
            ) as $id) {
                $this->fortschreibung->folgeAktualisieren((int) $id);
            }
            $this->db->update('klasse', ['status' => 'ausgelost'], ['id' => $klasseId]);
            foreach ($this->db->spalte("SELECT id FROM phase WHERE klasse_id = ? AND status <> 'wartet' ORDER BY id", [$klasseId]) as $phaseId) {
                $this->fortschreibung->phasePruefen((int) $phaseId);
            }
            $this->tatamiWiederherstellen($klasseId, $tatamis);

            $namen = [];
            foreach ($this->starterNamen($klasseId) as $id => $name) {
                $namen[$id] = $name;
            }
            $this->db->update('auslosung', ['aktiv' => 0], ['klasse_id' => $klasseId]);
            $auslosungId = $this->db->insert('auslosung', [
                'klasse_id' => $klasseId,
                'seed' => $seed,
                'algorithmus' => self::ALGORITHMUS,
                'optionen' => json_encode([
                    'modus' => $klasse['modus'],
                    'dojo_trennung' => (bool) $klasse['dojo_trennung'],
                    'anzahl_pools' => (int) $klasse['anzahl_pools'],
                    'aufsteiger_pro_pool' => (int) $klasse['aufsteiger_pro_pool'],
                ]),
                'protokoll' => json_encode($ergebnis + ['starter' => $namen], JSON_UNESCAPED_UNICODE),
                'aktiv' => 1,
                'erstellt_am' => date('Y-m-d H:i:s'),
                'erstellt_von' => Session::akteur(),
            ]);
            $this->protokoll->eintragen($turnierId, 'klasse', $klasseId, 'ausgelost', ['seed' => $seed, 'starter' => count($starter)]);
            (new PlanService($this->db))->nachziehen($turnierId); // dynamische Verteilung: freie Tatami bekommt die Gruppe
            return $auslosungId;
        });
    }

    /** Lost alle offenen Klassen aus, die ihre Mindestanzahl an Startern erreichen. @return int Anzahl */
    public function alleAuslosen(int $turnierId): int
    {
        $ids = $this->db->spalte(
            "SELECT k.id FROM klasse k WHERE k.turnier_id = ? AND k.status = 'offen'
                AND (SELECT COUNT(*) FROM start s WHERE s.klasse_id = k.id AND s.status <> 'zurueckgezogen') >= k.min_starter
              ORDER BY k.sortierung",
            [$turnierId],
        );
        foreach ($ids as $id) {
            $this->auslosen($turnierId, (int) $id);
        }
        return count($ids);
    }

    public function zuruecknehmen(int $turnierId, int $klasseId): void
    {
        $this->db->transaktion(function () use ($turnierId, $klasseId) {
            $klasse = $this->klasse($turnierId, $klasseId);
            if ($klasse['status'] === 'offen') {
                return;
            }
            if ($this->hatErgebnisse($klasseId)) {
                throw new FachFehler("In Klasse {$klasse['code']} liegen bereits Ergebnisse vor. Die Auslosung kann nicht mehr geändert werden.");
            }
            $this->db->delete('phase', ['klasse_id' => $klasseId]);
            $this->db->delete('platzierung', ['klasse_id' => $klasseId]);
            $this->db->update('auslosung', ['aktiv' => 0], ['klasse_id' => $klasseId]);
            $this->db->query('UPDATE start SET stichentscheid = NULL WHERE klasse_id = ?', [$klasseId]);
            $this->db->update('klasse', ['status' => 'offen', 'tisch_abschluss_am' => null, 'tisch_abschluss_von' => null], ['id' => $klasseId]);
            $this->protokoll->eintragen($turnierId, 'klasse', $klasseId, 'auslosung_zurueckgenommen');
        });
    }

    public function hatErgebnisse(int $klasseId): bool
    {
        return (int) $this->db->wert(
            "SELECT (SELECT COUNT(*) FROM begegnung b JOIN phase p ON p.id = b.phase_id WHERE p.klasse_id = ? AND b.status IN ('beendet', 'laeuft'))
                  + (SELECT COUNT(*) FROM auftritt a JOIN phase p ON p.id = a.phase_id WHERE p.klasse_id = ? AND a.status IN ('beendet', 'laeuft'))",
            [$klasseId, $klasseId],
        ) > 0;
    }

    /** @return list<array{id: int, gruppe: int|string|null}> */
    private function starter(int $klasseId): array
    {
        return array_map(
            static fn($s) => ['id' => (int) $s['id'], 'gruppe' => $s['gruppe'] === null ? null : (int) $s['gruppe']],
            $this->db->alle(
                "SELECT s.id, COALESCE(a.verein_id, tm.verein_id) AS gruppe
                   FROM start s
                   LEFT JOIN teilnahme t ON t.id = s.teilnahme_id
                   LEFT JOIN athlet a ON a.id = t.athlet_id
                   LEFT JOIN team tm ON tm.id = s.team_id
                  WHERE s.klasse_id = ? AND s.status <> 'zurueckgezogen'
                    AND (t.id IS NULL OR t.status NOT IN ('gesperrt', 'abgemeldet', 'nicht_erschienen'))
                  ORDER BY s.id",
                [$klasseId],
            ),
        );
    }

    /** @return array<int, string> Start-ID → „Name (Verein)“ für das Protokoll */
    private function starterNamen(int $klasseId): array
    {
        $namen = [];
        foreach ($this->db->alle(
            "SELECT s.id, a.vorname, a.nachname, tm.name AS team, COALESCE(v.name, vt.name) AS verein
               FROM start s
               LEFT JOIN teilnahme t ON t.id = s.teilnahme_id LEFT JOIN athlet a ON a.id = t.athlet_id
               LEFT JOIN verein v ON v.id = a.verein_id LEFT JOIN team tm ON tm.id = s.team_id
               LEFT JOIN verein vt ON vt.id = tm.verein_id
              WHERE s.klasse_id = ?",
            [$klasseId],
        ) as $s) {
            $name = $s['team'] ?? trim("{$s['vorname']} {$s['nachname']}");
            $namen[(int) $s['id']] = $name . ($s['verein'] ? " ({$s['verein']})" : '');
        }
        return $namen;
    }

    /** @return array<string, array{tatami_id: int|null, reihenfolge: int}> */
    private function tatamiZuordnung(int $klasseId): array
    {
        $zuordnung = [];
        foreach ($this->db->alle('SELECT typ, nummer, tatami_id, reihenfolge FROM phase WHERE klasse_id = ?', [$klasseId]) as $p) {
            $zuordnung["{$p['typ']}|{$p['nummer']}"] = ['tatami_id' => $p['tatami_id'], 'reihenfolge' => (int) $p['reihenfolge']];
        }
        return $zuordnung;
    }

    /** @param array<string, array{tatami_id: int|null, reihenfolge: int}> $zuordnung */
    private function tatamiWiederherstellen(int $klasseId, array $zuordnung): void
    {
        // Ändert sich der Modus (z. B. 4 → 3 Starter: K.-o. → jeder gegen jeden), übernimmt
        // jede neue Phase Tatami und Platz der bisherigen ersten Phase
        $erste = array_values(array_filter($zuordnung, static fn($z) => $z['tatami_id'] !== null));
        usort($erste, static fn($a, $b) => $a['reihenfolge'] <=> $b['reihenfolge']);
        foreach ($this->db->alle('SELECT id, typ, nummer FROM phase WHERE klasse_id = ?', [$klasseId]) as $p) {
            $ziel = $zuordnung["{$p['typ']}|{$p['nummer']}"] ?? $erste[0] ?? null;
            if ($ziel !== null) {
                $this->db->update('phase', $ziel, ['id' => $p['id']]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function klasse(int $turnierId, int $klasseId): array
    {
        return $this->db->eine('SELECT * FROM klasse WHERE id = ? AND turnier_id = ?', [$klasseId, $turnierId])
            ?? throw new FachFehler('Klasse nicht gefunden.');
    }
}
