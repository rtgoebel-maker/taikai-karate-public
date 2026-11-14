<?php

declare(strict_types=1);

namespace Wettkampf\Datenschutz;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Kern\Uuid;

/**
 * Pseudonymisierung nach Ablauf der Löschfrist (ein Turnier) bzw. auf Antrag (eine Person, Art. 17 DSGVO).
 *
 * Erhalten bleiben Klassen, Starts, Begegnungen, Auftritte, Wertungen, Platzierungen, Vereine, Geschlecht, Graduierung
 * und Alter – also die Statistik. Namen werden durch „Starter N“ ersetzt; Geburtsdaten, Ausweisnummern, Kontaktdaten und
 * Inhalte der Anmeldungen, Mails, Nachrichten, Korrekturanträge, Gerätenamen und alle Personenangaben im
 * Änderungsverlauf werden gelöscht.
 *
 * Teilnehmer (Tabelle athlet) gelten über Turniere hinweg: Wer noch in einem anderen, nicht pseudonymisierten Turnier
 * steht, bekommt für dieses Turnier einen eigenen pseudonymen Datensatz – das andere Turnier bleibt unverändert.
 */
final class Pseudonymisierung
{
    /** Platzhalter für Pflichtfelder (NOT NULL), deren Inhalt entfernt wurde. */
    public const ENTFERNT = '–';

    public function __construct(private readonly Database $db) {}

