<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;

/**
 * Ausruf der Starter: Der Ausrufer der Tischbesatzung bestätigt je Begegnung
 * (AKA/SHIRO) bzw. Vortrag, dass der Starter da ist (oder fehlt), und schließt
 * den Ausruf ab. Danach kann der Kampf auf der Tatami beginnen.
 */
final class AusrufService
{
    public const STATUS = ['da', 'fehlt'];

    /**
     * Wo steht AKA – vom Kampfrichtertisch aus gesehen? (Einstellung je Turnier, Standard: rechts)
     *
     * @param array<string, mixed> $turnier
     */
    public static function akaSeite(array $turnier): string
    {
        $e = json_decode((string) ($turnier['einstellungen'] ?? ''), true) ?: [];
        return ($e['aka_seite'] ?? 'rechts') === 'links' ? 'links' : 'rechts';
    }

    /**
     * Kampfuhr am Tablet (Einstellung je Turnier, Standard: aus). Kampfzeit laut Wettkampfordnung
     * B 4.1 zwei Minuten, Finale bis fünf Minuten; Ato shibaraku 30 Sekunden vor Ende.
     *
     * @param array<string, mixed> $turnier
     * @return array{aktiv: bool, minuten: float, minuten_finale: float, ton: bool}
     */
    public static function kampfuhr(array $turnier): array
    {
        $e = json_decode((string) ($turnier['einstellungen'] ?? ''), true) ?: [];
        $u = is_array($e['kampfuhr'] ?? null) ? $e['kampfuhr'] : [];
        $minuten = static fn($w, float $standard) => is_numeric($w) ? max(1.0, min(5.0, round((float) $w * 2) / 2)) : $standard;
        return [
            'aktiv' => !empty($u['aktiv']),
            'minuten' => $minuten($u['minuten'] ?? null, 2.0),
            'minuten_finale' => $minuten($u['minuten_finale'] ?? null, 2.0),
            'ton' => ($u['ton'] ?? true) !== false,
        ];
    }

    private Protokoll $protokoll;

    public function __construct(private readonly Database $db)
    {
        $this->protokoll = new Protokoll($db);
    }

    /** Setzt „da“, „fehlt“ oder (null) wieder offen für eine Seite; ein abgeschlossener Ausruf wird dabei wieder geöffnet. */
    public function markieren(int $turnierId, string $typ, int $id, string $seite, ?string $status): void
    {
        if ($status !== null && !in_array($status, self::STATUS, true)) {
            throw new FachFehler('Ungültiger Ausruf-Status.');
        }
        [$tabelle, $spalten] = $this->ziel($turnierId, $typ, $id);
        if (!isset($spalten[$seite])) {
            throw new FachFehler('Ungültige Seite.');
        }
        $this->db->update($tabelle, [$spalten[$seite] => $status, 'aufruf_am' => null], ['id' => $id]);
        $this->protokoll->revisionErhoehen($turnierId);
    }

    /**
     * Gruppen-Ausruf starten bzw. beenden: Alle Starter der Gruppe erscheinen auf der
     * Tatami-Übersicht unter „Bitte zur Tatami“, bis sie als „da“ bestätigt sind.
     */
    public function gruppeAufrufen(int $turnierId, int $klasseId, bool $an, ?int $tatamiId = null): void
    {
        $k = $this->db->eine('SELECT id, code FROM klasse WHERE id = ? AND turnier_id = ?', [$klasseId, $turnierId])
            ?? throw new FachFehler('Gruppe nicht gefunden.');
        if ($an && ($konflikte = $this->konflikte($turnierId, $klasseId)) !== []) {
            throw new FachFehler("Gruppe {$k['code']} kann noch nicht aufgerufen werden – noch im Wettkampf auf einer anderen Tatami: "
                . implode(', ', array_map(static fn($c) => "{$c['name']} ({$c['klasse']}, {$c['tatami']})", $konflikte)) . '.');
        }
        $this->phasenSetzen($klasseId, $tatamiId, ['ausruf_am' => $an ? date('Y-m-d H:i:s') : null]);
        $this->protokoll->eintragen($turnierId, 'klasse', (int) $k['id'], $an ? 'gruppe_aufgerufen' : 'gruppe_aufruf_beendet');
        $this->protokoll->revisionErhoehen($turnierId);
    }

