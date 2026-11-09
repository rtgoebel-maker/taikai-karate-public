<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Domain\Klasse\Vorlagen;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Kern\Uuid;

/**
 * Klassen, manuelle Starts, Zusammenlegungen und Hinweise.
 */
final class KlassenService
{
    public const DISZIPLINEN = ['kata', 'kumite', 'kata_team', 'kumite_team'];
    public const MODI = ['auto', 'pools', 'punkte', 'jgj'];
    /** sanbon = Kumite Shobu Sanbon (zwei volle Punkte), z. B. als Wertung im Finale */
    public const WERTUNGEN = ['flaggen', 'punkte', 'kumite', 'sanbon'];
    public const KUMITE_FORMEN = ['kihon_ippon', 'jiyu_ippon', 'jiyu'];
    public const AB_RUNDE = ['letzte8', 'letzte4', 'finale'];

    private readonly Protokoll $protokoll;

    public function __construct(private readonly Database $db)
    {
        $this->protokoll = new Protokoll($db);
    }

    /** @return int Anzahl angelegter Klassen (vorhandene Codes werden übersprungen) */
    public function vorlageLaden(int $turnierId, string $vorlage): int
    {
        return $this->db->transaktion(function () use ($turnierId, $vorlage) {
            $vorhanden = array_flip($this->db->spalte('SELECT code FROM klasse WHERE turnier_id = ?', [$turnierId]));
            $basis = (int) $this->db->wert('SELECT MAX(sortierung) FROM klasse WHERE turnier_id = ?', [$turnierId]);
            $anzahl = 0;
            foreach (Vorlagen::laden($vorlage) as $daten) {
                if (isset($vorhanden[$daten['code']])) {
                    continue;
                }
                $daten['sortierung'] = $basis + $daten['sortierung'];
                $this->klasseSpeichern($turnierId, $daten);
                $anzahl++;
            }
            $this->protokoll->eintragen($turnierId, 'klasse', null, 'vorlage', ['vorlage' => $vorlage, 'anzahl' => $anzahl]);
            return $anzahl;
        });
    }

    /**
     * @param array<string, mixed> $daten Felder der Tabelle klasse, optional 'regeln' (Liste)
     */
    /** Mit weniger Startern findet keine Klasse statt (1 Starter = kein Wettkampf). */
    public const MIN_STARTER = 2;

    /**
     * Findet die Klasse mit dieser Starterzahl statt (Mindestanzahl erreicht)?
     *
     * @param array<string, mixed> $klasse
     */
    public static function findetStatt(array $klasse, int $anzahl): bool
    {
        return $anzahl >= self::mindestens($klasse);
    }

    /** @param array<string, mixed> $klasse */
    public static function mindestens(array $klasse): int
    {
        return max(self::MIN_STARTER, (int) ($klasse['min_starter'] ?? self::MIN_STARTER));
    }

