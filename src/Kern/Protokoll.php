<?php

declare(strict_types=1);

namespace Wettkampf\Kern;

use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Db\Database;

/**
 * Audit-Log (Tabelle audit_log) und Revisionszähler. Jede fachliche Änderung an einem Turnier
 * erhöht dessen Revision; Live-Ansichten fragen nur die Revision ab und
 * laden Daten nur bei Änderungen neu.
 *
 * Aufbewahrung: Einträge eines Turniers leben so lange wie das Turnier (Teil der Sicherung, beim
 * Löschen des Turniers mit entfernt). Globale Einträge ohne Turnier (Konten, Login-Sperren mit IP)
 * werden nach AUFBEWAHRUNG_GLOBAL_TAGE gelöscht (Datenminimierung, DSGVO Art. 5).
 */
final class Protokoll
{
    public const AUFBEWAHRUNG_GLOBAL_TAGE = 90;

    public function __construct(private readonly Database $db) {}

    /** Globale Einträge älter als die Aufbewahrungsfrist löschen. @return int Anzahl gelöschter Einträge */
    public function aufraeumen(?int $jetzt = null): int
    {
        // Kalendertage statt 86400 Sekunden: sonst verschiebt die Zeitumstellung die Grenze um eine Stunde
        $grenze = date('Y-m-d H:i:s', (int) strtotime('-' . self::AUFBEWAHRUNG_GLOBAL_TAGE . ' days', $jetzt ?? time()));
        return $this->db->query('DELETE FROM audit_log WHERE turnier_id IS NULL AND zeit < ?', [$grenze])->rowCount();
    }

    /** Alle Einträge eines Turniers löschen (beim Löschen des Turniers). */
    public function turnierEntfernen(int $turnierId): void
    {
        $this->db->query('DELETE FROM audit_log WHERE turnier_id = ?', [$turnierId]);
    }

    /** @param array<array-key, mixed>|null $daten */
    public function eintragen(?int $turnierId, string $entitaet, ?int $entitaetId, string $aktion, ?array $daten = null): void
    {
        $this->db->insert('audit_log', [
            'zeit' => date('Y-m-d H:i:s'),
            'benutzer' => Session::akteur(),
            'turnier_id' => $turnierId,
            'entitaet' => $entitaet,
            'entitaet_id' => $entitaetId,
            'aktion' => $aktion,
            'daten' => $daten === null ? null : json_encode($daten, JSON_UNESCAPED_UNICODE),
        ]);
        if ($turnierId !== null) {
            $this->revisionErhoehen($turnierId);
        } else {
            $this->aufraeumen(); // globale Einträge sind selten – dabei gleich alte entfernen
        }
    }

    public function revisionErhoehen(int $turnierId): void
    {
        $this->db->query('UPDATE turnier SET revision = revision + 1 WHERE id = ?', [$turnierId]);
    }
}
