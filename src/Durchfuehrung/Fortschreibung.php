<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung;

use Wettkampf\Domain\Auslosung\Zufall;
use Wettkampf\Domain\Platzierung\Platzierung;
use Wettkampf\Domain\Wertung\Wertung;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Turnier\StammdatenService;

/**
 * Schreibt Turnierbäume fort: Sieger/Verlierer in Folgebegegnungen
 * eintragen, Freilose auswerten, Phasen abschließen, Finalrunden befüllen
 * und Platzierungen berechnen.
 *
 * Quellen einer Begegnungsseite (Spalte *_quelle):
 *   S:<start>  fester Starter      F  Freilos
 *   W:<id>     Sieger von …        L:<id>  Verlierer von …
 */
final class Fortschreibung
{
    public function __construct(private readonly Database $db, private readonly PhasenErzeugung $erzeugung) {}

    /**
     * Trägt das Ergebnis einer Begegnung ein und schreibt den Baum fort.
     *
     * @param array<string, mixed> $felder weitere Spalten (wertung, entscheidung, kata_aka …)
     */
    public function begegnungAbschliessen(int $begegnungId, int $siegerStartId, array $felder = []): void
    {
        $this->db->transaktion(function () use ($begegnungId, $siegerStartId, $felder) {
            $b = $this->begegnung($begegnungId);
            if (!in_array($siegerStartId, [(int) $b['aka_start_id'], (int) $b['shiro_start_id']], true)) {
                throw new \Wettkampf\Domain\FachFehler('Der Sieger muss einer der beiden Starter sein.');
            }
            $this->db->query(
                'UPDATE begegnung SET version = version + 1 WHERE id = ?',
                [$begegnungId],
            );
            $this->db->update('begegnung', $felder + [
                'sieger_start_id' => $siegerStartId,
                'status' => 'beendet',
                'beendet_am' => date('Y-m-d H:i:s'),
                'erfasst_von' => \Wettkampf\Kern\Http\Session::akteur(),
            ], ['id' => $begegnungId]);
            $this->folgeAktualisieren($begegnungId);
            $this->phasePruefen((int) $b['phase_id']);
        });
    }

    /**
     * Überträgt Sieger/Verlierer einer Begegnung in die Folgebegegnungen.
     */
    public function folgeAktualisieren(int $begegnungId): void
    {
        $b = $this->begegnung($begegnungId);
        $sieger = $b['sieger_start_id'] === null ? null : (int) $b['sieger_start_id'];
        $verlierer = null;
        if ($sieger !== null && $b['status'] !== 'freilos') {
            $verlierer = $sieger === (int) $b['aka_start_id'] ? (int) $b['shiro_start_id'] : (int) $b['aka_start_id'];
        }
        foreach ($this->db->alle(
            'SELECT * FROM begegnung WHERE aka_quelle IN (?, ?) OR shiro_quelle IN (?, ?)',
            ["W:$begegnungId", "L:$begegnungId", "W:$begegnungId", "L:$begegnungId"],
        ) as $folge) {
            $aenderung = [];
            foreach (['aka', 'shiro'] as $seite) {
                $quelle = $folge["{$seite}_quelle"];
                if ($quelle === "W:$begegnungId") {
                    $aenderung["{$seite}_start_id"] = $sieger;
                } elseif ($quelle === "L:$begegnungId") {
                    $aenderung["{$seite}_start_id"] = $verlierer;
                }
            }
            $aka = array_key_exists('aka_start_id', $aenderung) ? $aenderung['aka_start_id'] : $folge['aka_start_id'];
            $shiro = array_key_exists('shiro_start_id', $aenderung) ? $aenderung['shiro_start_id'] : $folge['shiro_start_id'];
            if (in_array($folge['status'], ['wartet', 'bereit'], true)) {
                $aenderung['status'] = ($aka !== null && $shiro !== null) ? 'bereit' : 'wartet';
            }
            $this->db->update('begegnung', $aenderung, ['id' => $folge['id']]);
            if (($aenderung['status'] ?? null) === 'bereit') {
                $this->ausfallPruefen((int) $folge['id']);
            }
        }
    }