    public function klasseSpeichern(int $turnierId, array $daten, ?int $klasseId = null): int
    {
        $felder = $this->felderPruefen($daten);
        return $this->db->transaktion(function () use ($turnierId, $daten, $felder, $klasseId) {
            $doppelt = $this->db->wert(
                'SELECT id FROM klasse WHERE turnier_id = ? AND code = ? AND id <> ?',
                [$turnierId, $felder['code'], $klasseId ?? 0],
            );
            if ($doppelt !== null) {
                throw new FachFehler("Der Klassen-Code „{$felder['code']}“ ist bereits vergeben.");
            }
            if ($klasseId === null) {
                $klasseId = $this->db->insert('klasse', $felder + ['turnier_id' => $turnierId, 'uuid' => Uuid::v4()]);
            } else {
                $alt = $this->db->eine('SELECT * FROM klasse WHERE id = ? AND turnier_id = ?', [$klasseId, $turnierId])
                    ?? throw new FachFehler('Klasse nicht gefunden.');
                if ($alt['status'] !== 'offen' && $alt['status'] !== 'zusammengelegt') {
                    foreach (['disziplin', 'modus', 'anzahl_pools', 'aufsteiger_pro_pool', 'finale_art', 'finalisten', 'wertung', 'wertung_finale'] as $f) {
                        if ((string) $alt[$f] !== (string) $felder[$f]) {
                            throw new FachFehler('Die Klasse ist bereits ausgelost. Modus und Wertung können erst nach Zurücknehmen der Auslosung geändert werden.');
                        }
                    }
                }
                $this->db->update('klasse', $felder, ['id' => $klasseId]);
            }
            if (array_key_exists('regeln', $daten)) {
                $this->db->delete('klasse_regel', ['klasse_id' => $klasseId]);
                foreach ((array) $daten['regeln'] as $regel) {
                    if (!in_array($regel['ab_runde'] ?? '', self::AB_RUNDE, true)) {
                        continue;
                    }
                    $this->db->insert('klasse_regel', [
                        'klasse_id' => $klasseId,
                        'ab_runde' => $regel['ab_runde'],
                        'kumite_form' => in_array($regel['kumite_form'] ?? '', self::KUMITE_FORMEN, true) ? $regel['kumite_form'] : null,
                        'erlaubte_kata' => isset($regel['erlaubte_kata']) ? trim((string) $regel['erlaubte_kata']) : null,
                        'wertung' => in_array($regel['wertung'] ?? '', self::WERTUNGEN, true) ? $regel['wertung'] : null,
                    ]);
                }
            }
            $this->protokoll->eintragen($turnierId, 'klasse', $klasseId, 'gespeichert');
            return $klasseId;
        });
    }

    public function klasseLoeschen(int $turnierId, int $klasseId): void
    {
        $this->db->transaktion(function () use ($turnierId, $klasseId) {
            $klasse = $this->db->eine('SELECT * FROM klasse WHERE id = ? AND turnier_id = ?', [$klasseId, $turnierId])
                ?? throw new FachFehler('Klasse nicht gefunden.');
            if (!in_array($klasse['status'], ['offen', 'zusammengelegt'], true)) {
                throw new FachFehler('Eine ausgeloste Klasse kann nicht gelöscht werden.');
            }
            if ((int) $this->db->wert('SELECT COUNT(*) FROM start WHERE klasse_id = ?', [$klasseId]) > 0) {
                throw new FachFehler('Die Klasse hat noch Starter. Bitte zuerst verschieben oder zusammenlegen.');
            }
            if ((int) $this->db->wert("SELECT COUNT(*) FROM klasse WHERE zusammengelegt_in = ?", [$klasseId]) > 0) {
                throw new FachFehler('In diese Klasse sind andere Klassen zusammengelegt. Bitte zuerst aufheben.');
            }
            $this->db->delete('klasse', ['id' => $klasseId]);
            $this->protokoll->eintragen($turnierId, 'klasse', $klasseId, 'geloescht', ['code' => $klasse['code']]);
        });
    }

