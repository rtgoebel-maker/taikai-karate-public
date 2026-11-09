<?php

declare(strict_types=1);

namespace Wettkampf\Betrieb;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Db\Migrator;
use Wettkampf\Kern\Instanz;

/**
 * Automatische Sicherung der SQLite-Datenbank im laufenden Betrieb (Hallen-Laptop, lokale Installation):
 * höchstens alle $abstand Sekunden und nur, wenn sich seit der letzten Sicherung etwas geändert hat,
 * eine konsistente Kopie (VACUUM INTO) nach data/sicherungen/. Die ältesten Stände werden gelöscht,
 * sobald mehr als $anzahl vorliegen.
 *
 * Ohne Cron: ausgelöst nach einer Anfrage (App::autoSicherung). Eine Sperrdatei sorgt dafür,
 * dass gleichzeitige Anfragen nicht doppelt sichern. Für MySQL (online) gibt es keine automatische
 * Sicherung – dort sichert der Hoster.
 */
final class AutoSicherung
{
    /** Dateinamen der automatischen Stände: auto_JJJJMMTT_HHMMSS.sqlite */
    private const MUSTER = '/^auto_\d{8}_\d{6}\.sqlite$/';

    public function __construct(
        private readonly Database $db,
        private readonly string $ordner,
        private readonly int $abstand = 120,
        private readonly int $anzahl = 30,
    ) {}

    /**
     * Aus der Konfiguration ('auto_sicherung' => [aktiv, abstand_sekunden, anzahl, ordner]); null, wenn
     * abgeschaltet oder nicht SQLite.
     *
     * @param array<string, mixed> $config
     */
    public static function ausKonfiguration(Database $db, array $config, string $wurzel): ?self
    {
        $c = (array) ($config['auto_sicherung'] ?? []);
        if (($c['aktiv'] ?? true) === false || $db->driver() !== 'sqlite' || self::datenbankDatei($db) === null) {
            return null;
        }
        return new self(
            $db,
            (string) ($c['ordner'] ?? $wurzel . '/data/sicherungen'),
            max(30, (int) ($c['abstand_sekunden'] ?? 120)),
            max(2, (int) ($c['anzahl'] ?? 30)),
        );
    }

    /** Pfad der Datenbankdatei; null bei einer Datenbank im Speicher (Tests). */
    public static function datenbankDatei(Database $db): ?string
    {
        foreach ($db->alle('PRAGMA database_list') as $z) {
            if (($z['name'] ?? '') === 'main') {
                return ($z['file'] ?? '') !== '' ? (string) $z['file'] : null;
            }
        }
        return null;
    }

    /**
     * Sichert, wenn fällig. Gibt den Dateinamen der neuen Sicherung zurück oder null.
     * Fehler (z. B. Platte voll) brechen die Anfrage nicht ab, sie landen im Error-Log.
     */
    public function beiBedarf(?int $jetzt = null): ?string
    {
        $jetzt ??= time();
        $datei = self::datenbankDatei($this->db);
        if ($datei === null || !$this->ordnerBereit()) {
            return null;
        }
        $sperre = $this->ordner . '/.letzte';
        clearstatcache();
        $letzte = is_file($sperre) ? (int) filemtime($sperre) : 0;
        if ($jetzt - $letzte < $this->abstand) {
            return null;
        }
        // geändert seit der letzten Sicherung? (WAL-Datei bzw. Datenbank wurde beschrieben)
        $geaendert = max((int) @filemtime($datei), (int) @filemtime($datei . '-wal'));
        if ($letzte > 0 && $geaendert <= $letzte) {
            return null;
        }
        $griff = fopen($sperre, 'c');
        if ($griff === false) {
            return null;
        }
        try {
            if (!flock($griff, LOCK_EX | LOCK_NB)) {
                return null; // sichert gerade eine andere Anfrage
            }
            clearstatcache();
            if ($letzte !== 0 && (int) filemtime($sperre) !== $letzte) {
                return null; // inzwischen von einer anderen Anfrage erledigt
            }
            $name = $this->sichern($jetzt);
            touch($sperre, $jetzt);
            $this->aufraeumen();
            return $name;
        } catch (\Throwable $f) {
            error_log('Automatische Sicherung fehlgeschlagen: ' . $f->getMessage());
            return null;
        } finally {
            flock($griff, LOCK_UN);
            fclose($griff);
        }
    }