    /**
     * Ein Turnier pseudonymisieren.
     *
     * @param string $eigeneInstanz Instanz-ID dieser Installation (ein an den Hallen-Laptop übergebenes Turnier wird dort geführt)
     * @return array{starter: int, behalten: int, anmeldungen: int, mails: int}
     */
    public function turnier(int $turnierId, string $eigeneInstanz): array
    {
        $turnier = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$turnierId]) ?? throw new FachFehler('Turnier nicht gefunden.');
        $e = DatenschutzEinstellungen::lesen($turnier);
        if ($e['pseudonymisiert_am'] !== null) {
            throw new FachFehler('Dieses Turnier ist bereits pseudonymisiert (' . date('d.m.Y', (int) strtotime($e['pseudonymisiert_am'])) . ').');
        }
        if ((string) $turnier['datum'] >= date('Y-m-d')) {
            throw new FachFehler('Pseudonymisieren geht erst nach dem Turniertag.');
        }
        $fuehrung = (string) ($turnier['fuehrung_instanz'] ?? '');
        if ($fuehrung !== '' && !hash_equals($eigeneInstanz, $fuehrung)) {
            throw new FachFehler('Das Turnier wird gerade von einer anderen Installation geführt (Hallen-Laptop) – erst nach der Rückgabe pseudonymisieren.');
        }

        return $this->db->transaktion(function () use ($turnierId, $e): array {
            // Platzierte 1–3 (Einzel) behalten auf Wunsch ihren Namen (Chronik)
            $behalten = $e['platzierte_behalten'] ? array_map('intval', $this->db->spalte(
                'SELECT DISTINCT s.teilnahme_id FROM platzierung p JOIN start s ON s.id = p.start_id JOIN klasse k ON k.id = p.klasse_id
                  WHERE k.turnier_id = ? AND p.platz <= 3 AND s.teilnahme_id IS NOT NULL',
                [$turnierId],
            )) : [];
            $namen = [];
            $nr = 0;
            foreach ($this->db->alle(
                'SELECT ta.id, ta.athlet_id, a.vorname, a.nachname FROM teilnahme ta JOIN athlet a ON a.id = ta.athlet_id WHERE ta.turnier_id = ? ORDER BY ta.id',
                [$turnierId],
            ) as $t) {
                $nr++;
                $behalte = in_array((int) $t['id'], $behalten, true);
                $neuerName = $behalte ? null : ['vorname' => 'Starter', 'nachname' => (string) $nr];
                $this->athletAbloesen((int) $t['id'], (int) $t['athlet_id'], $turnierId, $neuerName);
                $namen[(int) $t['id']] = $behalte ? trim($t['vorname'] . ' ' . $t['nachname']) : 'Starter ' . $nr;
            }
            $this->db->query('UPDATE teilnahme SET vermerk = NULL WHERE turnier_id = ?', [$turnierId]);
            $this->auslosungsprotokolle($turnierId, $namen);

            // Online-Anmeldungen: Dojo, Beträge und Zahlungsstand bleiben (Statistik), Personenangaben gehen
            $anmeldungen = 0;
            foreach ($this->db->alle('SELECT id, uuid, daten FROM anmeldung WHERE turnier_id = ?', [$turnierId]) as $a) {
                $this->db->update('anmeldung', [
                    'code' => bin2hex(random_bytes(12)), // alter Bearbeitungslink gilt nicht mehr
                    'ansprechpartner' => self::ENTFERNT, 'email' => self::ENTFERNT, 'telefon' => null, 'bemerkung' => null,
                    'daten' => self::anmeldedatenPseudonym((string) $a['daten']),
                    'grund' => null, 'uebernahme' => null, 'freigegeben_von' => null, 'zahlung_notiz' => null, 'zahlung_von' => null,
                ], ['id' => $a['id']]);
                $this->db->query('DELETE FROM konto_anmeldung WHERE anmeldung_uuid = ?', [$a['uuid']]);
                $anmeldungen++;
            }

            $mails = (int) $this->db->wert('SELECT COUNT(*) FROM mail_ausgang WHERE turnier_id = ?', [$turnierId]);
            $this->db->query('DELETE FROM mail_ausgang WHERE turnier_id = ?', [$turnierId]);
            $this->db->query('DELETE FROM tatami_geraet WHERE turnier_id = ?', [$turnierId]);
            $this->db->query('UPDATE tatami_nachricht SET text = ?, von = ?, bestaetigt_von = NULL WHERE turnier_id = ?', [self::ENTFERNT, self::ENTFERNT, $turnierId]);
            $this->db->query('UPDATE korrekturantrag SET neu = ?, begruendung = ?, antwort = NULL, erstellt_von = ?, bearbeitet_von = NULL WHERE turnier_id = ?', [self::ENTFERNT, self::ENTFERNT, self::ENTFERNT, $turnierId]);
            $this->db->query('UPDATE feedback SET von = ?, geraet = NULL, screenshot = NULL, screenshot_mime = NULL WHERE turnier_id = ?', [self::ENTFERNT, $turnierId]);

            // Wer hat was eingetragen: Personen- bzw. Gerätenamen aus Ergebnissen und Verlauf
            $klassen = 'SELECT id FROM klasse WHERE turnier_id = ?';
            $phasen = "SELECT id FROM phase WHERE klasse_id IN ($klassen)";
            $this->db->query("UPDATE begegnung SET erfasst_von = NULL WHERE phase_id IN ($phasen)", [$turnierId]);
            $this->db->query("UPDATE auftritt SET erfasst_von = NULL WHERE phase_id IN ($phasen)", [$turnierId]);
            $this->db->query("UPDATE platzierung SET bestaetigt_von = NULL WHERE klasse_id IN ($klassen)", [$turnierId]);
            $this->db->query("UPDATE auslosung SET erstellt_von = NULL WHERE klasse_id IN ($klassen)", [$turnierId]);
            $this->db->query("UPDATE zusammenlegung SET erstellt_von = NULL, zustimmung = NULL WHERE ziel_klasse_id IN ($klassen)", [$turnierId]);
            $this->db->query('UPDATE klasse SET tisch_abschluss_von = NULL WHERE turnier_id = ?', [$turnierId]);
            $this->db->query('UPDATE sync_uebergabe SET erstellt_von = ? WHERE turnier_id = ?', [self::ENTFERNT, $turnierId]);
            $this->db->query('UPDATE turnier_rolle SET erstellt_von = NULL WHERE turnier_id = ?', [$turnierId]);
            $this->db->query('UPDATE audit_log SET benutzer = NULL, daten = NULL WHERE turnier_id = ?', [$turnierId]);

            $ergebnis = ['starter' => $nr - count($behalten), 'behalten' => count($behalten), 'anmeldungen' => $anmeldungen, 'mails' => $mails];
            (new DatenschutzEinstellungen($this->db))->vermerken($turnierId, 'pseudonymisiert_am', date('Y-m-d H:i:s'));
            (new Protokoll($this->db))->eintragen($turnierId, 'turnier', $turnierId, 'pseudonymisiert', $ergebnis);
            // eigener Eintrag ohne Namen des Auslösers
            $this->db->query('UPDATE audit_log SET benutzer = NULL WHERE turnier_id = ? AND aktion = ?', [$turnierId, 'pseudonymisiert']);
            return $ergebnis;
        });
    }

    /**
     * Eine Person (alle Datensätze athlet mit diesen IDs) über alle Turniere pseudonymisieren – Löschantrag nach Art. 17.
     * Ergebnisse und Platzierungen bleiben als „Gelöscht N“ erhalten, damit Turnierbäume und Statistik stimmen.
     *
     * @param list<int> $athletIds
     * @return int Anzahl der betroffenen Teilnahmen
     */
    public function person(array $athletIds): int
    {
        if ($athletIds === []) {
            return 0;
        }
        return $this->db->transaktion(function () use ($athletIds): int {
            $teilnahmen = 0;
            foreach ($athletIds as $athletId) {
                $a = $this->db->eine('SELECT * FROM athlet WHERE id = ?', [$athletId]);
                if ($a === null) {
                    continue;
                }
                $name = ['vorname' => 'Gelöscht', 'nachname' => (string) $athletId];
                $ids = array_map('intval', $this->db->spalte('SELECT id FROM teilnahme WHERE athlet_id = ?', [$athletId]));
                foreach ($this->db->alle('SELECT DISTINCT turnier_id FROM teilnahme WHERE athlet_id = ?', [$athletId]) as $t) {
                    $namen = [];
                    foreach ($this->db->spalte('SELECT id FROM teilnahme WHERE athlet_id = ? AND turnier_id = ?', [$athletId, $t['turnier_id']]) as $tid) {
                        $namen[(int) $tid] = 'Gelöscht ' . $athletId;
                    }
                    $this->auslosungsprotokolle((int) $t['turnier_id'], $namen);
                    // in den Online-Anmeldungen dieses Turniers die Zeile der Person (Name + Geburtsdatum)
                    foreach ($this->db->alle('SELECT id, daten FROM anmeldung WHERE turnier_id = ?', [$t['turnier_id']]) as $an) {
                        $neu = self::anmeldedatenOhnePerson((string) $an['daten'], (string) $a['vorname'], (string) $a['nachname'], (string) ($a['geburtsdatum'] ?? ''));
                        if ($neu !== null) {
                            $this->db->update('anmeldung', ['daten' => $neu], ['id' => $an['id']]);
                        }
                    }
                }
                $this->db->update('athlet', $name + ['geburtsdatum' => null, 'ausweis_nr' => null], ['id' => $athletId]);
                if ($ids !== []) {
                    $platz = implode(',', array_fill(0, count($ids), '?'));
                    $this->db->query("UPDATE teilnahme SET vermerk = NULL WHERE id IN ($platz)", $ids);
                    $this->db->query("UPDATE audit_log SET daten = NULL WHERE entitaet = 'teilnahme' AND entitaet_id IN ($platz)", $ids);
                    $this->db->query("UPDATE korrekturantrag SET neu = ?, begruendung = ?, antwort = NULL WHERE teilnahme_id IN ($platz)", [self::ENTFERNT, self::ENTFERNT, ...$ids]);
                }
                $this->db->query("UPDATE audit_log SET daten = NULL WHERE entitaet = 'athlet' AND entitaet_id = ?", [$athletId]);
                // Freitext im Verlauf, der den vollen Namen enthält
                $voll = trim($a['vorname'] . ' ' . $a['nachname']);
                if (mb_strlen($voll) >= 5) {
                    foreach ([$voll, trim($a['nachname'] . ', ' . $a['vorname'])] as $muster) {
                        $this->db->query("UPDATE audit_log SET daten = NULL WHERE daten LIKE ? ESCAPE '!'", ['%' . self::like($muster) . '%']);
                    }
                }
                (new Protokoll($this->db))->eintragen(null, 'athlet', $athletId, 'geloescht_auf_antrag', ['teilnahmen' => count($ids)]);
                $teilnahmen += count($ids);
            }
            return $teilnahmen;
        });
    }

    /**
     * Teilnahme von ihrem Athleten lösen: Wird der Athlet noch in einem anderen, nicht pseudonymisierten Turnier gebraucht,
     * bekommt die Teilnahme einen eigenen (pseudonymen) Datensatz, sonst wird der Athlet selbst geändert.
     *
     * @param array{vorname: string, nachname: string}|null $name neuer Name; null = Name behalten (Platzierte, Chronik)
     */
    private function athletAbloesen(int $teilnahmeId, int $athletId, int $turnierId, ?array $name): void
    {
        $anderswo = (int) $this->db->wert('SELECT COUNT(*) FROM teilnahme WHERE athlet_id = ? AND turnier_id <> ?', [$athletId, $turnierId]) > 0
            || (int) $this->db->wert('SELECT COUNT(*) FROM teilnahme WHERE athlet_id = ? AND turnier_id = ? AND id <> ?', [$athletId, $turnierId, $teilnahmeId]) > 0;
        $felder = ['geburtsdatum' => null, 'ausweis_nr' => null] + ($name ?? []);
        if (!$anderswo) {
            $this->db->update('athlet', $felder, ['id' => $athletId]);
            return;
        }
        $a = $this->db->eine('SELECT * FROM athlet WHERE id = ?', [$athletId]) ?? throw new FachFehler('Teilnehmer nicht gefunden.');
        $neu = $this->db->insert('athlet', $felder + [
            'uuid' => Uuid::v4(),
            'vorname' => $a['vorname'], 'nachname' => $a['nachname'],
            'geschlecht' => $a['geschlecht'], 'graduierung' => $a['graduierung'], 'verein_id' => $a['verein_id'],
        ]);
        $this->db->update('teilnahme', ['athlet_id' => $neu], ['id' => $teilnahmeId]);
    }

    /**
     * Starternamen in den Auslosungsprotokollen ersetzen (Schlüssel = Start-ID).
     *
     * @param array<int, string> $namen Teilnahme-ID → neuer Name
     */
    private function auslosungsprotokolle(int $turnierId, array $namen): void
    {
        if ($namen === []) {
            return;
        }
        $startZuTeilnahme = [];
        foreach ($this->db->alle('SELECT s.id, s.teilnahme_id FROM start s JOIN klasse k ON k.id = s.klasse_id WHERE k.turnier_id = ? AND s.teilnahme_id IS NOT NULL', [$turnierId]) as $s) {
            $startZuTeilnahme[(int) $s['id']] = (int) $s['teilnahme_id'];
        }
        foreach ($this->db->alle('SELECT au.id, au.protokoll FROM auslosung au JOIN klasse k ON k.id = au.klasse_id WHERE k.turnier_id = ?', [$turnierId]) as $au) {
            $p = json_decode((string) $au['protokoll'], true);
            if (!is_array($p) || !is_array($p['starter'] ?? null)) {
                continue;
            }
            $geaendert = false;
            foreach ($p['starter'] as $startId => $alt) {
                $teilnahme = $startZuTeilnahme[(int) $startId] ?? null;
                if ($teilnahme !== null && isset($namen[$teilnahme]) && $alt !== $namen[$teilnahme]) {
                    $p['starter'][$startId] = $namen[$teilnahme];
                    $geaendert = true;
                }
            }
            if ($geaendert) {
                $this->db->update('auslosung', ['protokoll' => json_encode($p, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)], ['id' => $au['id']]);
            }
        }
    }

    /** Anmeldedaten mit gleicher Struktur (Startgeld bleibt nachrechenbar), aber ohne Namen und Geburtsdaten. */
    private static function anmeldedatenPseudonym(string $json): string
    {
        $d = json_decode($json, true);
        $d = is_array($d) ? $d : [];
        $teilnehmer = [];
        $nr = 0;
        foreach ((array) ($d['teilnehmer'] ?? []) as $schluessel => $t) {
            $nr++;
            $teilnehmer[$schluessel] = array_merge((array) $t, ['vorname' => 'Starter', 'nachname' => (string) $nr, 'geburtsdatum' => '']);
        }
        return (string) json_encode([
            'teilnehmer' => $teilnehmer,
            'teams' => array_values((array) ($d['teams'] ?? [])),
            'pseudonymisiert' => true,
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** Anmeldedaten ohne die eine Person; null = Person kommt nicht vor. */
    private static function anmeldedatenOhnePerson(string $json, string $vorname, string $nachname, string $geburtsdatum): ?string
    {
        $d = json_decode($json, true);
        if (!is_array($d) || !is_array($d['teilnehmer'] ?? null)) {
            return null;
        }
        $treffer = false;
        foreach ($d['teilnehmer'] as $schluessel => $t) {
            if (!is_array($t)) {
                continue;
            }
            $gleich = mb_strtolower(trim((string) ($t['vorname'] ?? ''))) === mb_strtolower(trim($vorname))
                && mb_strtolower(trim((string) ($t['nachname'] ?? ''))) === mb_strtolower(trim($nachname))
                && ($geburtsdatum === '' || (string) ($t['geburtsdatum'] ?? '') === $geburtsdatum);
            if ($gleich) {
                $d['teilnehmer'][$schluessel]['vorname'] = 'Gelöscht';
                $d['teilnehmer'][$schluessel]['nachname'] = '';
                $d['teilnehmer'][$schluessel]['geburtsdatum'] = '';
                $treffer = true;
            }
        }
        return $treffer ? (string) json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null;
    }

    /** Platzhalter von LIKE wörtlich nehmen (Escape-Zeichen „!“, in SQLite und MySQL gleich). */
    private static function like(string $text): string
    {
        return strtr($text, ['!' => '!!', '%' => '!%', '_' => '!_']);
    }
}
