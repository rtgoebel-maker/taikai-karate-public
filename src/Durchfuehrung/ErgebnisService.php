<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Domain\Wertung\Wertung;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;

/**
 * Ergebnisse eintragen und korrigieren (PC der Leitung und Tablets),
 * Stichentscheide und Bestätigung der Platzierungen.
 */
final class ErgebnisService
{
    private readonly Fortschreibung $fortschreibung;
    private readonly Protokoll $protokoll;

    public function __construct(private readonly Database $db)
    {
        $this->fortschreibung = new Fortschreibung($db, new PhasenErzeugung($db));
        $this->protokoll = new Protokoll($db);
    }

    /**
     * Trägt das Ergebnis einer Begegnung ein.
     *
     * Eingabe je nach System:
     *   alle:    entscheidung (wertung|kiken|hansoku|shikkaku), gegen (aka|shiro), grund,
     *            kata_aka, kata_shiro, version (Konflikterkennung)
     *   flaggen: flaggen_aka, flaggen_shiro, flaggen_unentschieden
     *   punkte:  aka_werte[], shiro_werte[], hantei_aka, hantei_shiro, hantei_unentschieden
     *   kumite/sanbon: aka_ippon, aka_wazaari, aka_strafen{regel,jogai,mubobi}, shiro_…, zeit_abgelaufen,
     *            saishiai, hauptkampf{…}, hantei_aka, hantei_shiro, hantei_unentschieden
     *   Kumite-Team: kaempfe[] (sieger, aka_ippon …, hansoku, entscheidungskampf)
     *
     * @param array<string, mixed> $eingabe
     */
    public function begegnungEintragen(int $turnierId, int $begegnungId, array $eingabe, bool $darfKorrigieren): void
    {
        $this->db->transaktion(function () use ($turnierId, $begegnungId, $eingabe, $darfKorrigieren) {
            $b = $this->begegnungMitKlasse($turnierId, $begegnungId);
            if (isset($eingabe['version']) && $eingabe['version'] !== '' && (int) $eingabe['version'] !== (int) $b['version']) {
                throw new FachFehler('Das Ergebnis wurde zwischenzeitlich an anderer Stelle geändert. Bitte neu laden.');
            }
            if ($b['aka_start_id'] === null || $b['shiro_start_id'] === null) {
                throw new FachFehler('Die Begegnung ist noch nicht vollständig besetzt.');
            }
            if ($b['status'] === 'freilos') {
                throw new FachFehler('Freilos – hier gibt es nichts einzutragen.');
            }
            $alt = null;
            if ($b['status'] === 'beendet') {
                if (!$darfKorrigieren) {
                    throw new FachFehler('Das Ergebnis ist bereits eingetragen. Korrekturen nur mit Berechtigung der Wettkampfleitung.');
                }
                $this->korrekturVorbereiten($b);
                $alt = ['sieger' => $b['sieger_start_id'], 'entscheidung' => $b['entscheidung'], 'wertung' => $b['wertung']];
            }

            $ergebnis = $this->auswerten($b, $eingabe);
            $sieger = $ergebnis['sieger'] === 'aka' ? (int) $b['aka_start_id'] : (int) $b['shiro_start_id'];
            $this->fortschreibung->begegnungAbschliessen($begegnungId, $sieger, [
                'entscheidung' => $ergebnis['entscheidung'],
                'wertung' => json_encode($ergebnis['daten'], JSON_UNESCAPED_UNICODE),
                'kata_aka' => $this->text($eingabe['kata_aka'] ?? null),
                'kata_shiro' => $this->text($eingabe['kata_shiro'] ?? null),
            ]);
            $this->laufendMarkieren($b);
            $this->protokoll->eintragen($turnierId, 'begegnung', $begegnungId, $alt === null ? 'ergebnis' : 'korrektur', [
                'alt' => $alt,
                'neu' => ['sieger' => $sieger, 'entscheidung' => $ergebnis['entscheidung']],
            ]);
            (new AusrufService($this->db))->uebernehmen((int) $b['klasse_id']); // Folgerunde: Ausruf gilt weiter
            $this->tatamiNachziehen($turnierId);
        });
    }

