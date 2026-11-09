<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Db;

use Wettkampf\Kern\Uuid;

/**
 * Spielt nummerierte SQL-Dateien aus migrations/ ein. Die Dateien verwenden
 * Platzhalter, die je Dialekt ersetzt werden:
 *   {{PK}}   Primärschlüssel mit Auto-Increment
 *   {{FK}}   Typ einer Fremdschlüssel-Spalte
 *   {{GROSSTEXT}} langer Text, z. B. Bilddaten (MySQL: MEDIUMTEXT, da TEXT nur 64 KB fasst)
 *   {{OPTS}} Tabellenoptionen (MySQL: InnoDB, utf8mb4)
 */
final class Migrator
{
    private const PLATZHALTER = [
        'sqlite' => [
            '{{PK}}' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            '{{FK}}' => 'INTEGER',
            '{{GROSSTEXT}}' => 'TEXT',
            '{{OPTS}}' => '',
        ],
        'mysql' => [
            '{{PK}}' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            '{{FK}}' => 'INT UNSIGNED',
            '{{GROSSTEXT}}' => 'MEDIUMTEXT',
            '{{OPTS}}' => ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ],
    ];

    public function __construct(private readonly Database $db, private readonly string $verzeichnis) {}

    /** @return list<string> Namen der neu eingespielten Migrationen */
    public function migrieren(): array
    {
        $this->db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS schema_version (version VARCHAR(100) NOT NULL PRIMARY KEY, eingespielt_am VARCHAR(19) NOT NULL)',
        );
        $vorhanden = array_flip($this->db->spalte('SELECT version FROM schema_version'));
        $dateien = glob($this->verzeichnis . '/*.sql') ?: [];
        sort($dateien);

        $neu = [];
        foreach ($dateien as $datei) {
            $version = basename($datei, '.sql');
            if (isset($vorhanden[$version])) {
                continue;
            }
            $sql = strtr((string) file_get_contents($datei), self::PLATZHALTER[$this->db->driver()]);
            // MySQL kann DDL nicht in Transaktionen zurückrollen; daher anweisungsweise.
            foreach ($this->anweisungen($sql) as $anweisung) {
                $this->db->pdo()->exec($anweisung);
            }
            $this->db->insert('schema_version', ['version' => $version, 'eingespielt_am' => date('Y-m-d H:i:s')]);
            $neu[] = $version;
        }
        if ($neu !== []) {
            $this->uuidsNachtragen();
        }
        return $neu;
    }

    /**
     * Fehlende UUIDs im Bestand nachtragen (Database::MIT_UUID), z. B. nach Migration 023.
     * Läuft nur, wenn gerade Migrationen eingespielt wurden – nicht bei jeder Anfrage.
     *
     * @return int Anzahl ergänzter Zeilen
     */
    public function uuidsNachtragen(): int
    {
        $anzahl = 0;
        foreach (Database::MIT_UUID as $tabelle) {
            try {
                $ids = $this->db->spalte("SELECT id FROM $tabelle WHERE uuid IS NULL OR uuid = ''");
            } catch (\PDOException) {
                continue; // Spalte (noch) nicht vorhanden, z. B. Schema vor Migration 023
            }
            if ($ids === []) {
                continue;
            }
            $this->db->transaktion(function () use ($tabelle, $ids, &$anzahl) {
                foreach ($ids as $id) {
                    $this->db->update($tabelle, ['uuid' => Uuid::v4()], ['id' => (int) $id]);
                    $anzahl++;
                }
            });
        }
        return $anzahl;
    }

    /** @return list<string> */
    private function anweisungen(string $sql): array
    {
        $ohneKommentare = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
        return array_values(array_filter(
            array_map('trim', explode(';', $ohneKommentare)),
            static fn(string $s) => $s !== '',
        ));
    }
}
