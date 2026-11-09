<?php

declare(strict_types=1);

namespace Wettkampf\Sync;

use Wettkampf\Betrieb\SicherungService;
use Wettkampf\Betrieb\Turnierpaket;
use Wettkampf\Kern\Db\Database;

/**
 * Übernimmt einen Hallenstand in das Online-Turnier (Schritt 9f) – per UUID statt neu einspielen: bekannte
 * Datensätze werden aktualisiert und behalten ihre ID (Links, offene Seiten, Coaching-Portal), neue angelegt,
 * in der Halle gelöschte gelöscht. Verweise und JSON-Felder werden von Hallen- auf Online-IDs umgeschrieben.
 * Felder, die der Hallenstand nicht enthält (Geburtsdaten, PINs …), bleiben unverändert.
 * Vereine und der Änderungsverlauf werden nur ergänzt, Korrekturanträge nie gelöscht (online gestellt, E8).
 */
final class Abgleich
{
    /** Reihenfolge = Abhängigkeiten; Verweisspalten => Zieltabelle. Gelöscht wird in umgekehrter Reihenfolge. */
    private const TABELLEN = [
        'athlet' => ['verein_id' => 'verein'],
        'teilnahme' => ['athlet_id' => 'athlet'],
        'team' => ['verein_id' => 'verein'],
        'team_mitglied' => ['team_id' => 'team', 'teilnahme_id' => 'teilnahme', 'verein_id' => 'verein'],
        'tatami' => [],
        'klasse' => [],
        'klasse_regel' => ['klasse_id' => 'klasse'],
        'start' => ['klasse_id' => 'klasse', 'teilnahme_id' => 'teilnahme', 'team_id' => 'team', 'herkunft_klasse_id' => 'klasse'],
        'zusammenlegung' => ['ziel_klasse_id' => 'klasse', 'quell_klasse_id' => 'klasse'],
        'auslosung' => ['klasse_id' => 'klasse'],
        'phase' => ['klasse_id' => 'klasse', 'tatami_id' => 'tatami'],
        'begegnung' => ['phase_id' => 'phase', 'aka_start_id' => 'start', 'shiro_start_id' => 'start', 'sieger_start_id' => 'start'],
        'auftritt' => ['phase_id' => 'phase', 'start_id' => 'start'],
        'platzierung' => ['klasse_id' => 'klasse', 'start_id' => 'start'],
        'tatami_nachricht' => ['tatami_id' => 'tatami'],
        'korrekturantrag' => ['verein_id' => 'verein', 'teilnahme_id' => 'teilnahme'],
    ];

    /** Tabellen mit Spalte turnier_id. */
    private const MIT_TURNIER = ['teilnahme', 'team', 'tatami', 'klasse', 'tatami_nachricht', 'korrekturantrag'];

    /** Nie löschen: Anträge entstehen auch online. */
    private const NIE_LOESCHEN = ['korrekturantrag'];

    /** Turnierfelder, die nur Online verwaltet (Führung, eigene Revision). */
    private const TURNIER_FEST = ['id', 'uuid', 'revision', 'fuehrung_instanz', 'fuehrung_name', 'fuehrung_seit', 'fuehrung_stand', 'uebergabe_nr'];

    /** @var array<string, array<int, int>> Hallen-ID => Online-ID je Tabelle */
    private array $map = [];

    public function __construct(private readonly Database $db) {}

