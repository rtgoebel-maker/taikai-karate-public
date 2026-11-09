<?php

declare(strict_types=1);

namespace Wettkampf\Kern;

use RuntimeException;

/**
 * Identität dieser Installation (Online-Server oder Hallen-Laptop), dauerhaft unter data/:
 * - instanz.id: Kennung, z. B. welche Halle ein Turnier führt (kein Geheimnis)
 * - instanz.schluessel: geheimer Schlüssel für Prüfsummen eigener Sicherungen (HMAC, Finding 17), nur lesbar für PHP
 * Beides entsteht beim ersten Gebrauch; data/ wird nicht deployt und nicht eingecheckt.
 */
final class Instanz
{
    public function __construct(private readonly string $ordner) {}

    public static function fuer(string $wurzel): self
    {
        return new self($wurzel . '/data');
    }

    public function id(): string
    {
        return $this->lesenOderAnlegen('instanz.id', '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', static fn() => Uuid::v4());
    }

    public function schluessel(): string
    {
        return $this->lesenOderAnlegen('instanz.schluessel', '/^[0-9a-f]{64}$/', static fn() => bin2hex(random_bytes(32)));
    }

    /** @param callable(): string $neu */
    private function lesenOderAnlegen(string $name, string $muster, callable $neu): string
    {
        $datei = $this->ordner . '/' . $name;
        if (is_file($datei)) {
            $wert = trim((string) file_get_contents($datei));
            if (preg_match($muster, $wert)) {
                return $wert;
            }
        }
        $wert = $neu();
        if (!is_dir($this->ordner)) {
            mkdir($this->ordner, 0775, true);
        }
        if (file_put_contents($datei, $wert . "\n", LOCK_EX) === false) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden: ' . $datei);
        }
        chmod($datei, 0600);
        return $wert;
    }
}