    /** Markiert eine Begegnung als „läuft“ (für Statusseite und Tablet). */
    public function begegnungStarten(int $turnierId, int $begegnungId): void
    {
        $this->db->transaktion(function () use ($turnierId, $begegnungId) {
            $b = $this->begegnungMitKlasse($turnierId, $begegnungId);
            if ($b['status'] !== 'bereit') {
                throw new FachFehler('Nur bereite Begegnungen können gestartet werden.');
            }
            // auf derselben Tatami läuft immer nur eine Begegnung
            if ($b['tatami_id'] !== null) {
                $this->db->query(
                    "UPDATE begegnung SET status = 'bereit' WHERE status = 'laeuft' AND phase_id IN (SELECT id FROM phase WHERE tatami_id = ?)",
                    [$b['tatami_id']],
                );
            }
            $this->db->update('begegnung', ['status' => 'laeuft', 'unterbrochen_am' => null], ['id' => $begegnungId]);
            $this->laufendMarkieren($b);
            $this->protokoll->revisionErhoehen($turnierId);
        });
    }

    /**
     * Laufenden Kampf unterbrechen (z. B. Verletzung) bzw. fortsetzen. Er bleibt der aktuelle
     * Kampf der Tatami; das Ergebnis kann auch im unterbrochenen Zustand eingetragen werden.
     */
    public function begegnungUnterbrechen(int $turnierId, int $begegnungId, bool $unterbrochen): void
    {
        $this->db->transaktion(function () use ($turnierId, $begegnungId, $unterbrochen) {
            $b = $this->begegnungMitKlasse($turnierId, $begegnungId);
            if ($b['status'] !== 'laeuft') {
                throw new FachFehler('Nur ein laufender Kampf kann unterbrochen oder fortgesetzt werden.');
            }
            $this->db->update('begegnung', ['unterbrochen_am' => $unterbrochen ? date('Y-m-d H:i:s') : null], ['id' => $begegnungId]);
            $this->protokoll->eintragen($turnierId, 'begegnung', $begegnungId, $unterbrochen ? 'unterbrochen' : 'fortgesetzt');
            $this->protokoll->revisionErhoehen($turnierId);
        });
    }

    /**
     * Die Tischbesatzung bestätigt, dass eine Gruppe auf ihrer Tatami abgeschlossen ist
     * (alle Kämpfe eingetragen, Platzierungen stehen). Danach lädt das Tablet die nächste Gruppe.
     */
    public function tischAbschliessen(int $turnierId, int $klasseId): void
    {
        $k = $this->db->eine('SELECT * FROM klasse WHERE id = ? AND turnier_id = ?', [$klasseId, $turnierId])
            ?? throw new FachFehler('Klasse nicht gefunden.');
        if (!in_array($k['status'], ['beendet', 'bestaetigt'], true)) {
            throw new FachFehler("In Gruppe {$k['code']} sind noch nicht alle Kämpfe entschieden.");
        }
        if ($k['tisch_abschluss_am'] !== null) {
            return;
        }
        $this->db->update('klasse', ['tisch_abschluss_am' => date('Y-m-d H:i:s'), 'tisch_abschluss_von' => Session::akteur()], ['id' => $klasseId]);
        $this->protokoll->eintragen($turnierId, 'klasse', $klasseId, 'tisch_abgeschlossen');
        $this->protokoll->revisionErhoehen($turnierId);
    }

    /** Setzt ein Ergebnis zurück (Korrektur durch die Leitung). */
    public function begegnungZuruecksetzen(int $turnierId, int $begegnungId): void
    {
        $this->db->transaktion(function () use ($turnierId, $begegnungId) {
            $b = $this->begegnungMitKlasse($turnierId, $begegnungId);
            if ($b['status'] !== 'beendet' && $b['status'] !== 'laeuft') {
                return;
            }
            if ($b['status'] === 'beendet') {
                $this->korrekturVorbereiten($b);
            }
            $this->db->update('begegnung', [
                'status' => 'bereit', 'sieger_start_id' => null, 'entscheidung' => null, 'wertung' => null,
                'beendet_am' => null, 'kata_aka' => null, 'kata_shiro' => null,
            ], ['id' => $begegnungId]);
            $this->db->query('UPDATE begegnung SET version = version + 1 WHERE id = ?', [$begegnungId]);
            $this->fortschreibung->folgeAktualisieren($begegnungId);
            $this->db->delete('platzierung', ['klasse_id' => $b['klasse_id']]);
            $this->db->update('klasse', ['status' => 'laeuft', 'tisch_abschluss_am' => null, 'tisch_abschluss_von' => null], ['id' => $b['klasse_id']]);
            $this->fortschreibung->phasePruefen((int) $b['phase_id']);
            $this->protokoll->eintragen($turnierId, 'begegnung', $begegnungId, 'zurueckgesetzt', ['sieger' => $b['sieger_start_id']]);
        });
    }