    /**
     * Einmal ausgerufen genügt: Wer in einer Klasse in einem abgeschlossenen Ausruf „da“ war oder dort
     * schon kämpft bzw. gekämpft/vorgetragen hat (nicht per Kiken verloren), gilt in allen weiteren Kämpfen und
     * Vorträgen dieser Klasse als da – Halbfinale, Kampf um Platz 3, Finale, Finalrunde. Sind alle
     * Seiten eines Kampfes bzw. Vortrags so belegt, ist sein Ausruf abgeschlossen. „fehlt“ bleibt.
     *
     * @return int Anzahl Kämpfe/Vorträge, deren Ausruf dadurch abgeschlossen wurde
     */
    public function uebernehmen(int $klasseId): int
    {
        $da = array_flip(array_map('intval', $this->db->spalte(
            "SELECT b.aka_start_id FROM begegnung b JOIN phase p ON p.id = b.phase_id
              WHERE p.klasse_id = :k1 AND b.aka_start_id IS NOT NULL
                AND ((b.aufruf_aka = 'da' AND b.aufruf_am IS NOT NULL) OR b.status = 'laeuft' OR (b.status = 'beendet' AND (b.entscheidung <> 'kiken' OR b.sieger_start_id = b.aka_start_id)))
             UNION SELECT b.shiro_start_id FROM begegnung b JOIN phase p ON p.id = b.phase_id
              WHERE p.klasse_id = :k2 AND b.shiro_start_id IS NOT NULL
                AND ((b.aufruf_shiro = 'da' AND b.aufruf_am IS NOT NULL) OR b.status = 'laeuft' OR (b.status = 'beendet' AND (b.entscheidung <> 'kiken' OR b.sieger_start_id = b.shiro_start_id)))
             UNION SELECT a.start_id FROM auftritt a JOIN phase p ON p.id = a.phase_id
              WHERE p.klasse_id = :k3 AND ((a.aufruf_starter = 'da' AND a.aufruf_am IS NOT NULL) OR (a.status = 'beendet' AND COALESCE(a.kata, '') <> 'Kiken'))",
            ['k1' => $klasseId, 'k2' => $klasseId, 'k3' => $klasseId],
        )));
        if ($da === []) {
            return 0;
        }
        $jetzt = date('Y-m-d H:i:s');
        $anzahl = 0;
        foreach ($this->db->alle(
            "SELECT b.* FROM begegnung b JOIN phase p ON p.id = b.phase_id
              WHERE p.klasse_id = ? AND b.status IN ('bereit', 'laeuft') AND b.aufruf_am IS NULL
                AND b.aka_start_id IS NOT NULL AND b.shiro_start_id IS NOT NULL",
            [$klasseId],
        ) as $b) {
            $felder = [];
            foreach (['aka', 'shiro'] as $s) {
                if ($b["aufruf_$s"] === null && isset($da[(int) $b["{$s}_start_id"]])) {
                    $felder["aufruf_$s"] = 'da';
                }
            }
            if ($felder === []) {
                continue;
            }
            if (($felder['aufruf_aka'] ?? $b['aufruf_aka']) === 'da' && ($felder['aufruf_shiro'] ?? $b['aufruf_shiro']) === 'da') {
                $felder['aufruf_am'] = $jetzt;
                $anzahl++;
            }
            $this->db->update('begegnung', $felder, ['id' => $b['id']]);
        }
        foreach ($this->db->alle(
            "SELECT a.id, a.start_id FROM auftritt a JOIN phase p ON p.id = a.phase_id
              WHERE p.klasse_id = ? AND a.status <> 'beendet' AND p.status <> 'wartet' AND a.aufruf_am IS NULL AND a.aufruf_starter IS NULL",
            [$klasseId],
        ) as $a) {
            if (isset($da[(int) $a['start_id']])) {
                $this->db->update('auftritt', ['aufruf_starter' => 'da', 'aufruf_am' => $jetzt], ['id' => $a['id']]);
                $anzahl++;
            }
        }
        return $anzahl;
    }

