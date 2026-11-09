<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung;

use Wettkampf\Domain\Wertung\Wertung;
use Wettkampf\Domain\Turnierbaum\KoBaum;
use Wettkampf\Kern\Db\Database;

/**
 * Was läuft auf einer Tatami, was kommt als Nächstes? Reihenfolge laut
 * Tatami-Plan (Phasen), innerhalb der Phase nach Runde und Position.
 * Nur Name und Verein – keine Geburtsdaten (öffentliche Statusseite).
 */
final class TatamiAnsicht
{
    private readonly KlassenAnsicht $namen;
    /** @var array<int, array<int, int>> Klasse → (Begegnung → Kampfnummer) */
    private array $nummern = [];
    /** @var array<int, bool> Phase → schon begonnen */
    private array $begonnen = [];

    public function __construct(private readonly Database $db)
    {
        $this->namen = new KlassenAnsicht($db);
    }

    /** @return list<array<string, mixed>> */
    public function alle(int $turnierId, int $anzahlNaechste = 4): array
    {
        return array_map(
            fn($t) => $this->fuerTatami((int) $t['id'], $anzahlNaechste),
            $this->db->alle('SELECT * FROM tatami WHERE turnier_id = ? ORDER BY nummer', [$turnierId]),
        );
    }

    /**
     * @return array{tatami: array<string, mixed>, laufend: array<string, mixed>|null, naechste: list<array<string, mixed>>, offen: int, beendet: int, aufruf_liste: list<array{start_id: int, name: string, verein: string, klasse: string, fehlt: bool}>}
     */
    public function fuerTatami(int $tatamiId, int $anzahlNaechste = 5): array
    {
        $tatami = $this->db->eine('SELECT id, turnier_id, nummer, bezeichnung, stream_url, gesperrt_am FROM tatami WHERE id = ?', [$tatamiId]);
        $eintraege = [];
        $offen = 0;
        $beendet = 0;
        foreach ($this->db->alle(
            "SELECT p.*, k.code, k.bezeichnung AS klasse_name, k.disziplin, k.kampfrichter, k.optionen
               FROM phase p JOIN klasse k ON k.id = p.klasse_id
              WHERE p.tatami_id = ? ORDER BY p.reihenfolge, p.id",
            [$tatamiId],
        ) as $phase) {
            foreach ($this->db->alle("SELECT * FROM begegnung WHERE phase_id = ? AND status <> 'freilos' ORDER BY runde, position", [$phase['id']]) as $b) {
                if ($b['status'] === 'beendet') {
                    $beendet++;
                    continue;
                }
                $offen++;
                if (in_array($b['status'], ['bereit', 'laeuft'], true)) {
                    $eintraege[] = $this->begegnung($b, $phase);
                }
            }
            if ($phase['status'] === 'wartet') {
                continue;
            }
            foreach ($this->db->alle('SELECT * FROM auftritt WHERE phase_id = ? ORDER BY reihenfolge', [$phase['id']]) as $a) {
                if ($a['status'] === 'beendet') {
                    $beendet++;
                    continue;
                }
                $offen++;
                $eintraege[] = $this->auftritt($a, $phase);
            }
        }
        $laufend = null;
        foreach ($eintraege as $i => $e) {
            if ($e['status'] === 'laeuft') {
                $laufend = $e;
                unset($eintraege[$i]);
                break;
            }
        }
        // Vorträge (Punktesystem) haben keinen Status „läuft“: Der nächste Vortrag ist der aktuelle
        $erster = array_key_first($eintraege);
        if ($laufend === null && $erster !== null && $eintraege[$erster]['typ'] === 'auftritt') {
            $laufend = $eintraege[$erster];
            unset($eintraege[$erster]);
        }
        // Wer stand im zuletzt entschiedenen Kampf dieser Tatami? (Jiyu-Kumite: Pause anbieten)
        $zuletzt = $this->db->eine(
            "SELECT b.aka_start_id, b.shiro_start_id FROM begegnung b JOIN phase p ON p.id = b.phase_id
              WHERE p.tatami_id = ? AND b.status = 'beendet' AND b.beendet_am IS NOT NULL ORDER BY b.beendet_am DESC, b.id DESC LIMIT 1",
            [$tatamiId],
        );
        $gekaempft = $zuletzt === null ? [] : array_flip(array_filter([(int) $zuletzt['aka_start_id'], (int) $zuletzt['shiro_start_id']]));
        $direktWieder = static function (?array $e) use ($gekaempft): ?array {
            if ($e === null || $e['typ'] !== 'begegnung') {
                return $e;
            }
            $e['direkt_wieder'] = array_values(array_filter(['aka', 'shiro'], static fn($s) => isset($gekaempft[(int) ($e['starts'][$s] ?? 0)])));
            return $e;
        };
        $laufend = $direktWieder($laufend);
        $eintraege = array_map($direktWieder, array_values($eintraege));
        return [
            'tatami' => $tatami,
            'laufend' => $laufend,
            'naechste' => array_slice(array_values($eintraege), 0, $anzahlNaechste),
            'aufruf_liste' => $this->aufrufListe(array_values($eintraege), $laufend),
            'offen' => $offen,
            'beendet' => $beendet,
        ];
    }