    /**
     * Wertung eines Kata-Vortrags im Punktesystem.
     *
     * @param array<string, mixed> $eingabe werte[] (je Kampfrichter), kata, version | entscheidung=kiken
     */
    public function auftrittEintragen(int $turnierId, int $auftrittId, array $eingabe, bool $darfKorrigieren): void
    {
        $this->db->transaktion(function () use ($turnierId, $auftrittId, $eingabe, $darfKorrigieren) {
            $a = $this->db->eine(
                'SELECT a.*, p.klasse_id, p.typ AS phase_typ, p.tatami_id, k.turnier_id, k.kampfrichter, k.optionen, k.status AS klasse_status
                   FROM auftritt a JOIN phase p ON p.id = a.phase_id JOIN klasse k ON k.id = p.klasse_id WHERE a.id = ?',
                [$auftrittId],
            );
            if ($a === null || (int) $a['turnier_id'] !== $turnierId) {
                throw new FachFehler('Auftritt nicht gefunden.');
            }
            if (isset($eingabe['version']) && $eingabe['version'] !== '' && (int) $eingabe['version'] !== (int) $a['version']) {
                throw new FachFehler('Die Wertung wurde zwischenzeitlich an anderer Stelle geändert. Bitte neu laden.');
            }
            if ($a['status'] === 'beendet') {
                if (!$darfKorrigieren) {
                    throw new FachFehler('Die Wertung ist bereits eingetragen. Korrekturen nur mit Berechtigung der Wettkampfleitung.');
                }
                $this->bestaetigungPruefen((int) $a['klasse_id']);
                if ($a['phase_typ'] === 'punkte_vorrunde') {
                    $this->finalrundeZuruecksetzen((int) $a['klasse_id']);
                }
            }

            if (($eingabe['entscheidung'] ?? '') === 'kiken') {
                $felder = ['gesamt' => 0, 'nebenwertung' => 0, 'wertung' => json_encode(['kiken' => true]), 'kata' => 'Kiken'];
            } else {
                $optionen = json_decode((string) $a['optionen'], true) ?: [];
                $r = Wertung::kataPunkte((array) ($eingabe['werte'] ?? []), $optionen, (int) $a['kampfrichter']);
                $felder = [
                    'gesamt' => $r['gesamt'],
                    'nebenwertung' => $r['nebenwertung'],
                    'wertung' => json_encode($r),
                    'kata' => $this->text($eingabe['kata'] ?? null),
                ];
            }
            $this->db->query('UPDATE auftritt SET version = version + 1 WHERE id = ?', [$auftrittId]);
            $this->db->update('auftritt', $felder + [
                'status' => 'beendet',
                'beendet_am' => date('Y-m-d H:i:s'),
                'erfasst_von' => Session::akteur(),
            ], ['id' => $auftrittId]);
            $this->db->update('phase', ['status' => 'laeuft'], ['id' => $a['phase_id'], 'status' => 'bereit']);
            if ($a['klasse_status'] === 'ausgelost') {
                $this->db->update('klasse', ['status' => 'laeuft'], ['id' => $a['klasse_id']]);
            }
            $this->fortschreibung->phasePruefen((int) $a['phase_id']);
            $this->protokoll->eintragen($turnierId, 'auftritt', $auftrittId, 'wertung', ['gesamt' => $felder['gesamt']]);
            (new AusrufService($this->db))->uebernehmen((int) $a['klasse_id']); // Finalrunde: Ausruf gilt weiter
            $this->tatamiNachziehen($turnierId);
        });
    }