    /** Setzt einen Starter/ein Team manuell in eine Klasse (verschiebt ggf. aus derselben Disziplin). */
    public function startSetzen(int $turnierId, int $klasseId, ?int $teilnahmeId, ?int $teamId): void
    {
        $this->db->transaktion(function () use ($turnierId, $klasseId, $teilnahmeId, $teamId) {
            $klasse = $this->offeneKlasse($turnierId, $klasseId);
            $spalte = $teilnahmeId !== null ? 'teilnahme_id' : 'team_id';
            $wer = $teilnahmeId ?? $teamId ?? throw new FachFehler('Bitte einen Starter wählen.');
            $tabelle = $teilnahmeId !== null ? 'teilnahme' : 'team';
            if ($this->db->wert("SELECT id FROM $tabelle WHERE id = ? AND turnier_id = ?", [$wer, $turnierId]) === null) {
                throw new FachFehler('Starter gehört nicht zu diesem Turnier.');
            }
            // bisherige Starts derselben Disziplin entfernen (nur aus offenen Klassen)
            foreach ($this->db->alle(
                "SELECT s.*, k.status AS klasse_status FROM start s JOIN klasse k ON k.id = s.klasse_id
                  WHERE s.$spalte = ? AND k.disziplin = ?",
                [$wer, $klasse['disziplin']],
            ) as $s) {
                if ((int) $s['klasse_id'] === $klasseId) {
                    $this->db->update('start', ['manuell' => 1, 'status' => 'gemeldet'], ['id' => $s['id']]);
                    return;
                }
                if ($s['klasse_status'] !== 'offen') {
                    throw new FachFehler('Der Starter ist in dieser Disziplin bereits in einer ausgelosten Klasse.');
                }
                $this->db->delete('start', ['id' => $s['id']]);
            }
            $this->db->insert('start', [$spalte => $wer, 'klasse_id' => $klasseId, 'manuell' => 1]);
            $this->protokoll->eintragen($turnierId, 'start', null, 'manuell_gesetzt', [$spalte => $wer, 'klasse_id' => $klasseId]);
        });
    }

    public function startEntfernen(int $turnierId, int $startId): void
    {
        $start = $this->db->eine(
            'SELECT s.*, k.turnier_id, k.status AS klasse_status FROM start s JOIN klasse k ON k.id = s.klasse_id WHERE s.id = ?',
            [$startId],
        );
        if ($start === null || (int) $start['turnier_id'] !== $turnierId) {
            throw new FachFehler('Start nicht gefunden.');
        }
        if ($start['klasse_status'] !== 'offen') {
            throw new FachFehler('Die Klasse ist bereits ausgelost. Der Starter kann nur noch abgemeldet werden.');
        }
        $this->db->delete('start', ['id' => $startId]);
        $this->protokoll->eintragen($turnierId, 'start', $startId, 'entfernt');
    }

    /**
     * Legt Quellklassen in eine Zielklasse zusammen. Die Starts wandern in die
     * Zielklasse und merken sich ihre Herkunft (für das Aufheben).
     *
     * @param list<int> $quellIds
     */
    public function zusammenlegen(int $turnierId, int $zielId, array $quellIds, string $grund, string $zustimmung): void
    {
        $this->db->transaktion(function () use ($turnierId, $zielId, $quellIds, $grund, $zustimmung) {
            $ziel = $this->offeneKlasse($turnierId, $zielId);
            $quellIds = array_values(array_unique(array_filter($quellIds, static fn($id) => (int) $id !== $zielId)));
            if ($quellIds === []) {
                throw new FachFehler('Bitte mindestens eine weitere Klasse zum Zusammenlegen wählen.');
            }
            foreach ($quellIds as $quellId) {
                $quelle = $this->offeneKlasse($turnierId, (int) $quellId);
                if ($quelle['disziplin'] !== $ziel['disziplin']) {
                    throw new FachFehler("{$quelle['code']} und {$ziel['code']} haben unterschiedliche Disziplinen.");
                }
                $this->db->query(
                    'UPDATE start SET klasse_id = ?, herkunft_klasse_id = COALESCE(herkunft_klasse_id, ?) WHERE klasse_id = ?',
                    [$zielId, $quelle['id'], $quelle['id']],
                );
                $this->db->update('klasse', ['status' => 'zusammengelegt', 'zusammengelegt_in' => $zielId], ['id' => $quelle['id']]);
                $this->db->insert('zusammenlegung', [
                    'ziel_klasse_id' => $zielId,
                    'quell_klasse_id' => $quelle['id'],
                    'grund' => $grund ?: null,
                    'zustimmung' => $zustimmung ?: null,
                    'erstellt_am' => date('Y-m-d H:i:s'),
                    'erstellt_von' => \Wettkampf\Kern\Http\Session::akteur(),
                ]);
            }
            $this->protokoll->eintragen($turnierId, 'klasse', $zielId, 'zusammengelegt', ['quellen' => $quellIds, 'grund' => $grund]);
        });
    }