    /**
     * Ist ein Starter ausgefallen (gesperrt, abgemeldet, nicht erschienen, zurückgezogen),
     * verliert er die bereite Begegnung kampflos (Kiken).
     */
    public function ausfallPruefen(int $begegnungId): bool
    {
        $b = $this->begegnung($begegnungId);
        if ($b['status'] !== 'bereit') {
            return false;
        }
        $akaWeg = $this->ausgefallen((int) $b['aka_start_id']);
        $shiroWeg = $this->ausgefallen((int) $b['shiro_start_id']);
        if (!$akaWeg && !$shiroWeg) {
            return false;
        }
        // Fallen beide aus, kommt Aka formal weiter (Kampf wird nicht ausgetragen)
        $gegen = $shiroWeg ? 'shiro' : 'aka';
        $sieger = $gegen === 'aka' ? (int) $b['shiro_start_id'] : (int) $b['aka_start_id'];
        $this->begegnungAbschliessen($begegnungId, $sieger, [
            'entscheidung' => 'kiken',
            'wertung' => json_encode(['gegen' => $gegen, 'grund' => 'ausgefallen']),
        ]);
        return true;
    }

    public function ausgefallen(int $startId): bool
    {
        $s = $this->db->eine(
            'SELECT s.status, t.status AS teilnahme_status FROM start s LEFT JOIN teilnahme t ON t.id = s.teilnahme_id WHERE s.id = ?',
            [$startId],
        );
        return $s !== null && ($s['status'] === 'zurueckgezogen' || in_array($s['teilnahme_status'], StammdatenService::AUSGEFALLEN, true));
    }

    /**
     * Prüft, ob eine Phase abgeschlossen ist, und löst die Folgeschritte aus.
     */
    public function phasePruefen(int $phaseId): void
    {
        $phase = $this->db->eine('SELECT * FROM phase WHERE id = ?', [$phaseId]);
        if ($phase === null) {
            return;
        }
        // Eine Phase besteht aus Begegnungen oder Auftritten (Punkte-Finalrunde)
        $offen = (int) $this->db->wert("SELECT COUNT(*) FROM auftritt WHERE phase_id = ? AND status <> 'beendet'", [$phaseId])
            + (int) $this->db->wert("SELECT COUNT(*) FROM begegnung WHERE phase_id = ? AND status NOT IN ('beendet', 'freilos')", [$phaseId]);

        if ($offen > 0) {
            if ($phase['status'] === 'beendet') {
                $this->db->update('phase', ['status' => 'laeuft'], ['id' => $phaseId]);
            }
            return;
        }
        $this->db->update('phase', ['status' => 'beendet'], ['id' => $phaseId]);
        $klasseId = (int) $phase['klasse_id'];

        // Vorrunden abgeschlossen → Finalrunde befüllen
        $wartend = $this->db->eine("SELECT * FROM phase WHERE klasse_id = ? AND status = 'wartet' AND typ IN ('finalrunde', 'punkte_finale')", [$klasseId]);
        if ($wartend !== null) {
            $vorrundenOffen = (int) $this->db->wert(
                "SELECT COUNT(*) FROM phase WHERE klasse_id = ? AND typ IN ('pool', 'punkte_vorrunde') AND status <> 'beendet'",
                [$klasseId],
            );
            if ($vorrundenOffen === 0) {
                try {
                    $this->finalrundeBefuellen($wartend);
                } catch (StichentscheidNoetig) {
                    // Finalrunde bleibt „wartet“, bis die Leitung den Gleichstand auflöst
                    $this->db->update('klasse', ['status' => 'laeuft'], ['id' => $klasseId]);
                }
            }
            return;
        }

        $nochOffen = (int) $this->db->wert("SELECT COUNT(*) FROM phase WHERE klasse_id = ? AND status <> 'beendet'", [$klasseId]);
        if ($nochOffen === 0) {
            $this->klasseAbschliessen($klasseId);
        }
    }

    /**
     * Berechnet die Platzierungen einer Klasse (noch unbestätigt).
     *
     * @return list<list<int>> nicht auflösbare Gleichstände (leer = eindeutig)
     */
    public function klasseAbschliessen(int $klasseId): array
    {
        $ergebnis = $this->platzierungenBerechnen($klasseId);
        $this->db->delete('platzierung', ['klasse_id' => $klasseId]);
        foreach ($ergebnis['plaetze'] as $p) {
            if ($p['platz'] <= 4) {
                $this->db->insert('platzierung', ['klasse_id' => $klasseId, 'platz' => $p['platz'], 'start_id' => $p['start_id']]);
            }
        }
        $this->db->update('klasse', ['status' => $ergebnis['gleichstand'] ? 'laeuft' : 'beendet'], ['id' => $klasseId]);
        return $ergebnis['gleichstand'];
    }