    /**
     * Starter, die gerade ausgerufen werden und noch nicht als „da“ bestätigt
     * sind: aus Gruppen mit gestartetem Gruppen-Ausruf bzw. aus Kämpfen, in denen
     * der Ausrufer schon jemanden markiert hat. Wer in einem offenen Kampf „da“
     * ist oder gerade auf der Matte steht, fehlt in der Liste.
     *
     * @param list<array<string, mixed>> $eintraege
     * @param array<string, mixed>|null $laufend
     * @return list<array{start_id: int, name: string, verein: string, klasse: string, fehlt: bool}>
     */
    private function aufrufListe(array $eintraege, ?array $laufend): array
    {
        // ein noch nicht begonnener Kata-Vortrag („laufend“ nur als nächster) wird mit aufgerufen
        if ($laufend !== null && $laufend['status'] !== 'laeuft' && empty($laufend['gruppe_begonnen'])) {
            array_unshift($eintraege, $laufend);
            $laufend = null;
        }
        $da = [];
        foreach ($laufend['starts'] ?? [] as $startId) {
            if ($startId !== null) {
                $da[$startId] = true;
            }
        }
        $liste = [];
        foreach ($eintraege as $e) {
            $a = $e['aufruf'];
            $aktiv = $e['gruppe_aufgerufen'] || ($a['aka'] ?? $a['shiro'] ?? $a['starter'] ?? null) !== null;
            foreach ($e['starts'] as $seite => $startId) {
                if ($startId === null) {
                    continue;
                }
                if (($a[$seite] ?? null) === 'da') {
                    $da[$startId] = true;
                } elseif ($aktiv && !isset($liste[$startId])) {
                    $person = $seite === 'starter' ? $e['starter'] : $e[$seite];
                    $liste[$startId] = ['start_id' => $startId, 'name' => $person['name'], 'verein' => $person['verein'], 'klasse' => $e['klasse'], 'fehlt' => ($a[$seite] ?? null) === 'fehlt'];
                }
            }
        }
        return array_values(array_diff_key($liste, $da));
    }

