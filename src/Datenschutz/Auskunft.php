<?php

declare(strict_types=1);

namespace Wettkampf\Datenschutz;

use Wettkampf\Kern\Db\Database;

/**
 * Auskunft nach Art. 15 DSGVO: alle gespeicherten Angaben zu einer Person – als Starter (Tabelle athlet, über alle Turniere)
 * oder als Kontakt (E-Mail-Adresse: Anmeldungen, Konto, versendete Mails). Dazu je Turnier die Datenschutzhinweise
 * (Zwecke, Empfänger, Speicherdauer, Rechte, Herkunft der Daten).
 *
 * Angaben über andere Personen (Gegner, meldende Person, Tischbesatzung) stehen nicht im Bericht.
 */
final class Auskunft
{
    public function __construct(private readonly Database $db) {}

    /**
     * Starter nach Name (Teil von Vor- oder Nachname bzw. „Vorname Nachname“), optional Geburtsdatum.
     *
     * @return list<array<string, mixed>>
     */
    public function suchen(string $name, string $geburtsdatum = ''): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if (mb_strlen($name) < 2) {
            return [];
        }
        $muster = '%' . mb_strtolower(strtr($name, ['!' => '!!', '%' => '!%', '_' => '!_'])) . '%';
        // Verkettung je Datenbank (nur feste Ausdrücke, keine Eingaben im SQL)
        [$vollname, $umgekehrt] = $this->db->driver() === 'mysql'
            ? ["CONCAT(a.vorname, ' ', a.nachname)", "CONCAT(a.nachname, ', ', a.vorname)"]
            : ["a.vorname || ' ' || a.nachname", "a.nachname || ', ' || a.vorname"];
        $sql = "SELECT a.id, a.vorname, a.nachname, a.geburtsdatum, a.geschlecht, a.graduierung, v.name AS verein,
                       (SELECT COUNT(*) FROM teilnahme ta WHERE ta.athlet_id = a.id) AS teilnahmen
                  FROM athlet a LEFT JOIN verein v ON v.id = a.verein_id
                 WHERE (LOWER(a.vorname) LIKE ? ESCAPE '!' OR LOWER(a.nachname) LIKE ? ESCAPE '!'
                        OR LOWER($vollname) LIKE ? ESCAPE '!' OR LOWER($umgekehrt) LIKE ? ESCAPE '!')";
        $werte = [$muster, $muster, $muster, $muster];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $geburtsdatum)) {
            $sql .= ' AND a.geburtsdatum = ?';
            $werte[] = $geburtsdatum;
        }
        return $this->db->alle($sql . ' ORDER BY a.nachname, a.vorname, a.geburtsdatum LIMIT 100', $werte);
    }

    /**
     * Bericht zu einem oder mehreren Datensätzen derselben Person (z. B. doppelt angelegt).
     *
     * @param list<int> $athletIds
     * @return array<string, mixed>
     */
    public function starter(array $athletIds): array
    {
        $personen = [];
        $turniere = [];
        foreach (array_unique($athletIds) as $id) {
            $a = $this->db->eine('SELECT a.*, v.name AS verein FROM athlet a LEFT JOIN verein v ON v.id = a.verein_id WHERE a.id = ?', [$id]);
            if ($a === null) {
                continue;
            }
            $teilnahmen = [];
            $teilnahmeIds = [];
            foreach ($this->db->alle(
                'SELECT ta.*, t.id AS tid, t.name AS turnier, t.datum FROM teilnahme ta JOIN turnier t ON t.id = ta.turnier_id WHERE ta.athlet_id = ? ORDER BY t.datum, t.id',
                [$id],
            ) as $ta) {
                $teilnahmeIds[] = (int) $ta['id'];
                $turniere[(int) $ta['tid']] = true;
                $teilnahmen[] = [
                    'turnier' => $ta['turnier'], 'datum' => $ta['datum'], 'status' => $ta['status'], 'startnummer' => $ta['startnummer'],
                    'disziplinen' => $ta['disziplinen'], 'alter_manuell' => $ta['alter_manuell'], 'ausweis_geprueft' => (bool) $ta['ausweis_geprueft'],
                    'nachmeldung' => (bool) $ta['nachmeldung'], 'vermerk' => $ta['vermerk'],
                    'starts' => $this->starts((int) $ta['id']),
                    'anmeldungen' => $this->anmeldezeilen((int) $ta['tid'], (string) $a['vorname'], (string) $a['nachname']),
                ];
            }
            $personen[] = [
                'stammdaten' => [
                    'vorname' => $a['vorname'], 'nachname' => $a['nachname'], 'geburtsdatum' => $a['geburtsdatum'], 'geschlecht' => $a['geschlecht'],
                    'graduierung' => grad((int) $a['graduierung']), 'verein' => $a['verein'], 'ausweis_nr' => $a['ausweis_nr'],
                ],
                'teilnahmen' => $teilnahmen,
                'verlauf' => $this->verlauf($teilnahmeIds, (int) $id),
            ];
        }
        return ['erstellt_am' => date('Y-m-d H:i:s'), 'personen' => $personen, 'turniere' => $this->turnierHinweise(array_keys($turniere))];
    }

    /**
     * Bericht zu einer E-Mail-Adresse (meldende Person, Coach, Konto).
     *
     * @return array<string, mixed>
     */
    public function kontakt(string $email): array
    {
        $email = mb_strtolower(trim($email));
        $konten = [];
        foreach ($this->db->alle('SELECT b.*, v.name AS verein FROM benutzer b LEFT JOIN verein v ON v.id = b.verein_id WHERE LOWER(b.email) = ?', [$email]) as $b) {
            $konten[] = [
                'name' => trim(($b['vorname'] ?? '') . ' ' . ($b['nachname'] ?? '')) ?: $b['name'], 'login' => $b['login'], 'email' => $b['email'],
                'telefon' => $b['telefon'], 'dojo' => $b['verein'], 'aktiv' => (bool) $b['aktiv'], 'angelegt_am' => $b['erstellt_am'],
                'gilt_bis' => $b['ablauf_am'], 'authenticator_app' => $b['totp_geheimnis'] !== null,
                'vertraute_geraete' => $this->db->alle('SELECT geraet, erstellt_am, ablauf FROM konto_vertrauen WHERE benutzer_id = ?', [$b['id']]),
                'turnierrollen' => $this->db->alle('SELECT t.name AS turnier, r.rolle FROM turnier_rolle r JOIN turnier t ON t.id = r.turnier_id WHERE r.benutzer_id = ?', [$b['id']]),
            ];
        }
        $anmeldungen = [];
        $turniere = [];
        foreach ($this->db->alle(
            'SELECT a.*, t.name AS turnier, t.datum FROM anmeldung a JOIN turnier t ON t.id = a.turnier_id WHERE LOWER(a.email) = ? ORDER BY a.eingereicht_am',
            [$email],
        ) as $a) {
            $turniere[(int) $a['turnier_id']] = true;
            $d = json_decode((string) $a['daten'], true) ?: [];
            $anmeldungen[] = [
                'turnier' => $a['turnier'], 'datum' => $a['datum'], 'dojo' => $a['dojo'], 'ansprechpartner' => $a['ansprechpartner'], 'email' => $a['email'],
                'telefon' => $a['telefon'], 'bemerkung' => $a['bemerkung'], 'status' => $a['status'], 'eingereicht_am' => $a['eingereicht_am'],
                'betrag_cent' => (int) $a['aufforderung_cent'], 'bezahlt_cent' => (int) $a['bezahlt_cent'], 'bezahlt_am' => $a['bezahlt_am'],
                'gemeldete_starter' => count((array) ($d['teilnehmer'] ?? [])), 'datenschutz_bestaetigt' => $d['datenschutz'] ?? null,
            ];
        }
        $mails = $this->db->alle('SELECT betreff, anlass, erstellt_am, gesendet_am FROM mail_ausgang WHERE LOWER(empfaenger) = ? ORDER BY id', [$email]);
        $einladung = $this->db->eine('SELECT email, name, dojo, herkunft, eingewilligt_am, fassung, zuletzt_eingeladen_am FROM einladung WHERE email = ?', [$email]);
        return ['erstellt_am' => date('Y-m-d H:i:s'), 'email' => $email, 'konten' => $konten, 'anmeldungen' => $anmeldungen, 'mails' => $mails, 'einladung' => $einladung,
            'turniere' => $this->turnierHinweise(array_keys($turniere))];
    }

    /** @return list<array<string, mixed>> */
    private function starts(int $teilnahmeId): array
    {
        $liste = [];
        foreach ($this->db->alle(
            'SELECT s.id, s.status, k.id AS kid, k.code, k.bezeichnung FROM start s JOIN klasse k ON k.id = s.klasse_id WHERE s.teilnahme_id = ? ORDER BY k.sortierung, k.id',
            [$teilnahmeId],
        ) as $s) {
            $sid = (int) $s['id'];
            $begegnungen = [];
            foreach ($this->db->alle(
                'SELECT b.runde, b.aka_start_id, b.sieger_start_id, b.entscheidung, b.kata_aka, b.kata_shiro, b.wertung, b.status, b.beendet_am, p.bezeichnung AS phase
                   FROM begegnung b JOIN phase p ON p.id = b.phase_id WHERE b.aka_start_id = ? OR b.shiro_start_id = ? ORDER BY p.nummer, b.runde, b.position',
                [$sid, $sid],
            ) as $b) {
                $aka = (int) $b['aka_start_id'] === $sid;
                $begegnungen[] = [
                    'phase' => $b['phase'], 'runde' => $b['runde'], 'seite' => $aka ? 'AKA' : 'SHIRO', 'kata' => $aka ? $b['kata_aka'] : $b['kata_shiro'],
                    'ergebnis' => $b['sieger_start_id'] === null ? ($b['status'] === 'beendet' ? 'unentschieden/offen' : 'offen') : ((int) $b['sieger_start_id'] === $sid ? 'gewonnen' : 'verloren'),
                    'entscheidung' => $b['entscheidung'], 'wertung' => $b['wertung'], 'beendet_am' => $b['beendet_am'],
                ];
            }
            $auftritte = $this->db->alle(
                'SELECT p.bezeichnung AS phase, a.kata, a.wertung, a.gesamt, a.status, a.beendet_am FROM auftritt a JOIN phase p ON p.id = a.phase_id WHERE a.start_id = ? ORDER BY p.nummer',
                [$sid],
            );
            $liste[] = [
                'klasse' => $s['code'] . ' ' . $s['bezeichnung'], 'status' => $s['status'],
                'platz' => $this->db->wert('SELECT platz FROM platzierung WHERE start_id = ? AND klasse_id = ?', [$sid, $s['kid']]),
                'begegnungen' => $begegnungen, 'auftritte' => $auftritte,
            ];
        }
        return $liste;
    }

    /**
     * Zeilen der Person in den Online-Anmeldungen des Turniers (Herkunft der Daten).
     *
     * @return list<array<string, mixed>>
     */
    private function anmeldezeilen(int $turnierId, string $vorname, string $nachname): array
    {
        $liste = [];
        foreach ($this->db->alle('SELECT dojo, eingereicht_am, status, daten FROM anmeldung WHERE turnier_id = ?', [$turnierId]) as $a) {
            foreach ((array) ((json_decode((string) $a['daten'], true) ?: [])['teilnehmer'] ?? []) as $t) {
                if (is_array($t) && mb_strtolower(trim((string) ($t['vorname'] ?? ''))) === mb_strtolower(trim($vorname))
                    && mb_strtolower(trim((string) ($t['nachname'] ?? ''))) === mb_strtolower(trim($nachname))) {
                    $liste[] = ['quelle' => 'Online-Anmeldung durch das Dojo „' . $a['dojo'] . '“', 'eingereicht_am' => $a['eingereicht_am'], 'status' => $a['status'],
                        'angaben' => array_intersect_key($t, array_flip(['vorname', 'nachname', 'geschlecht', 'geburtsdatum', 'graduierung', 'kata', 'kumite']))];
                }
            }
        }
        return $liste;
    }

    /**
     * @param list<int> $teilnahmeIds
     * @return list<array<string, mixed>>
     */
    private function verlauf(array $teilnahmeIds, int $athletId): array
    {
        $bedingung = "(entitaet = 'athlet' AND entitaet_id = ?)";
        $werte = [$athletId];
        if ($teilnahmeIds !== []) {
            $bedingung .= " OR (entitaet = 'teilnahme' AND entitaet_id IN (" . implode(',', array_fill(0, count($teilnahmeIds), '?')) . '))';
            $werte = [...$werte, ...$teilnahmeIds];
        }
        return $this->db->alle("SELECT zeit, aktion, daten FROM audit_log WHERE $bedingung ORDER BY id", $werte);
    }

    /**
     * @param list<int> $turnierIds
     * @return list<array{name: string, datum: string, abschnitte: list<array{titel: string, absaetze: list<string>, punkte: list<string>}>}>
     */
    private function turnierHinweise(array $turnierIds): array
    {
        $hinweise = new Hinweise($this->db);
        $liste = [];
        foreach ($turnierIds as $id) {
            $t = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$id]);
            if ($t !== null) {
                $liste[] = ['name' => (string) $t['name'], 'datum' => (string) $t['datum'], 'abschnitte' => $hinweise->abschnitte($t)];
            }
        }
        return $liste;
    }
}