    /**
     * Nach Sperre/Abmeldung eines Teilnehmers: offene Begegnungen kampflos
     * werten, offene Auftritte als Kiken abschließen.
     *
     * @return int Anzahl kampflos gewerteter Begegnungen/Auftritte
     */
    public function ausfaelleVerarbeiten(int $turnierId, int $teilnahmeId): int
    {
        return $this->db->transaktion(function () use ($turnierId, $teilnahmeId) {
            $anzahl = 0;
            foreach ($this->db->spalte(
                "SELECT b.id FROM begegnung b JOIN start s ON s.id IN (b.aka_start_id, b.shiro_start_id)
                  WHERE s.teilnahme_id = ? AND b.status IN ('bereit', 'laeuft')",
                [$teilnahmeId],
            ) as $id) {
                $this->db->update('begegnung', ['status' => 'bereit'], ['id' => $id]);
                $anzahl += $this->fortschreibung->ausfallPruefen((int) $id) ? 1 : 0;
            }
            foreach ($this->db->spalte(
                "SELECT a.id FROM auftritt a JOIN start s ON s.id = a.start_id JOIN phase p ON p.id = a.phase_id
                  WHERE s.teilnahme_id = ? AND a.status = 'offen' AND p.status <> 'wartet'",
                [$teilnahmeId],
            ) as $id) {
                $this->auftrittEintragen($turnierId, (int) $id, ['entscheidung' => 'kiken'], true);
                $anzahl++;
            }
            $this->tatamiNachziehen($turnierId);
            return $anzahl;
        });
    }

    /**
     * Stichentscheid der Leitung bei Gleichstand.
     *
     * @param array<int|string, int|string|null> $rang Start-ID (jeder gegen jeden) bzw. Auftritt-ID (Punkte) → Rang (1 = vorne)
     */
    public function stichentscheid(int $turnierId, int $klasseId, string $art, array $rang): void
    {
        $this->db->transaktion(function () use ($turnierId, $klasseId, $art, $rang) {
            $this->klasse($turnierId, $klasseId);
            $this->bestaetigungPruefen($klasseId);
            foreach ($rang as $id => $wert) {
                $wert = $wert === '' || $wert === null ? null : (int) $wert;
                if ($art === 'auftritt') {
                    $this->db->query(
                        'UPDATE auftritt SET stichentscheid = ? WHERE id = ? AND phase_id IN (SELECT id FROM phase WHERE klasse_id = ?)',
                        [$wert, (int) $id, $klasseId],
                    );
                } else {
                    $this->db->query('UPDATE start SET stichentscheid = ? WHERE id = ? AND klasse_id = ?', [$wert, (int) $id, $klasseId]);
                }
            }
            $this->fortschreibung->finalrundeErneutPruefen($klasseId);
            if ((int) $this->db->wert("SELECT COUNT(*) FROM phase WHERE klasse_id = ? AND status <> 'beendet'", [$klasseId]) === 0) {
                $this->fortschreibung->klasseAbschliessen($klasseId);
            }
            $this->protokoll->eintragen($turnierId, 'klasse', $klasseId, 'stichentscheid', $rang);
            $this->tatamiNachziehen($turnierId);
        });
    }

    /** Dynamische Verteilung: frei gewordene Tatamis bekommen die nächste Gruppe. */
    private function tatamiNachziehen(int $turnierId): void
    {
        (new PlanService($this->db))->nachziehen($turnierId);
    }

    public function bestaetigen(int $turnierId, int $klasseId): void
    {
        $klasse = $this->klasse($turnierId, $klasseId);
        if ($klasse['status'] !== 'beendet') {
            throw new FachFehler("Klasse {$klasse['code']} ist noch nicht abgeschlossen oder hat einen offenen Gleichstand.");
        }
        $this->db->transaktion(function () use ($turnierId, $klasseId) {
            $this->db->query(
                'UPDATE platzierung SET bestaetigt_am = ?, bestaetigt_von = ? WHERE klasse_id = ?',
                [date('Y-m-d H:i:s'), Session::akteur(), $klasseId],
            );
            $this->db->update('klasse', ['status' => 'bestaetigt'], ['id' => $klasseId]);
            $this->protokoll->eintragen($turnierId, 'klasse', $klasseId, 'bestaetigt');
        });
    }