    /**
     * Gruppe, die auf dieser Tatami fertig ist und von der Tischbesatzung noch
     * abgeschlossen werden muss (Klasse beendet, letzte Phase auf dieser Tatami).
     *
     * @return array<string, mixed>|null
     */
    public function abschlussOffen(int $tatamiId): ?array
    {
        $k = $this->db->eine(
            "SELECT k.id, k.code, k.bezeichnung, k.status FROM klasse k
              WHERE k.status IN ('beendet', 'bestaetigt') AND k.tisch_abschluss_am IS NULL
                AND (SELECT p.tatami_id FROM phase p WHERE p.klasse_id = k.id ORDER BY p.id DESC LIMIT 1) = ?
              ORDER BY k.sortierung, k.id LIMIT 1",
            [$tatamiId],
        );
        if ($k === null) {
            return null;
        }
        $platzierungen = array_map(
            fn($p) => ['platz' => (int) $p['platz']] + $this->namen->person((int) $p['start_id']) + $this->punktzahl((int) $k['id'], (int) $p['start_id']),
            $this->db->alle('SELECT platz, start_id FROM platzierung WHERE klasse_id = ? ORDER BY platz', [$k['id']]),
        );
        $kaempfe = (int) $this->db->wert(
            "SELECT COUNT(*) FROM begegnung b JOIN phase p ON p.id = b.phase_id WHERE p.klasse_id = ? AND b.status = 'beendet'",
            [$k['id']],
        ) + (int) $this->db->wert("SELECT COUNT(*) FROM auftritt a JOIN phase p ON p.id = a.phase_id WHERE p.klasse_id = ? AND a.status = 'beendet'", [$k['id']]);
        return ['klasse_id' => (int) $k['id'], 'klasse' => $k['code'], 'klasse_name' => $k['bezeichnung'], 'platzierungen' => $platzierungen, 'kaempfe' => $kaempfe];
    }

    /**
     * Punktzahl eines Starters aus seinem letzten gewerteten Vortrag (Finale vor Vorrunde);
     * bei Klassen ohne Punktwertung (Kumite, Flaggen) null.
     *
     * @return array{punkte: float|null, kiken: bool}
     */
    private function punktzahl(int $klasseId, int $startId): array
    {
        $a = $this->db->eine(
            "SELECT a.gesamt, a.kata FROM auftritt a JOIN phase p ON p.id = a.phase_id
              WHERE p.klasse_id = ? AND a.start_id = ? AND a.status = 'beendet' ORDER BY p.id DESC LIMIT 1",
            [$klasseId, $startId],
        );
        return $a === null ? ['punkte' => null, 'kiken' => false] : ['punkte' => (float) $a['gesamt'], 'kiken' => $a['kata'] === 'Kiken'];
    }

    /**
     * Zuletzt von der Tischbesatzung abgeschlossene Gruppe dieser Tatami (für den Hinweis an den Ausruf).
     *
     * @return array{klasse: string, am: string}|null
     */
    public function letzterAbschluss(int $tatamiId): ?array
    {
        $k = $this->db->eine(
            "SELECT k.code, k.tisch_abschluss_am FROM klasse k
              WHERE k.tisch_abschluss_am IS NOT NULL AND k.tisch_abschluss_am > '2000-01-01 00:00:00'
                AND (SELECT p.tatami_id FROM phase p WHERE p.klasse_id = k.id ORDER BY p.id DESC LIMIT 1) = ?
              ORDER BY k.tisch_abschluss_am DESC LIMIT 1",
            [$tatamiId],
        );
        return $k === null ? null : ['klasse' => $k['code'], 'am' => $k['tisch_abschluss_am']];
    }

    /**
     * Übergang zu einer neuen Gruppe – nach einem Tisch-Abschluss oder für die erste Gruppe der
     * Tatami zu Turnierbeginn: die Gruppe hat noch nicht begonnen (nichts läuft, nichts entschieden).
     * Solange zeigt das Tablet „Nächste Gruppe … Ausruf starten“ – auch nach dem Neuladen der
     * Seite – und startet keinen Kampf automatisch.
     *
     * @param array<string, mixed> $zustand Ergebnis von fuerTatami()
     * @return array{klasse: string, am: string}|null
     */
    public function uebergang(int $tatamiId, array $zustand): ?array
    {
        $letzter = $this->letzterAbschluss($tatamiId);
        $neu = $zustand['laufend'] ?? ($zustand['naechste'][0] ?? null);
        // „laufend“ ist bei Vorträgen auch der nächste offene – nur ein laufender Kampf beendet den Übergang
        if ($neu === null || ($zustand['laufend']['status'] ?? null) === 'laeuft' || ($letzter !== null && $neu['klasse'] === $letzter['klasse'])
            || $this->abschlussOffen($tatamiId) !== null) {
            return null;
        }
        $begonnen = (int) $this->db->wert(
            "SELECT (SELECT COUNT(*) FROM begegnung b JOIN phase p ON p.id = b.phase_id WHERE p.klasse_id = ? AND b.status IN ('laeuft', 'beendet'))
                  + (SELECT COUNT(*) FROM auftritt a JOIN phase p ON p.id = a.phase_id WHERE p.klasse_id = ? AND a.status = 'beendet')",
            [$neu['klasse_id'], $neu['klasse_id']],
        );
        // erste Gruppe der Tatami: eigener Schlüssel, damit „Weiter“ am Tablet gemerkt wird
        return $begonnen > 0 ? null : ($letzter ?? ['klasse' => '', 'am' => 'start-' . $neu['klasse_id']]);
    }

