<?php

declare(strict_types=1);

namespace Wettkampf\Betrieb;

use Wettkampf\Kern\Db\Database;

/**
 * Lesesicht auf das Audit-Log (Kern\Protokoll): Verlauf eines Turniers für dessen Turnierleitung bzw.
 * globale Einträge (Konten, Login-Sperren, gelöschte Turniere) für die Administration. Nur lesend.
 */
final class VerlaufAnsicht
{
    public const JE_SEITE = 100;

    public function __construct(private readonly Database $db) {}

    /**
     * @param int|null $turnierId null = globale Einträge (ohne Turnier)
     * @param array{objekt?: string, aktion?: string, wer?: string} $filter
     * @return array{eintraege: list<array<string, mixed>>, gesamt: int, seite: int, seiten: int,
     *               objekte: list<string>, aktionen: list<string>}
     */
    public function laden(?int $turnierId, array $filter = [], int $seite = 1): array
    {
        $bereich = $turnierId === null ? 'turnier_id IS NULL' : 'turnier_id = ?';
        $basis = $turnierId === null ? [] : [$turnierId];

        $wo = [$bereich];
        $werte = $basis;
        if (($filter['objekt'] ?? '') !== '') {
            $wo[] = 'entitaet = ?';
            $werte[] = $filter['objekt'];
        }
        if (($filter['aktion'] ?? '') !== '') {
            $wo[] = 'aktion = ?';
            $werte[] = $filter['aktion'];
        }
        if (($filter['wer'] ?? '') !== '') {
            // „!“ als Escape-Zeichen: gleiches Verhalten in SQLite und MySQL
            $wo[] = "LOWER(benutzer) LIKE ? ESCAPE '!'";
            $werte[] = '%' . mb_strtolower(str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filter['wer'])) . '%';
        }
        $bedingung = implode(' AND ', $wo);

        $gesamt = (int) $this->db->wert("SELECT COUNT(*) FROM audit_log WHERE $bedingung", $werte);
        $seiten = max(1, (int) ceil($gesamt / self::JE_SEITE));
        $seite = min(max(1, $seite), $seiten);
        $eintraege = $this->db->alle(
            "SELECT * FROM audit_log WHERE $bedingung ORDER BY id DESC LIMIT " . self::JE_SEITE . ' OFFSET ' . (($seite - 1) * self::JE_SEITE),
            $werte,
        );
        foreach ($eintraege as &$e) {
            $e['details'] = self::details($e['daten']);
        }
        unset($e);

        return [
            'eintraege' => $eintraege,
            'gesamt' => $gesamt,
            'seite' => $seite,
            'seiten' => $seiten,
            'objekte' => array_map('strval', $this->db->spalte("SELECT DISTINCT entitaet FROM audit_log WHERE $bereich ORDER BY entitaet", $basis)),
            'aktionen' => array_map('strval', $this->db->spalte("SELECT DISTINCT aktion FROM audit_log WHERE $bereich ORDER BY aktion", $basis)),
        ];
    }

    /**
     * Details (JSON) als lesbare Paare „Schlüssel: Wert“; verschachtelte Werte kompakt als JSON.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function details(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $daten = json_decode($json, true);
        if (!is_array($daten)) {
            return [['', $json]];
        }
        $paare = [];
        foreach ($daten as $schluessel => $wert) {
            $text = match (true) {
                $wert === null => '–',
                is_bool($wert) => $wert ? 'ja' : 'nein',
                is_scalar($wert) => (string) $wert,
                default => (string) json_encode($wert, JSON_UNESCAPED_UNICODE),
            };
            $paare[] = [(string) $schluessel, $text];
        }
        return $paare;
    }
}