    public function zusammenlegungAufheben(int $turnierId, int $quellId): void
    {
        $this->db->transaktion(function () use ($turnierId, $quellId) {
            $quelle = $this->db->eine('SELECT * FROM klasse WHERE id = ? AND turnier_id = ?', [$quellId, $turnierId]);
            if ($quelle === null || $quelle['status'] !== 'zusammengelegt') {
                throw new FachFehler('Diese Klasse ist nicht zusammengelegt.');
            }
            $this->offeneKlasse($turnierId, (int) $quelle['zusammengelegt_in']);
            $this->db->query(
                'UPDATE start SET klasse_id = ?, herkunft_klasse_id = NULL WHERE herkunft_klasse_id = ?',
                [$quellId, $quellId],
            );
            $this->db->update('klasse', ['status' => 'offen', 'zusammengelegt_in' => null], ['id' => $quellId]);
            $this->db->query(
                'UPDATE zusammenlegung SET aufgehoben_am = ? WHERE quell_klasse_id = ? AND aufgehoben_am IS NULL',
                [date('Y-m-d H:i:s'), $quellId],
            );
            $this->protokoll->eintragen($turnierId, 'klasse', $quellId, 'zusammenlegung_aufgehoben');
        });
    }

    /**
     * Klassen mit Starteranzahl für Übersichten.
     *
     * @return list<array<string, mixed>>
     */
    public function klassenMitAnzahl(int $turnierId): array
    {
        return $this->db->alle(
            "SELECT k.*,
                    (SELECT COUNT(*) FROM start s WHERE s.klasse_id = k.id AND s.status <> 'zurueckgezogen') AS anzahl,
                    z.code AS ziel_code
               FROM klasse k LEFT JOIN klasse z ON z.id = k.zusammengelegt_in
              WHERE k.turnier_id = ?
              ORDER BY k.sortierung, k.code",
            [$turnierId],
        );
    }

    /**
     * Mögliche Zusammenlegungen für kleine Klassen: gleiche Disziplin, gleiches
     * Geschlecht bzw. mixed, gleiche Kumite-Form, benachbarte Alters- oder Gradbereiche.
     *
     * @return array<int, list<array{id: int, code: string, anzahl: int, grund: string}>>
     */
    public function vorschlaege(int $turnierId): array
    {
        $klassen = array_filter($this->klassenMitAnzahl($turnierId), static fn($k) => $k['status'] === 'offen');
        $vorschlaege = [];
        foreach ($klassen as $klein) {
            $n = (int) $klein['anzahl'];
            if ($n === 0 || ($n > 3 && self::findetStatt($klein, $n))) {
                continue;
            }
            foreach ($klassen as $andere) {
                if ($andere['id'] === $klein['id'] || (int) $andere['anzahl'] === 0
                    || $andere['disziplin'] !== $klein['disziplin']
                    || ($andere['kumite_form'] ?? '') !== ($klein['kumite_form'] ?? '')) {
                    continue;
                }
                $grund = null;
                $gleichesGeschlecht = $andere['geschlecht'] === $klein['geschlecht'];
                if ($gleichesGeschlecht && $this->bereichGleich($klein, $andere, 'grad') && $this->benachbart($klein, $andere, 'alter')) {
                    $grund = 'benachbarte Altersklasse';
                } elseif ($gleichesGeschlecht && $this->bereichGleich($klein, $andere, 'alter') && $this->benachbart($klein, $andere, 'grad')) {
                    $grund = 'benachbarter Graduierungsbereich';
                } elseif (!$gleichesGeschlecht && $this->bereichGleich($klein, $andere, 'alter') && $this->bereichGleich($klein, $andere, 'grad')) {
                    $grund = 'gleiche Alters-/Gradgruppe, anderes Geschlecht (mixed)';
                }
                if ($grund !== null) {
                    $vorschlaege[(int) $klein['id']][] = [
                        'id' => (int) $andere['id'],
                        'code' => $andere['code'],
                        'anzahl' => (int) $andere['anzahl'],
                        'grund' => $grund,
                    ];
                }
            }
        }
        return $vorschlaege;
    }