    /** Legt sofort einen Stand an (auch ohne Änderung). @return string Dateiname */
    public function sichern(?int $jetzt = null): string
    {
        if (!$this->ordnerBereit()) {
            throw new FachFehler('Der Ordner für Sicherungen kann nicht angelegt werden: ' . $this->ordner);
        }
        $jetzt ??= time();
        $name = 'auto_' . date('Ymd_His', $jetzt) . '.sqlite';
        $ziel = $this->ordner . '/' . $name;
        $tmp = $ziel . '.tmp';
        if (is_file($tmp)) {
            unlink($tmp);
        }
        $this->db->query('VACUUM INTO ?', [$tmp]);
        rename($tmp, $ziel);
        return $name;
    }

    /**
     * Vorhandene Stände, neueste zuerst.
     *
     * @return list<array{name: string, zeit: int, groesse: int}>
     */
    public function liste(): array
    {
        $liste = [];
        foreach (glob($this->ordner . '/auto_*.sqlite') ?: [] as $pfad) {
            $name = basename($pfad);
            if (preg_match(self::MUSTER, $name)) {
                $liste[] = ['name' => $name, 'zeit' => (int) filemtime($pfad), 'groesse' => (int) filesize($pfad)];
            }
        }
        usort($liste, static fn(array $a, array $b) => strcmp($b['name'], $a['name']));
        return $liste;
    }

    /** Pfad eines Stands; nur gültige Namen aus diesem Ordner (kein Pfad-Trick, OWASP A01). */
    public function pfad(string $name): string
    {
        $pfad = $this->ordner . '/' . $name;
        if (!preg_match(self::MUSTER, $name) || !is_file($pfad)) {
            throw new FachFehler('Diese Sicherung gibt es nicht.');
        }
        return $pfad;
    }

    /**
     * Turniere in einem Stand (zum Wiederherstellen einzelner Turniere).
     *
     * @return list<array{id: int, name: string, datum: string|null}>
     */
    public function turniere(string $name, string $arbeitsordner): array
    {
        return $this->mitStand($name, $arbeitsordner, static fn(Database $alt) => array_map(
            static fn(array $t) => ['id' => (int) $t['id'], 'name' => (string) $t['name'], 'datum' => $t['datum'] ?? null],
            $alt->alle('SELECT id, name, datum FROM turnier ORDER BY datum DESC, id DESC'),
        ));
    }

    /**
     * Ein Turnier aus einem Stand als Turnierpaket (wie „Turnier exportieren“). Der Stand wird dafür
     * kopiert und auf das aktuelle Schema gebracht, das Original bleibt unverändert.
     *
     * @return array<string, mixed>
     */
    public function turnierPaket(string $name, int $turnierId, string $arbeitsordner, string $migrationen, ?Instanz $instanz): array
    {
        return $this->mitStand($name, $arbeitsordner, static function (Database $alt) use ($turnierId, $migrationen, $instanz) {
            (new Migrator($alt, $migrationen))->migrieren();
            return (new SicherungService($alt, $instanz))->exportieren($turnierId);
        });
    }

    /**
     * @template T
     * @param callable(Database): T $arbeit
     * @return T
     */
    private function mitStand(string $name, string $arbeitsordner, callable $arbeit): mixed
    {
        $quelle = $this->pfad($name);
        if (!is_dir($arbeitsordner)) {
            mkdir($arbeitsordner, 0775, true);
        }
        $kopie = $arbeitsordner . '/stand_' . bin2hex(random_bytes(6)) . '.sqlite';
        copy($quelle, $kopie);
        try {
            $alt = new Database(new \PDO('sqlite:' . $kopie), 'sqlite');
            $ergebnis = $arbeit($alt);
            unset($alt);
            return $ergebnis;
        } finally {
            gc_collect_cycles(); // PDO-Verbindung schließen, damit die Kopie gelöscht werden kann (Windows)
            foreach ([$kopie, $kopie . '-wal', $kopie . '-shm'] as $d) {
                if (is_file($d)) {
                    @unlink($d);
                }
            }
        }
    }

    private function aufraeumen(): void
    {
        foreach (array_slice($this->liste(), $this->anzahl) as $alt) {
            @unlink($this->ordner . '/' . $alt['name']);
        }
    }

    private function ordnerBereit(): bool
    {
        if (!is_dir($this->ordner) && !@mkdir($this->ordner, 0775, true) && !is_dir($this->ordner)) {
            return false;
        }
        return is_writable($this->ordner);
    }
}
