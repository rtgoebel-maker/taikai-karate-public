<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Db;

use PDO;
use PDOStatement;
use Wettkampf\Kern\Uuid;
use Throwable;

/**
 * Dünne PDO-Abstraktion für SQLite (lokal) und MySQL/MariaDB (online).
 * Es wird ausschließlich portables SQL verwendet; Dialekt-Unterschiede
 * stecken in den Migrationen (Platzhalter) und in wenigen Methoden hier.
 */
final class Database
{
    /**
     * Tabellen mit Spalte uuid: Datensätze eines Turniers, die Halle, Online und Sicherungen eindeutig
     * wiedererkennen müssen (Schritt 9). insert() setzt die UUID, wenn sie fehlt; der Migrator trägt sie im Bestand nach.
     */
    public const MIT_UUID = [
        'turnier', 'verein', 'athlet', 'klasse', 'anmeldung',
        'tatami', 'teilnahme', 'team', 'team_mitglied', 'klasse_regel', 'start', 'zusammenlegung', 'auslosung',
        'phase', 'begegnung', 'auftritt', 'platzierung', 'tatami_nachricht', 'turnier_logo', 'korrekturantrag', 'audit_log',
    ];

    private int $transaktionsTiefe = 0;

    public function __construct(private readonly PDO $pdo, private readonly string $driver)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if ($driver === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        }
    }

    /** @param array<string, mixed> $config */
    public static function ausKonfiguration(array $config): self
    {
        $driver = $config['driver'] ?? 'sqlite';
        if ($driver === 'sqlite') {
            $pfad = $config['sqlite_pfad'];
            if ($pfad !== ':memory:' && !is_dir(dirname($pfad))) {
                mkdir(dirname($pfad), 0775, true);
            }
            return new self(new PDO('sqlite:' . $pfad), 'sqlite');
        }
        if ($driver === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config['host'],
                (int) ($config['port'] ?? 3306),
                $config['name'],
            );
            $pdo = new PDO($dsn, $config['user'], $config['passwort'] ?? '');
            $pdo->exec("SET time_zone = '+00:00', sql_mode = 'STRICT_ALL_TABLES'");
            return new self($pdo, 'mysql');
        }
        throw new \InvalidArgumentException("Unbekannter Datenbanktreiber: $driver");
    }

    public static function inMemory(): self
    {
        return new self(new PDO('sqlite::memory:'), 'sqlite');
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @param array<int|string, mixed> $params */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $wert) {
            $stmt->bindValue(
                is_int($key) ? $key + 1 : ':' . ltrim($key, ':'),
                $wert,
                match (true) {
                    is_int($wert) => PDO::PARAM_INT,
                    is_bool($wert) => PDO::PARAM_BOOL,
                    $wert === null => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                },
            );
        }
        $stmt->execute();
        return $stmt;
    }

    /** @return list<array<string, mixed>> */
    public function alle(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function eine(string $sql, array $params = []): ?array
    {
        $zeile = $this->query($sql, $params)->fetch();
        return $zeile === false ? null : $zeile;
    }

    public function wert(string $sql, array $params = []): mixed
    {
        $wert = $this->query($sql, $params)->fetchColumn();
        return $wert === false ? null : $wert;
    }

    /** @return list<mixed> */
    public function spalte(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Tabellen- und Spaltennamen werden ins SQL eingesetzt (Werte laufen über Platzhalter).
     * Nur einfache Bezeichner zulassen – z. B. Spalten aus einer importierten Sicherung (OWASP A03).
     */
    /** @psalm-taint-escape sql nur [a-z_][a-z0-9_]* – sonst Ausnahme */
    private static function bezeichner(string $name): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]{0,63}$/', $name)) {
            throw new \InvalidArgumentException('Ungültiger Tabellen- oder Spaltenname: ' . substr($name, 0, 40));
        }
        return $name;
    }

    /** @param array<string, mixed> $daten */
    public function insert(string $tabelle, array $daten): int
    {
        $tabelle = self::bezeichner($tabelle);
        if (!isset($daten['uuid']) && in_array($tabelle, self::MIT_UUID, true)) {
            $daten['uuid'] = Uuid::v4(); // Datensatz instanzübergreifend erkennbar (Sync, Sicherung)
        }
        $spalten = array_map(static fn($s) => self::bezeichner((string) $s), array_keys($daten));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $tabelle,
            implode(', ', $spalten),
            implode(', ', array_map(static fn($s) => ':' . $s, $spalten)),
        );
        $this->query($sql, $daten);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $daten
     * @param array<string, mixed> $wo   Spalte => Wert (UND-verknüpft)
     */
    public function update(string $tabelle, array $daten, array $wo): int
    {
        $tabelle = self::bezeichner($tabelle);
        $set = [];
        $params = [];
        foreach ($daten as $spalte => $wert) {
            $spalte = self::bezeichner((string) $spalte);
            $set[] = "$spalte = :s_$spalte";
            $params["s_$spalte"] = $wert;
        }
        [$where, $whereParams] = $this->bedingung($wo);
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $tabelle, implode(', ', $set), $where);
        return $this->query($sql, $params + $whereParams)->rowCount();
    }

    /** @param array<string, mixed> $wo */
    public function delete(string $tabelle, array $wo): int
    {
        $tabelle = self::bezeichner($tabelle);
        [$where, $params] = $this->bedingung($wo);
        return $this->query("DELETE FROM $tabelle WHERE $where", $params)->rowCount();
    }

    /**
     * Führt $arbeit in einer Transaktion aus. Verschachtelte Aufrufe laufen
     * in der äußeren Transaktion mit.
     *
     * @template T
     * @param callable(): T $arbeit
     * @return T
     */
    public function transaktion(callable $arbeit): mixed
    {
        if ($this->transaktionsTiefe > 0) {
            $this->transaktionsTiefe++;
            try {
                return $arbeit();
            } finally {
                $this->transaktionsTiefe--;
            }
        }
        $this->pdo->beginTransaction();
        $this->transaktionsTiefe = 1;
        try {
            $ergebnis = $arbeit();
            $this->pdo->commit();
            return $ergebnis;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        } finally {
            $this->transaktionsTiefe = 0;
        }
    }

    /**
     * @param array<string, mixed> $wo
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function bedingung(array $wo): array
    {
        if ($wo === []) {
            throw new \InvalidArgumentException('Leere WHERE-Bedingung ist nicht erlaubt.');
        }
        $teile = [];
        $params = [];
        foreach ($wo as $spalte => $wert) {
            $spalte = self::bezeichner((string) $spalte);
            if ($wert === null) {
                $teile[] = "$spalte IS NULL";
            } else {
                $teile[] = "$spalte = :w_$spalte";
                $params["w_$spalte"] = $wert;
            }
        }
        return [implode(' AND ', $teile), $params];
    }
}