    /**
     * @return array{plaetze: list<array{platz: int, start_id: int}>, gleichstand: list<list<int>>}
     */
    public function platzierungenBerechnen(int $klasseId): array
    {
        $letzte = $this->db->eine(
            "SELECT * FROM phase WHERE klasse_id = ? AND typ IN ('ko', 'jgj', 'finalrunde', 'punkte_finale') ORDER BY id DESC",
            [$klasseId],
        );
        if ($letzte === null) {
            return ['plaetze' => [], 'gleichstand' => []];
        }
        $hatAuftritte = (int) $this->db->wert('SELECT COUNT(*) FROM auftritt WHERE phase_id = ?', [$letzte['id']]) > 0;
        if ($hatAuftritte) {
            $auftritte = array_map(static fn($a) => [
                'start_id' => (int) $a['start_id'],
                'gesamt' => (float) $a['gesamt'],
                'mit_niedrigster' => Wertung::mitNiedrigster($a['wertung'], (float) $a['gesamt']),
                'nebenwertung' => (float) $a['nebenwertung'],
                'stichentscheid' => $a['stichentscheid'] === null ? null : (int) $a['stichentscheid'],
            ], $this->db->alle("SELECT * FROM auftritt WHERE phase_id = ? AND status = 'beendet'", [$letzte['id']]));
            $r = Platzierung::ausPunkten($auftritte);
            return [
                'plaetze' => array_map(static fn($z) => ['platz' => $z['platz'], 'start_id' => $z['start_id']], $r['tabelle']),
                'gleichstand' => Platzierung::relevanterGleichstand($r['tabelle'], $r['gleichstand'], 4) ? $r['gleichstand'] : [],
            ];
        }

        $begegnungen = $this->db->alle('SELECT * FROM begegnung WHERE phase_id = ? ORDER BY runde, position', [$letzte['id']]);
        $istJgj = $begegnungen !== [] && $begegnungen[0]['art'] === 'jgj';
        if ($istJgj || $begegnungen === []) {
            $startIds = $this->phasenStarter((int) $letzte['id']);
            $daten = array_map(static function ($b) {
                $w = json_decode((string) $b['wertung'], true) ?: [];
                return [
                    'aka' => (int) $b['aka_start_id'],
                    'shiro' => (int) $b['shiro_start_id'],
                    'sieger' => (int) $b['sieger_start_id'],
                    'aka_ippon' => (int) ($w['aka']['ippon'] ?? 0),
                    'aka_wazaari' => (int) ($w['aka']['wazaari'] ?? 0),
                    'shiro_ippon' => (int) ($w['shiro']['ippon'] ?? 0),
                    'shiro_wazaari' => (int) ($w['shiro']['wazaari'] ?? 0),
                    'aka_flaggen' => (int) ($w['flaggen']['aka'] ?? 0),
                    'shiro_flaggen' => (int) ($w['flaggen']['shiro'] ?? 0),
                ];
            }, array_filter($begegnungen, static fn($b) => $b['status'] === 'beendet'));
            $stich = [];
            foreach ($this->db->alle('SELECT id, stichentscheid FROM start WHERE id IN (' . implode(',', $startIds ?: [0]) . ') AND stichentscheid IS NOT NULL') as $s) {
                $stich[(int) $s['id']] = (int) $s['stichentscheid'];
            }
            $r = Platzierung::ausJederGegenJeden($startIds, array_values($daten), $stich);
            return [
                'plaetze' => array_map(static fn($z) => ['platz' => $z['platz'], 'start_id' => $z['start_id']], $r['tabelle']),
                'gleichstand' => Platzierung::relevanterGleichstand($r['tabelle'], $r['gleichstand'], 4) ? $r['gleichstand'] : [],
            ];
        }

        $paar = static function (?array $b): ?array {
            if ($b === null || $b['sieger_start_id'] === null) {
                return null;
            }
            $sieger = (int) $b['sieger_start_id'];
            return ['sieger' => $sieger, 'verlierer' => $sieger === (int) $b['aka_start_id'] ? (int) $b['shiro_start_id'] : (int) $b['aka_start_id']];
        };
        $finale = current(array_filter($begegnungen, static fn($b) => $b['art'] === 'finale')) ?: null;
        $platz3 = current(array_filter($begegnungen, static fn($b) => $b['art'] === 'platz3')) ?: null;
        return ['plaetze' => Platzierung::ausKo($paar($finale), $paar($platz3)), 'gleichstand' => []];
    }