    /**
     * Hinweise für die Übersicht (kleine Klassen, Meldungen ohne Klasse,
     * Doppelmeldungen, Team-Regeln).
     *
     * @return list<array{typ: string, text: string, klasse_id?: int, teilnahme_id?: int}>
     */
    public function hinweise(int $turnierId): array
    {
        $hinweise = [];
        foreach ($this->klassenMitAnzahl($turnierId) as $k) {
            if ($k['status'] === 'zusammengelegt') {
                continue;
            }
            $n = (int) $k['anzahl'];
            if ($n > 0 && !self::findetStatt($k, $n)) {
                $hinweise[] = ['typ' => 'fehler', 'text' => "{$k['code']}: nur $n von mindestens " . self::mindestens($k)
                    . ' Startern – die Klasse findet nicht statt. Zusammenlegen oder Starter umsetzen.', 'klasse_id' => (int) $k['id']];
            } elseif ($n >= 2 && $n <= 3) {
                $hinweise[] = ['typ' => 'hinweis', 'text' => "{$k['code']}: nur $n Starter – " . ($n === 2 ? 'ein Kampf um Platz 1/2.' : 'jeder gegen jeden, kein Platz 4.'), 'klasse_id' => (int) $k['id']];
            }
        }

        // Meldungen ohne Start in der gemeldeten Disziplin
        $ohne = $this->db->alle(
            "SELECT t.id, t.disziplinen, a.vorname, a.nachname,
                    (SELECT GROUP_CONCAT(k.disziplin) FROM start s JOIN klasse k ON k.id = s.klasse_id
                      WHERE s.teilnahme_id = t.id AND s.status <> 'zurueckgezogen') AS belegt
               FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id
              WHERE t.turnier_id = ? AND t.status NOT IN ('abgemeldet', 'nicht_erschienen') AND t.disziplinen IS NOT NULL AND t.disziplinen <> ''",
            [$turnierId],
        );
        foreach ($ohne as $t) {
            $belegt = explode(',', (string) $t['belegt']);
            foreach (array_filter(explode(',', (string) $t['disziplinen'])) as $eintrag) {
                $disziplin = explode(':', $eintrag)[0];
                if (!in_array($disziplin, $belegt, true)) {
                    $hinweise[] = [
                        'typ' => 'fehler',
                        'text' => "{$t['vorname']} {$t['nachname']}: " . ucfirst($disziplin) . ' gemeldet, aber keiner Klasse zugeordnet.',
                        'teilnahme_id' => (int) $t['id'],
                    ];
                }
            }
        }

        // Doppel- bzw. Exklusiv-Meldungen (z. B. D2A und D2B)
        foreach ($this->db->alle(
            "SELECT a.vorname, a.nachname, COALESCE(k.exklusiv_gruppe, k.disziplin) AS gruppe, GROUP_CONCAT(k.code) AS codes, COUNT(*) AS anzahl
               FROM start s JOIN klasse k ON k.id = s.klasse_id JOIN teilnahme t ON t.id = s.teilnahme_id JOIN athlet a ON a.id = t.athlet_id
              WHERE k.turnier_id = ? AND s.status <> 'zurueckgezogen'
              GROUP BY s.teilnahme_id, a.vorname, a.nachname, COALESCE(k.exklusiv_gruppe, k.disziplin)
             HAVING COUNT(*) > 1",
            [$turnierId],
        ) as $d) {
            $hinweise[] = ['typ' => 'fehler', 'text' => "{$d['vorname']} {$d['nachname']}: mehrfach gemeldet ({$d['codes']}) – nur eine Meldung erlaubt."];
        }

        // Teams
        foreach ($this->db->alle(
            "SELECT tm.*,
                    (SELECT COUNT(*) FROM team_mitglied m WHERE m.team_id = tm.id AND m.rolle = 'stamm') AS stamm,
                    (SELECT COUNT(*) FROM team_mitglied m WHERE m.team_id = tm.id AND m.verein_id IS NOT NULL
                        AND (tm.verein_id IS NULL OR m.verein_id <> tm.verein_id)) AS fremd,
                    (SELECT COUNT(*) FROM start s WHERE s.team_id = tm.id AND s.status <> 'zurueckgezogen') AS starts
               FROM team tm WHERE tm.turnier_id = ?",
            [$turnierId],
        ) as $team) {
            if ((int) $team['stamm'] !== 3) {
                $hinweise[] = ['typ' => 'hinweis', 'text' => "Team {$team['name']}: {$team['stamm']} Stammmitglieder (üblich: 3)."];
            }
            if ((int) $team['fremd'] > 1) {
                $hinweise[] = ['typ' => 'fehler', 'text' => "Team {$team['name']}: mehr als ein vereinsfremdes Mitglied (2:1-Regel)."];
            }
            if ((int) $team['starts'] === 0 && (int) $team['stamm'] > 0) {
                $hinweise[] = ['typ' => 'fehler', 'text' => "Team {$team['name']}: keiner Klasse zugeordnet."];
            }
        }
        return $hinweise;
    }

