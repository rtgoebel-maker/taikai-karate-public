<?php

declare(strict_types=1);

namespace Wettkampf\Sync;

/**
 * Signatur jeder Sync-Sendung (ZIELARCHITEKTUR Kap. 5.3):
 * HMAC-SHA256(Schlüssel der Übergabe, Methode + Pfad + Zeit + Nonce + SHA-256 des Inhalts),
 * übertragen in X-Sync-Instanz, X-Sync-Zeit, X-Sync-Nonce und X-Sync-Signatur.
 * Zeitfenster und einmalige Nonce verhindern das Wiederholen abgefangener Sendungen (geprüft beim Empfang, 9f).
 */
final class Signatur
{
    public const ZEITFENSTER_SEKUNDEN = 300;

    /** @return array<string, string> Header für die Sendung */
    public static function kopf(string $schluessel, string $instanz, string $pfad, string $inhalt, ?int $zeit = null, ?string $nonce = null): array
    {
        $zeit ??= time();
        $nonce ??= bin2hex(random_bytes(16));
        return [
            'X-Sync-Instanz' => $instanz,
            'X-Sync-Zeit' => (string) $zeit,
            'X-Sync-Nonce' => $nonce,
            'X-Sync-Signatur' => self::wert($schluessel, $pfad, (string) $zeit, $nonce, $inhalt),
        ];
    }

    /**
     * Signatur und Zeitfenster prüfen (Nonce prüft der Empfang gegen die Datenbank).
     *
     * @param array<string, string|null> $kopf Header in Kleinbuchstaben (x-sync-zeit, x-sync-nonce, x-sync-signatur)
     */
    public static function gueltig(string $schluessel, string $pfad, string $inhalt, array $kopf, ?int $jetzt = null): bool
    {
        $zeit = (string) ($kopf['x-sync-zeit'] ?? '');
        $nonce = (string) ($kopf['x-sync-nonce'] ?? '');
        $signatur = (string) ($kopf['x-sync-signatur'] ?? '');
        if (!ctype_digit($zeit) || !preg_match('/^[0-9a-f]{32}$/', $nonce) || $signatur === '') {
            return false;
        }
        if (abs(($jetzt ?? time()) - (int) $zeit) > self::ZEITFENSTER_SEKUNDEN) {
            return false;
        }
        return hash_equals(self::wert($schluessel, $pfad, $zeit, $nonce, $inhalt), $signatur);
    }

    private static function wert(string $schluessel, string $pfad, string $zeit, string $nonce, string $inhalt): string
    {
        return hash_hmac('sha256', "POST\n$pfad\n$zeit\n$nonce\n" . hash('sha256', $inhalt), $schluessel);
    }
}