    /**
     * Die Tischbesatzung startet (bzw. nimmt zurück) den Ausruf einer Gruppe: erst danach
     * zeigt die Ausruf-Ansicht deren Starter – die laufende Gruppe ist immer sichtbar.
     * Der Start gilt zugleich als Gruppen-Aufruf (Starter auf der Statusseite), damit der
     * Ausrufer ihn nicht noch einmal auslösen muss – außer Starter kämpfen gerade noch auf
     * einer anderen Tatami; dann ruft der Ausrufer die Gruppe auf, sobald sie frei sind.
     * Mit $tatamiId nur für die Phasen (Pools) dieser Tatami.
     */
    public function freigeben(int $turnierId, int $klasseId, bool $an, ?int $tatamiId = null): void
    {
        $k = $this->db->eine('SELECT id FROM klasse WHERE id = ? AND turnier_id = ?', [$klasseId, $turnierId])
            ?? throw new FachFehler('Gruppe nicht gefunden.');
        $jetzt = date('Y-m-d H:i:s');
        $schonAufgerufen = $this->db->wert(
            'SELECT MAX(ausruf_am) FROM phase WHERE klasse_id = ?' . ($tatamiId === null ? '' : ' AND tatami_id = ?'),
            $tatamiId === null ? [$klasseId] : [$klasseId, $tatamiId],
        );
        $aufruf = $an ? ($schonAufgerufen ?? ($this->konflikte($turnierId, $klasseId) === [] ? $jetzt : null)) : null;
        $this->phasenSetzen($klasseId, $tatamiId, ['ausruf_freigabe_am' => $an ? $jetzt : null, 'ausruf_am' => $aufruf]);
        $this->protokoll->eintragen($turnierId, 'klasse', (int) $k['id'], $an ? 'ausruf_gestartet' : 'ausruf_start_zurueck');
        $this->protokoll->revisionErhoehen($turnierId);
    }

    /**
     * Einen Starter für die ganze Gruppe abhaken („da“/„fehlt“, null = wieder offen):
     * gilt für alle seine offenen Kämpfe bzw. Vorträge in dieser Gruppe; ein bereits
     * abgeschlossener Ausruf dieser Einträge wird dabei wieder geöffnet.
     */
    public function starterMarkieren(int $turnierId, int $klasseId, int $startId, ?string $status): void
    {
        if ($status !== null && !in_array($status, self::STATUS, true)) {
            throw new FachFehler('Ungültiger Ausruf-Status.');
        }
        $this->gruppe($turnierId, $klasseId);
        if ($this->db->wert('SELECT id FROM start WHERE id = ? AND klasse_id = ?', [$startId, $klasseId]) === null) {
            throw new FachFehler('Starter gehört nicht zu dieser Gruppe.');
        }
        $phasen = 'SELECT id FROM phase WHERE klasse_id = ?';
        foreach (['aka', 'shiro'] as $seite) {
            $this->db->query(
                "UPDATE begegnung SET aufruf_$seite = ?, aufruf_am = NULL
                  WHERE {$seite}_start_id = ? AND status IN ('bereit', 'laeuft') AND phase_id IN ($phasen)",
                [$status, $startId, $klasseId],
            );
        }
        $this->db->query(
            "UPDATE auftritt SET aufruf_starter = ?, aufruf_am = NULL WHERE start_id = ? AND status = 'offen' AND phase_id IN ($phasen)",
            [$status, $startId, $klasseId],
        );
        $this->protokoll->revisionErhoehen($turnierId);
    }

    /**
     * Ausruf-Felder der Phasen einer Klasse setzen – nur auf dieser Tatami bzw. (ohne) auf allen.
     *
     * @param array<string, string|null> $felder
     */
    private function phasenSetzen(int $klasseId, ?int $tatamiId, array $felder): void
    {
        $this->db->update('phase', $felder, $tatamiId === null ? ['klasse_id' => $klasseId] : ['klasse_id' => $klasseId, 'tatami_id' => $tatamiId]);
    }