    /**
     * Einzelner Eintrag inkl. bisheriger Wertung (für Korrekturen am Tablet).
     *
     * @return array<string, mixed>|null
     */
    public function eintrag(string $typ, int $id): ?array
    {
        $tabelle = $typ === 'auftritt' ? 'auftritt' : 'begegnung';
        $x = $this->db->eine("SELECT * FROM $tabelle WHERE id = ?", [$id]);
        if ($x === null) {
            return null;
        }
        $phase = $this->db->eine(
            'SELECT p.*, k.code, k.bezeichnung AS klasse_name, k.disziplin, k.kampfrichter, k.optionen
               FROM phase p JOIN klasse k ON k.id = p.klasse_id WHERE p.id = ?',
            [$x['phase_id']],
        );
        $eintrag = $typ === 'auftritt' ? $this->auftritt($x, $phase) : $this->begegnung($x, $phase);
        $eintrag['wertung'] = json_decode((string) $x['wertung'], true) ?: [];
        $eintrag['kata_aka'] = $x['kata_aka'] ?? null;
        $eintrag['kata_shiro'] = $x['kata_shiro'] ?? null;
        $eintrag['kata'] = $x['kata'] ?? null;
        $eintrag['entscheidung'] = $x['entscheidung'] ?? null;
        return $eintrag;
    }

