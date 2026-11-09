<?php

declare(strict_types=1);

namespace Wettkampf\Kern;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;

/**
 * Begrenzt, wie oft eine Aktion je Absender in einem Zeitfenster ausgeführt wird (OWASP A06/API4),
 * z. B. Online-Anmeldungen je IP. Gezählt werden nur erfolgreiche Aktionen (zaehlen() nach dem
 * Speichern), damit Tippfehler im Formular niemanden sperren.
 */
final class Drossel
{
    public function __construct(private readonly Database $db) {}

    /** Wirft einen Fehler, wenn $ziel im laufenden Fenster schon $max-mal gezählt wurde. */
    public function pruefen(string $ziel, int $max, int $sekunden, ?int $jetzt = null): void
    {
        if ($this->anzahl($ziel, $sekunden, $jetzt ?? time()) >= $max) {
            throw new FachFehler('Zu viele Anfragen in kurzer Zeit. Bitte später erneut versuchen.');
        }
    }

    public function zaehlen(string $ziel, int $sekunden, ?int $jetzt = null): void
    {
        $jetzt ??= time();
        $schluessel = self::schluessel($ziel);
        $this->db->transaktion(function () use ($schluessel, $ziel, $sekunden, $jetzt) {
            $anzahl = $this->anzahl($ziel, $sekunden, $jetzt);
            $zeile = $this->db->eine('SELECT fenster_start FROM drossel WHERE schluessel = ?', [$schluessel]);
            $start = $anzahl === 0 ? date('Y-m-d H:i:s', $jetzt) : (string) ($zeile['fenster_start'] ?? date('Y-m-d H:i:s', $jetzt));
            if ($zeile === null) {
                $this->db->insert('drossel', ['schluessel' => $schluessel, 'fenster_start' => $start, 'anzahl' => 1]);
            } else {
                $this->db->update('drossel', ['fenster_start' => $start, 'anzahl' => $anzahl + 1], ['schluessel' => $schluessel]);
            }
        });
    }

    private function anzahl(string $ziel, int $sekunden, int $jetzt): int
    {
        $zeile = $this->db->eine('SELECT fenster_start, anzahl FROM drossel WHERE schluessel = ?', [self::schluessel($ziel)]);
        if ($zeile === null || strtotime((string) $zeile['fenster_start']) <= $jetzt - $sekunden) {
            return 0; // kein Eintrag oder Fenster abgelaufen
        }
        return (int) $zeile['anzahl'];
    }

    private static function schluessel(string $ziel): string
    {
        return hash('sha256', $ziel);
    }
}