    /**
     * Schließt den Ausruf der ganzen Gruppe ab: alle offenen Kämpfe/Vorträge, deren
     * Starter vollständig abgehakt sind. Fehlt noch ein Haken, wird abgelehnt.
     * Mit $tatamiId nur die Phasen dieser Tatami – Pools einer Klasse können auf
     * verschiedenen Tatamis laufen, jede ruft ihren Pool selbst aus.
     *
     * @return int Anzahl abgeschlossener Kämpfe/Vorträge
     */
    public function gruppeAbschliessen(int $turnierId, int $klasseId, ?int $tatamiId = null): int
    {
        $k = $this->gruppe($turnierId, $klasseId);
        return $this->db->transaktion(function () use ($turnierId, $klasseId, $k, $tatamiId) {
            [$aufTatami, $parameter] = $tatamiId === null ? ['', [$klasseId]] : [' AND p.tatami_id = ?', [$klasseId, $tatamiId]];
            $begegnungen = $this->db->alle(
                "SELECT b.id, b.status, b.aufruf_aka, b.aufruf_shiro FROM begegnung b JOIN phase p ON p.id = b.phase_id
                  WHERE p.klasse_id = ? AND b.status IN ('bereit', 'laeuft') AND b.aufruf_am IS NULL
                    AND b.aka_start_id IS NOT NULL AND b.shiro_start_id IS NOT NULL" . $aufTatami,
                $parameter,
            );
            $auftritte = $this->db->alle(
                "SELECT a.id, a.aufruf_starter, a.phase_id, a.reihenfolge,
                        (SELECT COUNT(*) FROM auftritt b WHERE b.phase_id = a.phase_id AND b.status = 'beendet') AS beendet,
                        (SELECT MIN(c.reihenfolge) FROM auftritt c WHERE c.phase_id = a.phase_id AND c.status = 'offen') AS naechster
                   FROM auftritt a JOIN phase p ON p.id = a.phase_id
                  WHERE p.klasse_id = ? AND a.status = 'offen' AND a.aufruf_am IS NULL AND p.status <> 'wartet'" . $aufTatami,
                $parameter,
            );
            // Wer schon auf der Matte steht, braucht keinen Haken: der laufende Kampf und in einer
            // begonnenen Kata-Gruppe der aktuelle Vortrag (beide stehen nicht in der Abhakliste)
            $aufDerMatte = static fn(array $a) => (int) $a['beendet'] > 0 && (int) $a['reihenfolge'] === (int) $a['naechster'];
            $offen = count(array_filter($begegnungen, static fn($b) => $b['status'] !== 'laeuft' && ($b['aufruf_aka'] === null || $b['aufruf_shiro'] === null)))
                + count(array_filter($auftritte, static fn($a) => $a['aufruf_starter'] === null && !$aufDerMatte($a)));
            if ($offen > 0) {
                throw new FachFehler("Gruppe {$k['code']}: bitte zuerst alle Starter abhaken (noch $offen Kämpfe/Vorträge offen).");
            }
            $jetzt = date('Y-m-d H:i:s');
            foreach ($begegnungen as $b) {
                $this->db->update('begegnung', ['aufruf_am' => $jetzt], ['id' => $b['id']]);
            }
            foreach ($auftritte as $a) {
                $this->db->update('auftritt', ['aufruf_am' => $jetzt], ['id' => $a['id']]);
            }
            $anzahl = count($begegnungen) + count($auftritte);
            $this->uebernehmen($klasseId); // schon feststehende Folgerunden ohne erneuten Ausruf
            $this->protokoll->eintragen($turnierId, 'klasse', $klasseId, 'ausruf_gruppe', ['kaempfe' => $anzahl]);
            $this->protokoll->revisionErhoehen($turnierId);
            return $anzahl;
        });
    }

    /** Öffnet den abgeschlossenen Ausruf einer Gruppe wieder (Haken bleiben); mit $tatamiId nur auf dieser Tatami. */
    public function gruppeOeffnen(int $turnierId, int $klasseId, ?int $tatamiId = null): void
    {
        $this->gruppe($turnierId, $klasseId);
        [$phasen, $parameter] = $tatamiId === null
            ? ['SELECT id FROM phase WHERE klasse_id = ?', [$klasseId]]
            : ['SELECT id FROM phase WHERE klasse_id = ? AND tatami_id = ?', [$klasseId, $tatamiId]];
        $this->db->query("UPDATE begegnung SET aufruf_am = NULL WHERE status = 'bereit' AND phase_id IN ($phasen)", $parameter);
        $this->db->query("UPDATE auftritt SET aufruf_am = NULL WHERE status = 'offen' AND phase_id IN ($phasen)", $parameter);
        $this->protokoll->revisionErhoehen($turnierId);
    }

    /** @return array<string, mixed> */
    private function gruppe(int $turnierId, int $klasseId): array
    {
        return $this->db->eine('SELECT id, code FROM klasse WHERE id = ? AND turnier_id = ?', [$klasseId, $turnierId])
            ?? throw new FachFehler('Gruppe nicht gefunden.');
    }