    /** @param array<string, mixed> $stand geprüfter Hallenstand */
    public function uebernehmen(int $turnierId, array $stand): void
    {
        $this->map = [];
        $this->db->update('turnier', array_diff_key($stand['turnier'], array_flip(self::TURNIER_FEST)), ['id' => $turnierId]);
        $this->vereine($stand['verein'] ?? []);

        // erst löschen, was die Halle nicht mehr hat (Kinder zuerst), dann anlegen bzw. aktualisieren
        $bestand = [];
        foreach (array_keys(self::TABELLEN) as $tabelle) {
            $bestand[$tabelle] = $this->bestand($tabelle, $turnierId);
        }
        foreach (array_reverse(array_keys(self::TABELLEN)) as $tabelle) {
            if (in_array($tabelle, self::NIE_LOESCHEN, true)) {
                continue;
            }
            $bleiben = array_flip(array_column($stand[$tabelle] ?? [], 'uuid'));
            foreach ($bestand[$tabelle] as $uuid => $id) {
                if (!isset($bleiben[$uuid])) {
                    $this->db->delete($tabelle, ['id' => $id]);
                }
            }
        }
        foreach (self::TABELLEN as $tabelle => $verweise) {
            // neu erfassen: Löschen kann über ON DELETE CASCADE auch Zeilen mitnehmen, die die Halle noch hat
            $vorhanden = $this->bestand($tabelle, $turnierId);
            foreach ($stand[$tabelle] ?? [] as $zeile) {
                $this->zeile($tabelle, $zeile, $verweise, $turnierId, $vorhanden[$zeile['uuid']] ?? null);
            }
        }

        // Selbstverweise und JSON-Felder, sobald alle IDs bekannt sind
        foreach ($stand['klasse'] ?? [] as $z) {
            $this->db->update('klasse', ['zusammengelegt_in' => $z['zusammengelegt_in'] === null ? null : $this->ziel('klasse', $z['zusammengelegt_in'])], ['id' => $this->ziel('klasse', $z['id'])]);
        }
        foreach ($stand['begegnung'] ?? [] as $z) {
            $this->db->update('begegnung', [
                'aka_quelle' => SicherungService::quelleUmschreiben($z['aka_quelle'], $this->map),
                'shiro_quelle' => SicherungService::quelleUmschreiben($z['shiro_quelle'], $this->map),
            ], ['id' => $this->ziel('begegnung', $z['id'])]);
        }
        $this->verlauf($turnierId, $stand['audit_log'] ?? []);
        $this->verwaisteAthletenLoeschen();
    }

    /**
     * @param array<string, mixed> $zeile
     * @param array<string, string> $verweise
     */
    private function zeile(string $tabelle, array $zeile, array $verweise, int $turnierId, ?int $onlineId): void
    {
        $hallenId = (int) $zeile['id'];
        unset($zeile['id']);
        foreach ($verweise as $spalte => $ziel) {
            if (array_key_exists($spalte, $zeile) && $zeile[$spalte] !== null && $zeile[$spalte] !== '') {
                $zeile[$spalte] = $this->ziel($ziel, $zeile[$spalte]);
            }
        }
        if (in_array($tabelle, self::MIT_TURNIER, true)) {
            $zeile['turnier_id'] = $turnierId;
        }
        match ($tabelle) {
            'klasse' => $zeile['zusammengelegt_in'] = null, // zweiter Durchgang
            'begegnung' => [$zeile['aka_quelle'], $zeile['shiro_quelle']] = [null, null],
            'auslosung' => $zeile['protokoll'] = SicherungService::protokollUmschreiben($zeile['protokoll'] ?? null, $this->map['start'] ?? []),
            'phase' => $zeile['starter'] = SicherungService::idListeUmschreiben($zeile['starter'] ?? null, $this->map['start'] ?? []),
            default => null,
        };
        // nicht im Bestand des Turniers, aber schon vorhanden (z. B. Athlet, dessen Teilnahme eben mitgelöscht wurde)
        $onlineId ??= ($id = $this->db->wert("SELECT id FROM $tabelle WHERE uuid = ?", [$zeile['uuid']])) !== null ? (int) $id : null;
        if ($onlineId !== null) {
            unset($zeile['uuid']);
            $this->db->update($tabelle, $zeile, ['id' => $onlineId]);
        } else {
            $onlineId = $this->db->insert($tabelle, $zeile);
        }
        $this->map[$tabelle][$hallenId] = $onlineId;
    }

    /**
     * Vereine nur wiedererkennen (UUID, sonst Name) oder anlegen – sie gelten turnierübergreifend.
     *
     * @param list<array<string, mixed>> $vereine
     */
    private function vereine(array $vereine): void
    {
        foreach ($vereine as $v) {
            $id = $this->db->wert('SELECT id FROM verein WHERE uuid = ?', [$v['uuid']])
                ?? $this->db->wert('SELECT id FROM verein WHERE LOWER(name) = LOWER(?)', [$v['name']]);
            if ($id === null) {
                $neu = $v;
                unset($neu['id']);
                $id = $this->db->insert('verein', $neu);
            }
            $this->map['verein'][(int) $v['id']] = (int) $id;
        }
    }

    /**
     * Änderungsverlauf der Halle ergänzen (nur neue Einträge).
     *
     * @param list<array<string, mixed>> $eintraege
     */
    private function verlauf(int $turnierId, array $eintraege): void
    {
        foreach ($eintraege as $z) {
            if ($this->db->wert('SELECT id FROM audit_log WHERE uuid = ?', [$z['uuid']]) !== null) {
                continue;
            }
            unset($z['id']);
            $z['turnier_id'] = $turnierId;
            if ($z['entitaet_id'] !== null) {
                $z['entitaet_id'] = $this->map[$z['entitaet']][(int) $z['entitaet_id']] ?? ($z['entitaet'] === 'turnier' ? $turnierId : $z['entitaet_id']);
            }
            $this->db->insert('audit_log', $z);
        }
    }

