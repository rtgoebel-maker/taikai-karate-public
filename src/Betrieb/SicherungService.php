<?php

declare(strict_types=1);

namespace Wettkampf\Betrieb;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Instanz;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Kern\Uuid;

/**
 * Export und Import eines kompletten Turniers als JSON (Backup, Umzug
 * lokal → online). Beim Import werden alle IDs neu vergeben und sämtliche
 * Verweise – auch in Baum-Quellen (W:/L:/S:) und JSON-Feldern – umgeschrieben.
 * Vereine und Athleten werden über ihre UUID (bzw. den Vereinsnamen) wiedererkannt.
 * Enthalten ist alles, was zum Turnier gehört – auch PINs, Einstellungen (Startgeld,
 * Urkunden-Texte), Logos und Unterschriften, Online-Anmeldungen und der Änderungsverlauf.
 */
final class SicherungService
{
    /** @param Instanz|null $instanz für die Prüfsumme (ohne: Export ohne Prüfsumme, Import behandelt jede Datei als fremd) */
    public function __construct(private readonly Database $db, private readonly ?Instanz $instanz = null) {}

    /** @return array<string, mixed> */
    public function exportieren(int $turnierId): array
    {
        $turnier = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$turnierId]) ?? throw new FachFehler('Turnier nicht gefunden.');
        $in = static fn(array $ids) => $ids === [] ? '0' : implode(',', array_map('intval', $ids));

        $teilnahmen = $this->db->alle('SELECT * FROM teilnahme WHERE turnier_id = ?', [$turnierId]);
        $teams = $this->db->alle('SELECT * FROM team WHERE turnier_id = ?', [$turnierId]);
        $athleten = $this->db->alle('SELECT * FROM athlet WHERE id IN (' . $in(array_column($teilnahmen, 'athlet_id')) . ')');
        $mitglieder = $this->db->alle('SELECT * FROM team_mitglied WHERE team_id IN (' . $in(array_column($teams, 'id')) . ')');
        $anmeldungen = $this->db->alle('SELECT * FROM anmeldung WHERE turnier_id = ?', [$turnierId]);
        $antraege = $this->db->alle('SELECT * FROM korrekturantrag WHERE turnier_id = ? ORDER BY id', [$turnierId]);
        $vereinIds = array_merge(array_column($athleten, 'verein_id'), array_column($teams, 'verein_id'), array_column($mitglieder, 'verein_id'), array_column($anmeldungen, 'verein_id'), array_column($antraege, 'verein_id'));
        $klassen = $this->db->alle('SELECT * FROM klasse WHERE turnier_id = ?', [$turnierId]);
        $klassenIds = $in(array_column($klassen, 'id'));
        $phasen = $this->db->alle("SELECT * FROM phase WHERE klasse_id IN ($klassenIds)");
        $phasenIds = $in(array_column($phasen, 'id'));

        $paket = [
            'format' => Turnierpaket::FORMAT,
            'version' => Turnierpaket::VERSION,
            'exportiert_am' => date('Y-m-d H:i:s'),
            'turnier' => $turnier,
            'tatami' => $this->db->alle('SELECT * FROM tatami WHERE turnier_id = ?', [$turnierId]),
            'verein' => $this->db->alle('SELECT * FROM verein WHERE id IN (' . $in(array_filter($vereinIds)) . ')'),
            'athlet' => $athleten,
            'teilnahme' => $teilnahmen,
            'team' => $teams,
            'team_mitglied' => $mitglieder,
            'anmeldung' => $anmeldungen,
            'turnier_logo' => $this->db->alle('SELECT * FROM turnier_logo WHERE turnier_id = ?', [$turnierId]),
            'klasse' => $klassen,
            'klasse_regel' => $this->db->alle("SELECT * FROM klasse_regel WHERE klasse_id IN ($klassenIds)"),
            'start' => $this->db->alle("SELECT * FROM start WHERE klasse_id IN ($klassenIds)"),
            'zusammenlegung' => $this->db->alle("SELECT * FROM zusammenlegung WHERE ziel_klasse_id IN ($klassenIds)"),
            'auslosung' => $this->db->alle("SELECT * FROM auslosung WHERE klasse_id IN ($klassenIds)"),
            'phase' => $phasen,
            'begegnung' => $this->db->alle("SELECT * FROM begegnung WHERE phase_id IN ($phasenIds)"),
            'auftritt' => $this->db->alle("SELECT * FROM auftritt WHERE phase_id IN ($phasenIds)"),
            'platzierung' => $this->db->alle("SELECT * FROM platzierung WHERE klasse_id IN ($klassenIds)"),
            'audit_log' => $this->db->alle('SELECT * FROM audit_log WHERE turnier_id = ? ORDER BY id', [$turnierId]),
            'tatami_nachricht' => $this->db->alle('SELECT * FROM tatami_nachricht WHERE turnier_id = ? ORDER BY id', [$turnierId]),
            'korrekturantrag' => $antraege,
        ];
        return $this->instanz !== null ? Turnierpaket::signieren($paket, $this->instanz) : $paket;
    }

    /**
     * Nur erlaubte Tabellen und Spalten (Turnierpaket::pruefen). Stammt die Datei nicht von dieser Installation
     * (fremde oder fehlende Prüfsumme), werden Tatami-PINs und Leitungs-PIN nicht übernommen.
     *
     * @param array<string, mixed> $d Export-Daten
     * @param bool $uebergabe Echtheit bereits geprüft (Übergabe an die Halle, Schritt 9d): PINs übernehmen
     * @return array{turnier: int, herkunft: string} ID des neuen Turniers, Herkunft (Turnierpaket::EIGENE|FREMD|OHNE|UEBERGABE)
     */
    public function importieren(array $d, bool $uebergabe = false): array
    {
        Turnierpaket::pruefen($d);
        $herkunft = $uebergabe ? Turnierpaket::UEBERGABE : Turnierpaket::herkunft($d, $this->instanz);
        if (!in_array($herkunft, [Turnierpaket::EIGENE, Turnierpaket::UEBERGABE], true)) {
            $d = Turnierpaket::ohnePins($d);
        }

        $turnierId = $this->db->transaktion(function () use ($d, $herkunft) {
            $map = [];
            $neu = function (string $tabelle, array $zeile, array $ersetzen = []) use (&$map): int {
                foreach (array_keys($zeile) as $spalte) {
                    if (!is_string($spalte) || !preg_match('/^[a-z_][a-z0-9_]{0,63}$/', $spalte)) {
                        throw new FachFehler("Die Sicherung enthält eine ungültige Spalte in $tabelle.");
                    }
                }
                $altId = (int) $zeile['id'];
                unset($zeile['id']);
                // UUID behalten, wenn sie hier noch nicht vorkommt (Wiederherstellen, Übergabe an die Halle);
                // sonst neue UUID (Import als Kopie) – Database::insert vergibt sie
                if (isset($zeile['uuid']) && $this->db->wert("SELECT id FROM $tabelle WHERE uuid = ?", [$zeile['uuid']]) !== null) {
                    unset($zeile['uuid']);
                }
                foreach ($ersetzen as $spalte => $ziel) {
                    if ($zeile[$spalte] !== null && $zeile[$spalte] !== '') {
                        $zeile[$spalte] = $map[$ziel][(int) $zeile[$spalte]] ?? throw new FachFehler("Ungültiger Verweis $tabelle.$spalte in der Sicherung.");
                    }
                }
                $id = $this->db->insert($tabelle, $zeile);
                $map[$tabelle][$altId] = $id;
                return $id;
            };

            // Turnier (bei vorhandener UUID als Kopie anlegen)
            $t = $d['turnier'];
            if ($this->db->wert('SELECT id FROM turnier WHERE uuid = ?', [$t['uuid']]) !== null) {
                $t['uuid'] = Uuid::v4();
                $t['name'] .= ' (Import ' . date('d.m.Y H:i') . ')';
            }
            // eine wiederhergestellte Sicherung führt diese Installation selbst (Übergabe an die Halle: Schritt 9d)
            $t['fuehrung_instanz'] = $t['fuehrung_name'] = $t['fuehrung_seit'] = $t['fuehrung_stand'] = null;
            // Anmeldelink: gleicher Schlüssel bei Wiederherstellung, bei einer Kopie neu (eindeutig)
            if (isset($t['anmelde_schluessel']) && $this->db->wert('SELECT id FROM turnier WHERE anmelde_schluessel = ?', [$t['anmelde_schluessel']]) !== null) {
                $t['anmelde_schluessel'] = null;
            }
            $turnierId = $neu('turnier', $t);
            foreach ($d['tatami'] as $z) {
                $neu('tatami', ['turnier_id' => $turnierId] + $z);
            }

            // Vereine und Athleten wiedererkennen oder anlegen
            foreach ($d['verein'] as $v) {
                $id = $this->db->wert('SELECT id FROM verein WHERE uuid = ?', [$v['uuid']])
                    ?? $this->db->wert('SELECT id FROM verein WHERE LOWER(name) = LOWER(?)', [$v['name']]);
                $map['verein'][(int) $v['id']] = $id !== null ? (int) $id : $neu('verein', $v);
            }
            // Athleten gehören zu genau einem Turnier (kein Athleten-Stamm, Entscheidung 27.10.2026):
            // immer neu anlegen, bei schon vorhandener UUID mit neuer UUID
            foreach ($d['athlet'] as $a) {
                if ($this->db->wert('SELECT id FROM athlet WHERE uuid = ?', [$a['uuid']]) !== null) {
                    $a['uuid'] = Uuid::v4();
                }
                $neu('athlet', $a, ['verein_id' => 'verein']);
            }

            foreach ($d['teilnahme'] as $z) {
                $neu('teilnahme', ['turnier_id' => $turnierId] + $z, ['athlet_id' => 'athlet']);
            }
            foreach ($d['team'] as $z) {
                $neu('team', ['turnier_id' => $turnierId] + $z, ['verein_id' => 'verein']);
            }
            foreach ($d['team_mitglied'] as $z) {
                $neu('team_mitglied', $z, ['team_id' => 'team', 'teilnahme_id' => 'teilnahme', 'verein_id' => 'verein']);
            }
            foreach ($d['turnier_logo'] ?? [] as $z) {
                $neu('turnier_logo', ['turnier_id' => $turnierId] + $z);
            }
            foreach ($d['anmeldung'] ?? [] as $z) {
                if ($this->db->wert('SELECT id FROM anmeldung WHERE uuid = ? OR code = ?', [$z['uuid'], $z['code']]) !== null) {
                    $z['uuid'] = Uuid::v4();
                    $z['code'] = bin2hex(random_bytes(12));
                }
                $neu('anmeldung', ['turnier_id' => $turnierId] + $z, ['verein_id' => 'verein']);
            }

            // Klassen zuerst ohne Selbstverweis, dann „zusammengelegt_in“ nachtragen
            foreach ($d['klasse'] as $z) {
                $kopie = ['turnier_id' => $turnierId, 'zusammengelegt_in' => null] + $z;
                if ($this->db->wert('SELECT id FROM klasse WHERE uuid = ?', [$z['uuid']]) !== null) {
                    $kopie['uuid'] = Uuid::v4();
                }
                $neu('klasse', $kopie);
            }
            foreach ($d['klasse'] as $z) {
                if ($z['zusammengelegt_in'] !== null) {
                    $this->db->update('klasse', ['zusammengelegt_in' => $map['klasse'][(int) $z['zusammengelegt_in']]], ['id' => $map['klasse'][(int) $z['id']]]);
                }
            }
            foreach ($d['klasse_regel'] as $z) {
                $neu('klasse_regel', $z, ['klasse_id' => 'klasse']);
            }
            foreach ($d['start'] as $z) {
                $neu('start', $z, ['klasse_id' => 'klasse', 'teilnahme_id' => 'teilnahme', 'team_id' => 'team', 'herkunft_klasse_id' => 'klasse']);
            }
            foreach ($d['zusammenlegung'] as $z) {
                $neu('zusammenlegung', $z, ['ziel_klasse_id' => 'klasse', 'quell_klasse_id' => 'klasse']);
            }
            foreach ($d['auslosung'] as $z) {
                $z['protokoll'] = self::protokollUmschreiben($z['protokoll'], $map['start'] ?? []);
                $neu('auslosung', $z, ['klasse_id' => 'klasse']);
            }
            foreach ($d['phase'] as $z) {
                $z['starter'] = self::idListeUmschreiben($z['starter'], $map['start'] ?? []);
                $neu('phase', $z, ['klasse_id' => 'klasse', 'tatami_id' => 'tatami']);
            }
            foreach ($d['begegnung'] as $z) {
                $z['aka_quelle'] = null;
                $z['shiro_quelle'] = null;
                $neu('begegnung', $z, ['phase_id' => 'phase', 'aka_start_id' => 'start', 'shiro_start_id' => 'start', 'sieger_start_id' => 'start']);
            }
            // Baum-Quellen erst jetzt umschreiben (verweisen auf andere Begegnungen)
            foreach ($d['begegnung'] as $z) {
                $this->db->update('begegnung', [
                    'aka_quelle' => self::quelleUmschreiben($z['aka_quelle'], $map),
                    'shiro_quelle' => self::quelleUmschreiben($z['shiro_quelle'], $map),
                ], ['id' => $map['begegnung'][(int) $z['id']]]);
            }
            foreach ($d['auftritt'] as $z) {
                $neu('auftritt', $z, ['phase_id' => 'phase', 'start_id' => 'start']);
            }
            foreach ($d['platzierung'] as $z) {
                $neu('platzierung', $z, ['klasse_id' => 'klasse', 'start_id' => 'start']);
            }
            foreach ($d['tatami_nachricht'] ?? [] as $z) {
                $neu('tatami_nachricht', ['turnier_id' => $turnierId] + $z, ['tatami_id' => 'tatami']);
            }
            foreach ($d['korrekturantrag'] ?? [] as $z) {
                $neu('korrekturantrag', ['turnier_id' => $turnierId] + $z, ['verein_id' => 'verein', 'teilnahme_id' => 'teilnahme']);
            }
            // Änderungsverlauf übernehmen; Verweise auf Datensätze des Turniers umschreiben
            foreach ($d['audit_log'] ?? [] as $z) {
                unset($z['id']);
                if (isset($z['uuid']) && $this->db->wert('SELECT id FROM audit_log WHERE uuid = ?', [$z['uuid']]) !== null) {
                    unset($z['uuid']);
                }
                $z['turnier_id'] = $turnierId;
                if ($z['entitaet_id'] !== null) {
                    $z['entitaet_id'] = $map[$z['entitaet']][(int) $z['entitaet_id']] ?? $z['entitaet_id'];
                }
                $this->db->insert('audit_log', $z);
            }
            (new Protokoll($this->db))->eintragen($turnierId, 'turnier', $turnierId, 'importiert', ['exportiert_am' => $d['exportiert_am'] ?? null, 'herkunft' => $herkunft]);
            return $turnierId;
        });
        return ['turnier' => $turnierId, 'herkunft' => $herkunft];
    }

    /**
     * Konsistente Kopie der SQLite-Datenbank (VACUUM INTO) für den Download.
     */
    public function sqliteKopie(string $ziel): void
    {
        if ($this->db->driver() !== 'sqlite') {
            throw new FachFehler('Die Datenbank-Sicherung als Datei gibt es nur für SQLite. Bitte den Turnier-Export verwenden.');
        }
        if (is_file($ziel)) {
            unlink($ziel);
        }
        $this->db->query('VACUUM INTO ?', [$ziel]);
    }

    /** @param array<string, array<int, int>> $map */
    public static function quelleUmschreiben(?string $quelle, array $map): ?string
    {
        if ($quelle === null || $quelle === 'F') {
            return $quelle;
        }
        [$typ, $id] = explode(':', $quelle, 2);
        $tabelle = $typ === 'S' ? 'start' : 'begegnung';
        $neueId = $map[$tabelle][(int) $id] ?? throw new FachFehler("Ungültige Baum-Quelle $quelle in der Sicherung.");
        return "$typ:$neueId";
    }

    /** @param array<int, int> $starts */
    public static function idListeUmschreiben(?string $json, array $starts): ?string
    {
        $liste = json_decode((string) $json, true);
        if (!is_array($liste)) {
            return $json;
        }
        return json_encode(array_map(static fn($id) => $id === null ? null : ($starts[(int) $id] ?? $id), $liste));
    }

    /** @param array<int, int> $starts */
    public static function protokollUmschreiben(?string $json, array $starts): ?string
    {
        $p = json_decode((string) $json, true);
        if (!is_array($p)) {
            return $json;
        }
        foreach ($p['phasen'] ?? [] as $i => $phase) {
            foreach (['positionen', 'reihenfolge'] as $feld) {
                if (isset($phase[$feld])) {
                    $p['phasen'][$i][$feld] = array_map(static fn($id) => $id === null ? null : ($starts[(int) $id] ?? $id), $phase[$feld]);
                }
            }
        }
        if (isset($p['starter'])) {
            $neu = [];
            foreach ($p['starter'] as $id => $name) {
                $neu[$starts[(int) $id] ?? $id] = $name;
            }
            $p['starter'] = $neu;
        }
        return json_encode($p, JSON_UNESCAPED_UNICODE);
    }
}
