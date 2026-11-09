<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;

/**
 * Nachrichten der Wettkampfleitung an die Tischbesatzung einer oder aller Tatamis
 * (z. B. „Bitte ein Mitglied der Tischbesatzung zur Wettkampfleitung“). Sie bleiben
 * auf Tablet und Ausruf-Handy stehen, bis sie dort bestätigt werden.
 */
final class NachrichtService
{
    public const VORLAGEN = [
        'Bitte ein Mitglied der Tischbesatzung zur Wettkampfleitung.',
        'Bitte den Hauptkampfrichter zur Wettkampfleitung.',
        'Nach dieser Gruppe bitte eine kurze Pause einlegen.',
        'Bitte die eingetragenen Ergebnisse der Gruppe prüfen.',
    ];
    private const MAX_TEXT = 500;

    private readonly Protokoll $protokoll;

    public function __construct(private readonly Database $db)
    {
        $this->protokoll = new Protokoll($db);
    }

    /**
     * @param int|null $tatamiId null = an alle Tatamis des Turniers
     * @return int Anzahl der Tatamis, an die gesendet wurde
     */
    public function senden(int $turnierId, ?int $tatamiId, string $text, string $von): int
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            throw new FachFehler('Bitte einen Nachrichtentext eingeben.');
        }
        $tatamis = array_map('intval', $this->db->spalte(
            'SELECT id FROM tatami WHERE turnier_id = ?' . ($tatamiId === null ? '' : ' AND id = ?') . ' ORDER BY nummer',
            $tatamiId === null ? [$turnierId] : [$turnierId, $tatamiId],
        ));
        if ($tatamis === []) {
            throw new FachFehler('Tatami nicht gefunden.');
        }
        return $this->db->transaktion(function () use ($turnierId, $tatamis, $text, $von) {
            foreach ($tatamis as $t) {
                $this->db->insert('tatami_nachricht', [
                    'turnier_id' => $turnierId, 'tatami_id' => $t, 'text' => mb_substr($text, 0, self::MAX_TEXT),
                    'von' => mb_substr($von, 0, 100), 'erstellt_am' => date('Y-m-d H:i:s'),
                ]);
            }
            $this->protokoll->eintragen($turnierId, 'turnier', $turnierId, 'nachricht', ['tatamis' => $tatamis, 'text' => $text]);
            $this->protokoll->revisionErhoehen($turnierId);
            return count($tatamis);
        });
    }

    /** @return list<array{id: int, text: string, von: string, erstellt_am: string}> unbestätigte Nachrichten einer Tatami, älteste zuerst */
    public function offene(int $tatamiId): array
    {
        return array_map(static fn($n) => ['id' => (int) $n['id']] + $n, $this->db->alle(
            'SELECT id, text, von, erstellt_am FROM tatami_nachricht WHERE tatami_id = ? AND bestaetigt_am IS NULL ORDER BY id',
            [$tatamiId],
        ));
    }

    public function bestaetigen(int $turnierId, int $tatamiId, int $nachrichtId, string $von): void
    {
        $n = $this->db->eine('SELECT id, bestaetigt_am FROM tatami_nachricht WHERE id = ? AND tatami_id = ? AND turnier_id = ?', [$nachrichtId, $tatamiId, $turnierId])
            ?? throw new FachFehler('Nachricht nicht gefunden.');
        if ($n['bestaetigt_am'] !== null) {
            return;
        }
        $this->db->update('tatami_nachricht', ['bestaetigt_am' => date('Y-m-d H:i:s'), 'bestaetigt_von' => mb_substr($von, 0, 100)], ['id' => $nachrichtId]);
        $this->protokoll->revisionErhoehen($turnierId);
    }

    /** @return list<array<string, mixed>> letzte Nachrichten des Turniers mit Tatami und Bestätigung, neueste zuerst */
    public function liste(int $turnierId, int $anzahl = 30): array
    {
        return $this->db->alle(
            'SELECT n.*, t.bezeichnung AS tatami FROM tatami_nachricht n JOIN tatami t ON t.id = n.tatami_id
              WHERE n.turnier_id = ? ORDER BY n.id DESC LIMIT ' . max(1, $anzahl),
            [$turnierId],
        );
    }
}