    /**
     * Starter einer Gruppe, die auf einer anderen Tatami noch im Wettkampf einer
     * anderen Klasse sind: Die Klasse läuft (oder wird gerade aufgerufen) und der
     * Starter hat dort noch einen offenen Kampf bzw. Vortrag. Teams zählen über
     * ihre Mitglieder. Solange es Konflikte gibt, kann die Gruppe nicht aufgerufen werden.
     *
     * @return list<array{name: string, klasse: string, tatami: string}>
     */
    public function konflikte(int $turnierId, int $klasseId): array
    {
        // Personen (Teilnahmen) der Gruppe – einzeln oder als Teammitglied
        $personen = "SELECT s.teilnahme_id AS tid FROM start s WHERE s.klasse_id = :k AND s.status <> 'zurueckgezogen' AND s.teilnahme_id IS NOT NULL
                     UNION SELECT m.teilnahme_id FROM start s JOIN team_mitglied m ON m.team_id = s.team_id WHERE s.klasse_id = :k2 AND s.status <> 'zurueckgezogen'";
        // Starts derselben Personen in anderen Klassen (einzeln oder über ihr Team)
        $andere = "SELECT s2.id AS start_id, s2.klasse_id, COALESCE(s2.teilnahme_id, m2.teilnahme_id) AS tid
                     FROM start s2 LEFT JOIN team_mitglied m2 ON m2.team_id = s2.team_id
                    WHERE s2.klasse_id <> :k3 AND s2.status <> 'zurueckgezogen'";
        $zeilen = $this->db->alle(
            "SELECT DISTINCT x.tid, k2.code, ta.bezeichnung AS tatami
               FROM ($andere) x
               JOIN klasse k2 ON k2.id = x.klasse_id AND k2.turnier_id = :t
               JOIN phase p2 ON p2.klasse_id = k2.id
               LEFT JOIN tatami ta ON ta.id = p2.tatami_id
              WHERE x.tid IN ($personen)
                AND (p2.tatami_id IS NULL OR p2.tatami_id NOT IN (SELECT p.tatami_id FROM phase p WHERE p.klasse_id = :k4 AND p.tatami_id IS NOT NULL))
                AND (k2.status = 'laeuft' OR p2.ausruf_am IS NOT NULL
                     OR EXISTS (SELECT 1 FROM begegnung lb JOIN phase lp ON lp.id = lb.phase_id WHERE lp.klasse_id = k2.id AND lb.status = 'laeuft'))
                AND (EXISTS (SELECT 1 FROM begegnung b WHERE b.phase_id = p2.id AND b.status IN ('wartet', 'bereit', 'laeuft')
                                AND x.start_id IN (b.aka_start_id, b.shiro_start_id))
                     OR EXISTS (SELECT 1 FROM auftritt a WHERE a.phase_id = p2.id AND a.start_id = x.start_id AND a.status <> 'beendet'))",
            ['k' => $klasseId, 'k2' => $klasseId, 'k3' => $klasseId, 'k4' => $klasseId, 't' => $turnierId],
        );
        $namen = [];
        foreach ($zeilen as $z) {
            $p = $this->db->eine('SELECT a.vorname, a.nachname FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id WHERE t.id = ?', [$z['tid']]);
            $namen[] = ['name' => trim(($p['vorname'] ?? '') . ' ' . ($p['nachname'] ?? '')), 'klasse' => $z['code'], 'tatami' => (string) $z['tatami']];
        }
        return $namen;
    }

    /** Schließt den Ausruf ab – alle Seiten müssen „da“ oder „fehlt“ sein. */
    public function abschliessen(int $turnierId, string $typ, int $id): void
    {
        [$tabelle, $spalten, $zeile] = $this->ziel($turnierId, $typ, $id);
        $stand = [];
        foreach ($spalten as $seite => $spalte) {
            if ($zeile[$spalte] === null) {
                throw new FachFehler('Bitte zuerst bei ' . ($seite === 'starter' ? 'dem Starter' : strtoupper($seite)) . ' „da“ oder „fehlt“ wählen.');
            }
            $stand[$seite] = $zeile[$spalte];
        }
        $this->db->update($tabelle, ['aufruf_am' => date('Y-m-d H:i:s')], ['id' => $id]);
        $this->protokoll->eintragen($turnierId, $tabelle, $id, 'ausruf', $stand);
        $this->protokoll->revisionErhoehen($turnierId);
    }

    /** Öffnet einen abgeschlossenen Ausruf wieder (Eintragungen bleiben). */
    public function oeffnen(int $turnierId, string $typ, int $id): void
    {
        [$tabelle] = $this->ziel($turnierId, $typ, $id);
        $this->db->update($tabelle, ['aufruf_am' => null], ['id' => $id]);
        $this->protokoll->revisionErhoehen($turnierId);
    }

    // --- Rückmeldung „nicht erschienen“ an die Wettkampfleitung --------------------