    /**
     * Ermittelt die Aufsteiger aller Vorrunden und erzeugt die Finalrunde.
     *
     * @param array<string, mixed> $phase
     */
    private function finalrundeBefuellen(array $phase): void
    {
        $klasseId = (int) $phase['klasse_id'];
        $klasse = $this->db->eine('SELECT * FROM klasse WHERE id = ?', [$klasseId]);
        $starter = [];
        foreach ($this->db->alle("SELECT * FROM phase WHERE klasse_id = ? AND typ IN ('pool', 'punkte_vorrunde') ORDER BY nummer", [$klasseId]) as $vorrunde) {
            foreach ($this->aufsteiger($vorrunde, $klasse) as $startId) {
                $starter[] = ['id' => $startId, 'gruppe' => 'pool' . $vorrunde['nummer']];
            }
        }
        $seed = (string) $this->db->wert('SELECT seed FROM auslosung WHERE klasse_id = ? AND aktiv = 1', [$klasseId]);
        $this->erzeugung->finalrundeErzeugen($phase, $klasse, $starter, new Zufall($seed . ':finalrunde'));
        $this->db->update('phase', ['status' => 'bereit'], ['id' => $phase['id']]);
        foreach ($this->db->spalte("SELECT id FROM begegnung WHERE phase_id = ? AND status = 'freilos'", [$phase['id']]) as $id) {
            $this->folgeAktualisieren((int) $id);
        }
        $this->phasePruefen((int) $phase['id']);
    }

    /**
     * Versucht, eine wartende Finalrunde zu befüllen (z. B. nach einem
     * Stichentscheid der Leitung).
     */
    public function finalrundeErneutPruefen(int $klasseId): void
    {
        $vorrunde = $this->db->eine("SELECT id FROM phase WHERE klasse_id = ? AND typ IN ('pool', 'punkte_vorrunde') ORDER BY id DESC", [$klasseId]);
        if ($vorrunde !== null) {
            $this->phasePruefen((int) $vorrunde['id']);
        }
    }

    /**
     * @param array<string, mixed> $vorrunde
     * @param array<string, mixed> $klasse
     * @return list<int>
     */
    private function aufsteiger(array $vorrunde, array $klasse): array
    {
        if ($vorrunde['typ'] === 'punkte_vorrunde') {
            $auftritte = array_map(static fn($a) => [
                'start_id' => (int) $a['start_id'],
                'gesamt' => (float) $a['gesamt'],
                'mit_niedrigster' => Wertung::mitNiedrigster($a['wertung'], (float) $a['gesamt']),
                'nebenwertung' => (float) $a['nebenwertung'],
                'stichentscheid' => $a['stichentscheid'] === null ? null : (int) $a['stichentscheid'],
            ], $this->db->alle('SELECT * FROM auftritt WHERE phase_id = ?', [$vorrunde['id']]));
            $r = Platzierung::ausPunkten($auftritte);
            // Aufsteiger je Vorrunde bzw. Pool (bei Kata-Pools anteilig), sonst die Finalisten der Klasse
            $anzahl = (int) ($vorrunde['aufsteiger'] ?: $klasse['finalisten']);
            if (Platzierung::relevanterGleichstand($r['tabelle'], $r['gleichstand'], $anzahl)) {
                // Gleichstand an der Grenze zum Finale: nur relevant, wenn die Gruppe die Grenze überschreitet
                $platzVon = array_column($r['tabelle'], 'platz', 'start_id');
                foreach ($r['gleichstand'] as $gruppe) {
                    $plaetze = array_map(static fn($id) => $platzVon[$id], $gruppe);
                    if (min($plaetze) <= $anzahl && max($plaetze) > $anzahl) {
                        throw new StichentscheidNoetig($gruppe);
                    }
                }
            }
            return array_slice(array_column($r['tabelle'], 'start_id'), 0, $anzahl);
        }

        // Pool: Sieger der Begegnungen der letzten Runde; Pool ohne Begegnungen → alle Starter
        $letzteRunde = (int) $this->db->wert('SELECT MAX(runde) FROM begegnung WHERE phase_id = ?', [$vorrunde['id']]);
        if ($letzteRunde === 0) {
            return $this->phasenStarter((int) $vorrunde['id']);
        }
        return array_map('intval', $this->db->spalte(
            'SELECT sieger_start_id FROM begegnung WHERE phase_id = ? AND runde = ? ORDER BY position',
            [$vorrunde['id'], $letzteRunde],
        ));
    }

    /** @return list<int> */
    public function phasenStarter(int $phaseId): array
    {
        $protokoll = json_decode((string) $this->db->wert('SELECT starter FROM phase WHERE id = ?', [$phaseId]), true);
        return is_array($protokoll) ? array_map('intval', $protokoll) : [];
    }

    /** @return array<string, mixed> */
    private function begegnung(int $id): array
    {
        return $this->db->eine('SELECT * FROM begegnung WHERE id = ?', [$id])
            ?? throw new \Wettkampf\Domain\FachFehler('Begegnung nicht gefunden.');
    }
}