    /** @return array<string, mixed> */
    private function offeneKlasse(int $turnierId, int $klasseId): array
    {
        $klasse = $this->db->eine('SELECT * FROM klasse WHERE id = ? AND turnier_id = ?', [$klasseId, $turnierId]);
        if ($klasse === null) {
            throw new FachFehler('Klasse nicht gefunden.');
        }
        if ($klasse['status'] !== 'offen') {
            throw new FachFehler("Klasse {$klasse['code']} ist bereits ausgelost oder zusammengelegt.");
        }
        return $klasse;
    }

    /** @param array<string, mixed> $a @param array<string, mixed> $b */
    private function bereichGleich(array $a, array $b, string $feld): bool
    {
        return $a["{$feld}_min"] === $b["{$feld}_min"] && $a["{$feld}_max"] === $b["{$feld}_max"];
    }

    /** Bereiche überlappen oder grenzen direkt aneinander. @param array<string, mixed> $a @param array<string, mixed> $b */
    private function benachbart(array $a, array $b, string $feld): bool
    {
        $aMin = $a["{$feld}_min"] ?? PHP_INT_MIN;
        $aMax = $a["{$feld}_max"] ?? PHP_INT_MAX;
        $bMin = $b["{$feld}_min"] ?? PHP_INT_MIN;
        $bMax = $b["{$feld}_max"] ?? PHP_INT_MAX;
        return $aMin <= $bMax + 1 && $bMin <= $aMax + 1;
    }

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    private function felderPruefen(array $d): array
    {
        $int = static fn($w) => $w === null || $w === '' ? null : (int) $w;
        $code = trim((string) ($d['code'] ?? ''));
        $disziplin = (string) ($d['disziplin'] ?? '');
        $geschlecht = (string) ($d['geschlecht'] ?? '');
        $modus = (string) ($d['modus'] ?? 'auto');
        $wertung = (string) ($d['wertung'] ?? 'flaggen');
        $wertungFinale = ($d['wertung_finale'] ?? '') ?: null;

        if ($code === '') {
            throw new FachFehler('Bitte einen Klassen-Code angeben (z. B. „C1“).');
        }
        if (!in_array($disziplin, self::DISZIPLINEN, true)) {
            throw new FachFehler('Ungültige Disziplin.');
        }
        if (!in_array($geschlecht, ['w', 'm', 'mixed'], true)) {
            throw new FachFehler('Ungültiges Geschlecht.');
        }
        if (!in_array($modus, self::MODI, true)) {
            throw new FachFehler('Ungültiger Modus.');
        }
        if (!in_array($wertung, self::WERTUNGEN, true) || ($wertungFinale !== null && !in_array($wertungFinale, self::WERTUNGEN, true))) {
            throw new FachFehler('Ungültiges Wertungssystem.');
        }
        if (in_array('sanbon', [$wertung, $wertungFinale], true) && $disziplin !== 'kumite') {
            throw new FachFehler('Shobu Sanbon gibt es nur im Kumite Einzel.');
        }
        if ($modus === 'punkte' && $wertung !== 'punkte') {
            throw new FachFehler('Im Modus „Punkte-Vorrunde“ muss die Vorrunde im Punktesystem gewertet werden.');
        }
        $alterMin = $int($d['alter_min'] ?? null);
        $alterMax = $int($d['alter_max'] ?? null);
        $gradMin = $int($d['grad_min'] ?? null);
        $gradMax = $int($d['grad_max'] ?? null);
        if ($alterMin !== null && $alterMax !== null && $alterMin > $alterMax) {
            throw new FachFehler('Das Mindestalter ist größer als das Höchstalter.');
        }
        if ($gradMin !== null && $gradMax !== null && $gradMin > $gradMax) {
            throw new FachFehler('Der Graduierungsbereich ist ungültig („von“ muss der niedrigere Grad sein).');
        }
        $pools = max(1, min(16, (int) ($d['anzahl_pools'] ?? 1)));
        $aufsteiger = (int) ($d['aufsteiger_pro_pool'] ?? 2);
        if (!in_array($aufsteiger, [1, 2, 4, 8], true)) {
            throw new FachFehler('Aufsteiger je Pool: 1, 2, 4 oder 8.');
        }
        $kampfrichter = (int) ($d['kampfrichter'] ?? 5);
        if (!in_array($kampfrichter, [3, 5, 7], true)) {
            throw new FachFehler('Anzahl der Kampfrichter: 3, 5 oder 7.');
        }
        $optionen = is_array($d['optionen'] ?? null) ? $d['optionen'] : json_decode((string) ($d['optionen'] ?? ''), true);

        return [
            'code' => $code,
            'bezeichnung' => trim((string) ($d['bezeichnung'] ?? '')) ?: $code,
            'disziplin' => $disziplin,
            'kumite_form' => in_array($d['kumite_form'] ?? '', self::KUMITE_FORMEN, true) && str_starts_with($disziplin, 'kumite') ? $d['kumite_form'] : null,
            'geschlecht' => $geschlecht,
            'alter_min' => $alterMin,
            'alter_max' => $alterMax,
            'grad_min' => $gradMin,
            'grad_max' => $gradMax,
            'erlaubte_kata' => trim((string) ($d['erlaubte_kata'] ?? '')) ?: null,
            'exklusiv_gruppe' => trim((string) ($d['exklusiv_gruppe'] ?? '')) ?: null,
            'modus' => $modus,
            'anzahl_pools' => $pools,
            'aufsteiger_pro_pool' => $aufsteiger,
            'finale_art' => ($d['finale_art'] ?? 'ko') === 'punkte' ? 'punkte' : 'ko',
            'finalisten' => max(2, min(16, (int) ($d['finalisten'] ?? 4))),
            'wertung' => $wertung,
            'wertung_finale' => $wertungFinale,
            'kampfrichter' => $kampfrichter,
            'min_starter' => max(self::MIN_STARTER, min(64, (int) ($d['min_starter'] ?? self::MIN_STARTER))),
            'dojo_trennung' => empty($d['dojo_trennung']) && array_key_exists('dojo_trennung', $d) ? 0 : 1,
            'gleiche_flaeche' => empty($d['gleiche_flaeche']) ? 0 : 1,
            'optionen' => is_array($optionen) && $optionen !== [] ? json_encode($optionen) : null,
            'sortierung' => (int) ($d['sortierung'] ?? 0),
        ];
    }
}
