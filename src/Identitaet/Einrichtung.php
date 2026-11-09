<?php

declare(strict_types=1);

namespace Wettkampf\Identitaet;

use Wettkampf\Kern\Db\Database;

/**
 * Ersteinrichtung (OWASP A01): Solange es kein Konto gibt, darf nur das erste Leitungskonto
 * anlegen, wer den Einrichtungscode kennt. Der Code steht in einer Datei unter data/ (nicht
 * über das Web erreichbar) – wer Zugriff auf die Dateien der Installation hat, darf einrichten.
 * Ausnahme: Aufruf über den integrierten PHP-Server vom eigenen Rechner (start-lokal.bat).
 */
final class Einrichtung
{
    public function __construct(private readonly Database $db, private readonly string $codeDatei) {}

    /** Noch kein Konto angelegt? */
    public function offen(): bool
    {
        return (int) $this->db->wert('SELECT COUNT(*) FROM benutzer') === 0;
    }

    /** Integrierter PHP-Server, Aufruf vom selben Rechner: kein Code nötig. */
    public static function lokalerServer(): bool
    {
        return PHP_SAPI === 'cli-server' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    }

    /** Einrichtungscode; legt die Datei beim ersten Aufruf an. */
    public function code(): string
    {
        $code = is_file($this->codeDatei) ? trim((string) file_get_contents($this->codeDatei)) : '';
        if (preg_match('/^[a-f0-9]{32}$/', $code)) {
            return $code;
        }
        $code = bin2hex(random_bytes(16));
        $verzeichnis = dirname($this->codeDatei);
        if (!is_dir($verzeichnis)) {
            mkdir($verzeichnis, 0775, true);
        }
        if (file_put_contents($this->codeDatei, $code . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('Einrichtungscode konnte nicht gespeichert werden: ' . $this->codeDatei);
        }
        chmod($this->codeDatei, 0600);
        return $code;
    }

    public function codePasst(string $eingabe): bool
    {
        $eingabe = strtolower(trim($eingabe));
        return $eingabe !== '' && hash_equals($this->code(), $eingabe);
    }

    /** Nach dem Anlegen des ersten Kontos: Code ungültig machen. */
    public function abschliessen(): void
    {
        if (is_file($this->codeDatei)) {
            unlink($this->codeDatei);
        }
    }
}