    /**
     * @param array<string, mixed> $b
     * @param array<string, mixed> $phase
     * @return array<string, mixed>
     */
    private function begegnung(array $b, array $phase): array
    {
        $klasseId = (int) $phase['klasse_id'];
        $this->nummern[$klasseId] ??= KlassenAnsicht::kampfNummern($this->db, $klasseId);
        $aka = $this->namen->person($b['aka_start_id'] === null ? null : (int) $b['aka_start_id']);
        $shiro = $this->namen->person($b['shiro_start_id'] === null ? null : (int) $b['shiro_start_id']);
        return [
            'typ' => 'begegnung',
            'id' => (int) $b['id'],
            'version' => (int) $b['version'],
            'status' => $b['status'],
            'unterbrochen' => $b['status'] === 'laeuft' && !empty($b['unterbrochen_am']),
            'nr' => $this->nummern[$klasseId][(int) $b['id']] ?? null,
            'klasse_id' => $klasseId,
            'klasse' => $phase['code'],
            'klasse_name' => $phase['klasse_name'],
            'disziplin' => $phase['disziplin'],
            'phase' => $phase['bezeichnung'],
            'runde' => $b['art'] === 'jgj' ? 'Runde ' . $b['runde'] : KoBaum::rundenName((int) ($phase['baum_groesse'] ?: 2), (int) $b['runde'], $b['art']),
            'rundenart' => $b['art'],
            'system' => $b['wertungssystem'],
            'kumite_form' => $b['kumite_form'],
            'kata_liste' => array_values(array_filter(array_map('trim', explode(',', (string) $b['kata_liste'])))),
            'kampfrichter' => (int) $phase['kampfrichter'],
            'punkte' => self::punkteBereich($phase['optionen']),
            'team_kaempfe' => ErgebnisService::teamKaempfe($phase['optionen']),
            'aka' => $aka,
            'shiro' => $shiro,
            'aufruf' => ['aka' => $b['aufruf_aka'] ?? null, 'shiro' => $b['aufruf_shiro'] ?? null, 'fertig' => !empty($b['aufruf_am']), 'am' => $b['aufruf_am'] ?? null],
            'gruppe_aufgerufen' => !empty($phase['ausruf_am']),
            'ausruf_freigegeben' => !empty($phase['ausruf_freigabe_am']),
            'gruppe_begonnen' => $this->gruppeBegonnen((int) $phase['id']),
            'starts' => ['aka' => $b['aka_start_id'] === null ? null : (int) $b['aka_start_id'], 'shiro' => $b['shiro_start_id'] === null ? null : (int) $b['shiro_start_id']],
        ];
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $phase
     * @return array<string, mixed>
     */
    private function auftritt(array $a, array $phase): array
    {
        $optionen = json_decode((string) $phase['optionen'], true) ?: [];
        return [
            'typ' => 'auftritt',
            'id' => (int) $a['id'],
            'version' => (int) $a['version'],
            'status' => $a['status'],
            'nr' => (int) $a['reihenfolge'],
            'klasse_id' => (int) $phase['klasse_id'],
            'klasse' => $phase['code'],
            'klasse_name' => $phase['klasse_name'],
            'disziplin' => $phase['disziplin'],
            'phase' => $phase['bezeichnung'],
            'runde' => $phase['bezeichnung'],
            'system' => 'punkte',
            'kata_liste' => array_values(array_filter(array_map('trim', explode(',', (string) $a['kata_liste'])))),
            'kampfrichter' => (int) $phase['kampfrichter'],
            'punkte' => [
                'min' => (float) ($optionen['punkte_min'] ?? Wertung::PUNKTE_MIN),
                'max' => (float) ($optionen['punkte_max'] ?? Wertung::PUNKTE_MAX),
                'schritt' => (float) ($optionen['punkte_schritt'] ?? 0.1),
            ],
            'starter' => $this->namen->person((int) $a['start_id']),
            'aufruf' => ['starter' => $a['aufruf_starter'] ?? null, 'fertig' => !empty($a['aufruf_am']), 'am' => $a['aufruf_am'] ?? null],
            'gruppe_aufgerufen' => !empty($phase['ausruf_am']),
            'ausruf_freigegeben' => !empty($phase['ausruf_freigabe_am']),
            'gruppe_begonnen' => $this->gruppeBegonnen((int) $phase['id']),
            'starts' => ['starter' => (int) $a['start_id']],
        ];
    }

    /**
     * Hat in der Phase schon ein Kampf begonnen bzw. ein Vortrag stattgefunden? Je Phase, weil Pools
     * einer Klasse auf verschiedenen Tatamis unabhängig voneinander beginnen (je Phase einmal abgefragt).
     */
    private function gruppeBegonnen(int $phaseId): bool
    {
        return $this->begonnen[$phaseId] ??= (int) $this->db->wert(
            "SELECT (SELECT COUNT(*) FROM begegnung b WHERE b.phase_id = ? AND b.status IN ('laeuft', 'beendet'))
                  + (SELECT COUNT(*) FROM auftritt a WHERE a.phase_id = ? AND a.status = 'beendet')",
            [$phaseId, $phaseId],
        ) > 0;
    }

    /**
     * Erlaubter Bereich der Kata-Punktwertung (Tablet-Prüfung).
     *
     * @return array{min: float, max: float, schritt: float}
     */
    private static function punkteBereich(?string $optionenJson): array
    {
        $o = json_decode((string) $optionenJson, true) ?: [];
        return [
            'min' => (float) ($o['punkte_min'] ?? Wertung::PUNKTE_MIN),
            'max' => (float) ($o['punkte_max'] ?? Wertung::PUNKTE_MAX),
            'schritt' => (float) ($o['punkte_schritt'] ?? 0.1),
        ];
    }
}