    /** @return array<string, int> UUID => Online-ID der Datensätze dieses Turniers */
    private function bestand(string $tabelle, int $turnierId): array
    {
        $klassen = 'SELECT id FROM klasse WHERE turnier_id = ?';
        $sql = match ($tabelle) {
            'athlet' => 'SELECT uuid, id FROM athlet WHERE id IN (SELECT athlet_id FROM teilnahme WHERE turnier_id = ?)',
            'team_mitglied' => 'SELECT uuid, id FROM team_mitglied WHERE team_id IN (SELECT id FROM team WHERE turnier_id = ?)',
            'klasse_regel', 'start', 'auslosung', 'phase', 'platzierung' => "SELECT uuid, id FROM $tabelle WHERE klasse_id IN ($klassen)",
            'zusammenlegung' => "SELECT uuid, id FROM zusammenlegung WHERE ziel_klasse_id IN ($klassen)",
            'begegnung', 'auftritt' => "SELECT uuid, id FROM $tabelle WHERE phase_id IN (SELECT id FROM phase WHERE klasse_id IN ($klassen))",
            default => "SELECT uuid, id FROM $tabelle WHERE turnier_id = ?",
        };
        $ergebnis = [];
        foreach ($this->db->alle($sql, [$turnierId]) as $z) {
            $ergebnis[(string) $z['uuid']] = (int) $z['id'];
        }
        return $ergebnis;
    }

    private function ziel(string $tabelle, mixed $hallenId): int
    {
        return $this->map[$tabelle][(int) $hallenId]
            ?? throw new SyncAbgewiesen(400, 'inhalt', "Ungültiger Verweis auf $tabelle im Hallenstand.");
    }

    /** Athleten ohne Teilnahme (in der Halle entfernte Meldungen) – kein Athleten-Stamm (E1). */
    private function verwaisteAthletenLoeschen(): void
    {
        $this->db->query('DELETE FROM athlet WHERE id NOT IN (SELECT athlet_id FROM teilnahme)');
    }

    /**
     * Hallenstand prüfen: nur erlaubte Bereiche und Spalten (wie eine Sicherung), ohne die Felder, die der Hallenstand
     * nie enthalten darf (PINs, Geburtsdaten …), jede Zeile mit UUID.
     *
     * @param mixed $stand
     */
    public static function pruefen(mixed $stand): void
    {
        if (!is_array($stand) || !is_array($stand['turnier'] ?? null)) {
            throw new SyncAbgewiesen(400, 'inhalt', 'Hallenstand unvollständig.');
        }
        try {
            Turnierpaket::pruefen(['format' => Turnierpaket::FORMAT, 'version' => Turnierpaket::VERSION] + $stand);
        } catch (\Throwable $e) {
            throw new SyncAbgewiesen(400, 'inhalt', 'Hallenstand ungültig: ' . $e->getMessage());
        }
        foreach (Turnierpaket::HALLENSTAND_OHNE_BEREICHE as $bereich) {
            if (array_key_exists($bereich, $stand)) {
                throw new SyncAbgewiesen(400, 'inhalt', "Hallenstand darf $bereich nicht enthalten.");
            }
        }
        foreach (Turnierpaket::HALLENSTAND_OHNE_SPALTEN as $tabelle => $spalten) {
            foreach ($tabelle === 'turnier' ? [$stand['turnier']] : ($stand[$tabelle] ?? []) as $zeile) {
                if (array_intersect($spalten, array_keys($zeile)) !== []) {
                    throw new SyncAbgewiesen(400, 'inhalt', "Hallenstand darf $tabelle." . implode('/', $spalten) . ' nicht enthalten.');
                }
            }
        }
        foreach ($stand as $tabelle => $zeilen) {
            foreach ($tabelle === 'turnier' ? [$zeilen] : $zeilen as $zeile) {
                if (!is_string($zeile['uuid'] ?? null) || !preg_match('/^[0-9a-f-]{36}$/', $zeile['uuid'])) {
                    throw new SyncAbgewiesen(400, 'inhalt', "Hallenstand: Datensatz ohne UUID in $tabelle.");
                }
            }
        }
    }
}