    /** Anzahl offener Meldungen (für die Kopfzeile der Leitung). */
    public function anzahlNichtErschienen(int $turnierId): int
    {
        return (int) $this->db->wert(
            "SELECT (SELECT COALESCE(SUM(CASE WHEN b.aufruf_aka = 'fehlt' THEN 1 ELSE 0 END + CASE WHEN b.aufruf_shiro = 'fehlt' THEN 1 ELSE 0 END), 0)
                       FROM begegnung b JOIN phase p ON p.id = b.phase_id JOIN klasse k ON k.id = p.klasse_id
                      WHERE k.turnier_id = ? AND b.aufruf_am IS NOT NULL AND b.status IN ('bereit', 'laeuft'))
                  + (SELECT COUNT(*) FROM auftritt a JOIN phase p ON p.id = a.phase_id JOIN klasse k ON k.id = p.klasse_id
                      WHERE k.turnier_id = ? AND a.aufruf_am IS NOT NULL AND a.aufruf_starter = 'fehlt' AND a.status = 'offen')",
            [$turnierId, $turnierId],
        );
    }

    /**
     * Starter, die laut abgeschlossenem Ausruf fehlen und noch nicht kampflos gewertet sind.
     *
     * @return list<array<string, mixed>>
     */
    public function nichtErschienen(int $turnierId): array
    {
        $liste = [];
        foreach ($this->db->alle(
            "SELECT b.id, b.aka_start_id, b.shiro_start_id, b.aufruf_aka, b.aufruf_shiro, b.aufruf_am, k.id AS klasse_id, k.code, ta.bezeichnung AS tatami
               FROM begegnung b JOIN phase p ON p.id = b.phase_id JOIN klasse k ON k.id = p.klasse_id LEFT JOIN tatami ta ON ta.id = p.tatami_id
              WHERE k.turnier_id = ? AND b.aufruf_am IS NOT NULL AND b.status IN ('bereit', 'laeuft')
                AND (b.aufruf_aka = 'fehlt' OR b.aufruf_shiro = 'fehlt')
              ORDER BY b.aufruf_am",
            [$turnierId],
        ) as $b) {
            $nummern = KlassenAnsicht::kampfNummern($this->db, (int) $b['klasse_id']);
            foreach (['aka', 'shiro'] as $seite) {
                if ($b["aufruf_$seite"] === 'fehlt') {
                    $liste[] = $this->meldung('begegnung', $b, $seite, (int) $b["{$seite}_start_id"], 'Kampf ' . ($nummern[(int) $b['id']] ?? '?'));
                }
            }
        }
        foreach ($this->db->alle(
            "SELECT a.id, a.start_id, a.reihenfolge, a.aufruf_am, k.id AS klasse_id, k.code, ta.bezeichnung AS tatami
               FROM auftritt a JOIN phase p ON p.id = a.phase_id JOIN klasse k ON k.id = p.klasse_id LEFT JOIN tatami ta ON ta.id = p.tatami_id
              WHERE k.turnier_id = ? AND a.aufruf_am IS NOT NULL AND a.aufruf_starter = 'fehlt' AND a.status = 'offen'
              ORDER BY a.aufruf_am",
            [$turnierId],
        ) as $a) {
            $liste[] = $this->meldung('auftritt', $a, 'starter', (int) $a['start_id'], 'Vortrag ' . $a['reihenfolge']);
        }
        return $liste;
    }

    /**
     * Entscheidung der Leitung zu einer Meldung „nicht erschienen“:
     *   da            Starter ist doch da (Ausruf auf „da“)
     *   kiken         nur dieser Kampf/Vortrag kampflos (Kiken)
     *   zurueckziehen aus allen Wettkämpfen (Teilnehmer abgemeldet bzw. Team-Start zurückgezogen) – offene Kämpfe Kiken
     *   neu_auslosen  wie zurueckziehen, die Klasse wird vorher ohne den Starter neu ausgelost (nur ohne Ergebnisse)
     *
     * @return string Rückmeldung für die Leitung
     */
    public function entscheiden(int $turnierId, string $typ, int $id, string $seite, string $aktion): string
    {
        return $this->db->transaktion(function () use ($turnierId, $typ, $id, $seite, $aktion) {
            [$tabelle, $spalten, $zeile] = $this->ziel($turnierId, $typ, $id);
            if (!isset($spalten[$seite]) || $zeile[$spalten[$seite]] !== 'fehlt') {
                throw new FachFehler('Diese Meldung ist nicht mehr aktuell – bitte neu laden.');
            }
            $startId = (int) ($tabelle === 'auftritt' ? $zeile['start_id'] : $zeile["{$seite}_start_id"]);
            $start = $this->db->eine('SELECT * FROM start WHERE id = ?', [$startId]) ?? throw new FachFehler('Starter nicht gefunden.');
            $name = (new KlassenAnsicht($this->db))->person($startId)['name'];
            $ergebnis = new ErgebnisService($this->db);

            switch ($aktion) {
                case 'da':
                    $this->db->update($tabelle, [$spalten[$seite] => 'da'], ['id' => $id]);
                    $this->protokoll->revisionErhoehen($turnierId);
                    return "$name ist da – Meldung erledigt.";
                case 'kiken':
                    if ($tabelle === 'auftritt') {
                        $ergebnis->auftrittEintragen($turnierId, $id, ['entscheidung' => 'kiken'], true);
                    } else {
                        $ergebnis->begegnungEintragen($turnierId, $id, ['entscheidung' => 'kiken', 'gegen' => $seite, 'grund' => 'nicht erschienen (Ausruf)'], true);
                    }
                    return "$name: Kiken in diesem " . ($tabelle === 'auftritt' ? 'Vortrag' : 'Kampf') . '.';
                case 'zurueckziehen':
                case 'neu_auslosen':
                    return $this->zurueckziehen($turnierId, $start, $name, $aktion === 'neu_auslosen', $ergebnis);
                default:
                    throw new FachFehler('Unbekannte Aktion.');
            }
        });
    }

    /** @param array<string, mixed> $start */
    private function zurueckziehen(int $turnierId, array $start, string $name, bool $neuAuslosen, ErgebnisService $ergebnis): string
    {
        $klasseId = (int) $start['klasse_id'];
        $auslosung = new AuslosungService($this->db);
        if ($neuAuslosen && $auslosung->hatErgebnisse($klasseId)) {
            throw new FachFehler('In dieser Klasse gibt es schon Ergebnisse – neu auslosen ist nicht mehr möglich. Bitte „aus allen Wettkämpfen“ wählen.');
        }
        if ($start['teilnahme_id'] !== null) {
            return $this->teilnehmerNichtErschienen($turnierId, (int) $start['teilnahme_id'], 'Ausruf', $neuAuslosen ? [$klasseId] : []);
        } else {
            $this->db->update('start', ['status' => 'zurueckgezogen'], ['id' => $start['id']]);
            $this->protokoll->eintragen($turnierId, 'start', (int) $start['id'], 'nicht_erschienen');
        }
        $text = "$name: als nicht erschienen gewertet";
        if ($neuAuslosen) {
            $auslosung->auslosen($turnierId, $klasseId);
            $text .= ', Klasse ' . $this->db->wert('SELECT code FROM klasse WHERE id = ?', [$klasseId]) . ' ohne den Starter neu ausgelost';
        }
        // Einzelstarter sind oben schon zurückgegeben; hier geht es nur noch um Team-Starts
        $kampflos = $this->teamAusfall($turnierId, (int) $start['id'], $ergebnis);
        $this->protokoll->revisionErhoehen($turnierId);
        return $text . ($kampflos > 0 ? ", $kampflos offene Kämpfe/Vorträge kampflos (Kiken)." : '.');
    }

    /**
     * Kennzeichnet einen Teilnehmer als „nicht erschienen“ (Leitung oder Ausruf):
     * Status setzen, gewünschte Klassen ohne Ergebnis ohne ihn neu auslosen,
     * alle übrigen offenen und künftigen Kämpfe/Vorträge kampflos (Kiken).
     *
     * @param list<int> $neuAuslosen Klassen, die (falls noch ohne Ergebnis) neu ausgelost werden
     * @return string Rückmeldung für die Leitung
     */
    public function teilnehmerNichtErschienen(int $turnierId, int $teilnahmeId, string $quelle, array $neuAuslosen = []): string
    {
        return $this->db->transaktion(function () use ($turnierId, $teilnahmeId, $quelle, $neuAuslosen) {
            $t = $this->db->eine(
                'SELECT t.*, a.vorname, a.nachname FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id WHERE t.id = ? AND t.turnier_id = ?',
                [$teilnahmeId, $turnierId],
            ) ?? throw new FachFehler('Teilnehmer nicht gefunden.');
            $vermerk = trim(($t['vermerk'] ?? '') . " · nicht erschienen ($quelle " . date('H:i') . ')', ' ·');
            $this->db->update('teilnahme', ['status' => 'nicht_erschienen', 'vermerk' => mb_substr($vermerk, 0, 500)], ['id' => $teilnahmeId]);
            $this->protokoll->eintragen($turnierId, 'teilnahme', $teilnahmeId, 'nicht_erschienen', ['quelle' => $quelle]);

            $auslosung = new AuslosungService($this->db);
            $neu = [];
            foreach (array_unique($neuAuslosen) as $klasseId) {
                $klasse = $this->db->eine('SELECT code, status FROM klasse WHERE id = ? AND turnier_id = ?', [$klasseId, $turnierId]);
                if ($klasse === null || $klasse['status'] === 'offen' || $auslosung->hatErgebnisse((int) $klasseId)) {
                    continue;
                }
                $auslosung->auslosen($turnierId, (int) $klasseId);
                $neu[] = $klasse['code'];
            }
            $kampflos = (new ErgebnisService($this->db))->ausfaelleVerarbeiten($turnierId, $teilnahmeId);
            $this->protokoll->revisionErhoehen($turnierId);
            return "{$t['vorname']} {$t['nachname']}: als nicht erschienen gekennzeichnet"
                . ($neu ? ', ' . implode(', ', $neu) . ' ohne ihn/sie neu ausgelost' : '')
                . ($kampflos > 0 ? ", $kampflos offene Kämpfe/Vorträge kampflos (Kiken)." : '.');
        });
    }

    /** Zurückgezogener Team-Start: offene Begegnungen und Vorträge kampflos. */
    private function teamAusfall(int $turnierId, int $startId, ErgebnisService $ergebnis): int
    {
        $anzahl = 0;
        $fortschreibung = new Fortschreibung($this->db, new PhasenErzeugung($this->db));
        foreach ($this->db->spalte("SELECT id FROM begegnung WHERE ? IN (aka_start_id, shiro_start_id) AND status IN ('bereit', 'laeuft')", [$startId]) as $b) {
            $this->db->update('begegnung', ['status' => 'bereit'], ['id' => $b]);
            $anzahl += $fortschreibung->ausfallPruefen((int) $b) ? 1 : 0;
        }
        foreach ($this->db->spalte(
            "SELECT a.id FROM auftritt a JOIN phase p ON p.id = a.phase_id WHERE a.start_id = ? AND a.status = 'offen' AND p.status <> 'wartet'",
            [$startId],
        ) as $a) {
            $ergebnis->auftrittEintragen($turnierId, (int) $a, ['entscheidung' => 'kiken'], true);
            $anzahl++;
        }
        return $anzahl;
    }

    /**
     * @param array<string, mixed> $zeile
     * @return array<string, mixed>
     */
    private function meldung(string $typ, array $zeile, string $seite, int $startId, string $wo): array
    {
        $person = (new KlassenAnsicht($this->db))->person($startId);
        $start = $this->db->eine('SELECT teilnahme_id, team_id FROM start WHERE id = ?', [$startId]);
        $weitere = $start['teilnahme_id'] === null ? [] : $this->db->spalte(
            "SELECT k.code FROM start s JOIN klasse k ON k.id = s.klasse_id
              WHERE s.teilnahme_id = ? AND s.status <> 'zurueckgezogen' AND k.id <> ? AND k.status NOT IN ('offen', 'zusammengelegt', 'beendet', 'bestaetigt')
              ORDER BY k.sortierung",
            [$start['teilnahme_id'], $zeile['klasse_id']],
        );
        return [
            'typ' => $typ,
            'id' => (int) $zeile['id'],
            'seite' => $seite,
            'name' => $person['name'],
            'verein' => $person['verein'],
            'team' => $start['team_id'] !== null,
            'klasse_id' => (int) $zeile['klasse_id'],
            'klasse' => $zeile['code'],
            'wo' => $wo,
            'tatami' => $zeile['tatami'],
            'zeit' => substr((string) $zeile['aufruf_am'], 11, 5),
            'weitere' => $weitere,
            'neu_auslosbar' => !(new AuslosungService($this->db))->hatErgebnisse((int) $zeile['klasse_id']),
        ];
    }

    /**
     * @return array{0: string, 1: array<string, string>, 2: array<string, mixed>} Tabelle, Seite → Spalte, Datensatz
     */
    private function ziel(int $turnierId, string $typ, int $id): array
    {
        $tabelle = $typ === 'auftritt' ? 'auftritt' : 'begegnung';
        $zeile = $this->db->eine(
            "SELECT x.* FROM $tabelle x JOIN phase p ON p.id = x.phase_id JOIN klasse k ON k.id = p.klasse_id WHERE x.id = ? AND k.turnier_id = ?",
            [$id, $turnierId],
        ) ?? throw new FachFehler('Eintrag nicht gefunden.');
        if ($zeile['status'] === 'beendet') {
            throw new FachFehler('Dieser Kampf ist bereits beendet.');
        }
        $spalten = $tabelle === 'auftritt'
            ? ['starter' => 'aufruf_starter']
            : ['aka' => 'aufruf_aka', 'shiro' => 'aufruf_shiro'];
        return [$tabelle, $spalten, $zeile];
    }
}