    public function bestaetigungAufheben(int $turnierId, int $klasseId): void
    {
        $klasse = $this->klasse($turnierId, $klasseId);
        if ($klasse['status'] !== 'bestaetigt') {
            return;
        }
        $this->db->transaktion(function () use ($turnierId, $klasseId) {
            $this->db->query('UPDATE platzierung SET bestaetigt_am = NULL, bestaetigt_von = NULL WHERE klasse_id = ?', [$klasseId]);
            $this->db->update('klasse', ['status' => 'beendet'], ['id' => $klasseId]);
            $this->protokoll->eintragen($turnierId, 'klasse', $klasseId, 'bestaetigung_aufgehoben');
        });
    }

    /**
     * @param array<string, mixed> $b Begegnung mit Klassenfeldern
     * @param array<string, mixed> $e Eingabe
     * @return array{sieger: string, entscheidung: string, daten: array<string, mixed>}
     */
    private function auswerten(array $b, array $e): array
    {
        $entscheidung = (string) ($e['entscheidung'] ?? 'wertung');
        if (in_array($entscheidung, Wertung::KAMPFLOS, true)) {
            return Wertung::kampflos($entscheidung, (string) ($e['gegen'] ?? ''), $this->text($e['grund'] ?? null));
        }
        $kr = (int) $b['kampfrichter'];
        $hantei = (isset($e['hantei_aka']) && $e['hantei_aka'] !== '' && isset($e['hantei_shiro']) && $e['hantei_shiro'] !== '')
            ? ['aka' => (int) $e['hantei_aka'], 'shiro' => (int) $e['hantei_shiro'], 'unentschieden' => (int) ($e['hantei_unentschieden'] ?? 0)]
            : null;

        if ($b['disziplin'] === 'kumite_team' && $b['wertungssystem'] === 'kumite') {
            $kaempfe = array_values(array_filter((array) ($e['kaempfe'] ?? []), static fn($k) => ($k['sieger'] ?? '') !== ''));
            if ($kaempfe === []) {
                throw new FachFehler('Bitte die Einzelkämpfe eintragen.');
            }
            return Wertung::team(array_map(static fn($k) => [
                'sieger' => (string) $k['sieger'],
                'aka_ippon' => (int) ($k['aka_ippon'] ?? 0), 'aka_wazaari' => (int) ($k['aka_wazaari'] ?? 0),
                'shiro_ippon' => (int) ($k['shiro_ippon'] ?? 0), 'shiro_wazaari' => (int) ($k['shiro_wazaari'] ?? 0),
                'hansoku' => !empty($k['hansoku']),
                'entscheidungskampf' => !empty($k['entscheidungskampf']),
            ], $kaempfe), self::teamKaempfe($b['optionen']));
        }
        return match ($b['wertungssystem']) {
            'flaggen' => Wertung::flaggen((int) ($e['flaggen_aka'] ?? -1), (int) ($e['flaggen_shiro'] ?? -1), $kr, (int) ($e['flaggen_unentschieden'] ?? 0)),
            'punkte' => Wertung::punkteBegegnung((array) ($e['aka_werte'] ?? []), (array) ($e['shiro_werte'] ?? []), json_decode((string) $b['optionen'], true) ?: [], $kr, $hantei),
            'kumite' => Wertung::kumite($e, $kr, $hantei),
            'sanbon' => Wertung::kumite($e, $kr, $hantei, Wertung::ZIEL_SANBON),
            default => throw new FachFehler('Unbekanntes Wertungssystem.'),
        };
    }

    /** Einzelkämpfe je Mannschaftskampf (Ausschreibung: ungerade, mindestens 3; Standard 3). */
    public static function teamKaempfe(?string $optionenJson): int
    {
        $anzahl = (int) ((json_decode((string) $optionenJson, true) ?: [])['team_kaempfe'] ?? 3);
        return in_array($anzahl, [3, 5, 7], true) ? $anzahl : 3;
    }

    /**
     * Prüft, ob eine Korrektur möglich ist: keine entschiedenen Folgekämpfe,
     * Klasse nicht bestätigt; eine noch ergebnislose Finalrunde wird zurückgesetzt.
     *
     * @param array<string, mixed> $b
     */
    private function korrekturVorbereiten(array $b): void
    {
        $this->bestaetigungPruefen((int) $b['klasse_id']);
        $folge = $this->db->eine(
            "SELECT * FROM begegnung WHERE (aka_quelle IN (?, ?) OR shiro_quelle IN (?, ?)) AND status IN ('beendet', 'laeuft')",
            ["W:{$b['id']}", "L:{$b['id']}", "W:{$b['id']}", "L:{$b['id']}"],
        );
        if ($folge !== null) {
            $nr = KlassenAnsicht::kampfNummern($this->db, (int) $b['klasse_id'])[(int) $folge['id']] ?? '?';
            throw new FachFehler("Der Folgekampf (Kampf $nr) ist bereits entschieden. Bitte zuerst dort korrigieren.");
        }
        if ($b['phase_typ'] === 'pool') {
            $this->finalrundeZuruecksetzen((int) $b['klasse_id']);
        }
    }

    /** Setzt eine bereits befüllte Finalrunde ohne Ergebnisse auf „wartet“ zurück. */
    private function finalrundeZuruecksetzen(int $klasseId): void
    {
        $final = $this->db->eine("SELECT * FROM phase WHERE klasse_id = ? AND typ IN ('finalrunde', 'punkte_finale') AND status <> 'wartet'", [$klasseId]);
        if ($final === null) {
            return;
        }
        $ergebnisse = (int) $this->db->wert("SELECT COUNT(*) FROM begegnung WHERE phase_id = ? AND status IN ('beendet', 'laeuft')", [$final['id']])
            + (int) $this->db->wert("SELECT COUNT(*) FROM auftritt WHERE phase_id = ? AND status = 'beendet'", [$final['id']]);
        if ($ergebnisse > 0) {
            throw new FachFehler('In der Finalrunde liegen bereits Ergebnisse vor. Eine Korrektur der Vorrunde ist nicht mehr möglich.');
        }
        $this->db->delete('begegnung', ['phase_id' => $final['id']]);
        $this->db->delete('auftritt', ['phase_id' => $final['id']]);
        $this->db->delete('platzierung', ['klasse_id' => $klasseId]);
        $this->db->update('phase', ['status' => 'wartet', 'starter' => null, 'baum_groesse' => null], ['id' => $final['id']]);
        $this->db->update('klasse', ['status' => 'laeuft'], ['id' => $klasseId]);
    }

    private function bestaetigungPruefen(int $klasseId): void
    {
        if ($this->db->wert('SELECT status FROM klasse WHERE id = ?', [$klasseId]) === 'bestaetigt') {
            throw new FachFehler('Die Platzierungen dieser Klasse sind bereits bestätigt. Bitte zuerst die Bestätigung aufheben.');
        }
    }

    /** @param array<string, mixed> $b */
    private function laufendMarkieren(array $b): void
    {
        $this->db->update('phase', ['status' => 'laeuft'], ['id' => $b['phase_id'], 'status' => 'bereit']);
        $this->db->update('klasse', ['status' => 'laeuft'], ['id' => $b['klasse_id'], 'status' => 'ausgelost']);
    }

    /** @return array<string, mixed> */
    private function begegnungMitKlasse(int $turnierId, int $begegnungId): array
    {
        $b = $this->db->eine(
            'SELECT b.*, p.klasse_id, p.typ AS phase_typ, p.tatami_id, k.turnier_id, k.kampfrichter, k.optionen, k.disziplin, k.status AS klasse_status
               FROM begegnung b JOIN phase p ON p.id = b.phase_id JOIN klasse k ON k.id = p.klasse_id WHERE b.id = ?',
            [$begegnungId],
        );
        if ($b === null || (int) $b['turnier_id'] !== $turnierId) {
            throw new FachFehler('Begegnung nicht gefunden.');
        }
        return $b;
    }

    /** @return array<string, mixed> */
    private function klasse(int $turnierId, int $klasseId): array
    {
        return $this->db->eine('SELECT * FROM klasse WHERE id = ? AND turnier_id = ?', [$klasseId, $turnierId])
            ?? throw new FachFehler('Klasse nicht gefunden.');
    }

    private function text(mixed $wert): ?string
    {
        $t = trim((string) $wert);
        return $t === '' ? null : mb_substr($t, 0, 60);
    }
}
