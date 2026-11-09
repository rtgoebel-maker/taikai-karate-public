<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http;

/**
 * Langlebige Geräte-Cookies (vertrautes Gerät beim zweiten Faktor, gekoppeltes Tisch-Gerät): immer HttpOnly,
 * SameSite=Lax und – unter HTTPS – Secure (OWASP A02/A07). Der Wert ist ein Zufallstoken; der Server kennt nur seinen Hash.
 *
 * Auf der Kommandozeile (Tests) landet der Wert direkt in $_COOKIE, damit Folgeanfragen ihn „mitschicken“.
 */
final class Cookie
{
    public static function lesen(string $name): ?string
    {
        $wert = $_COOKIE[$name] ?? null;
        return is_string($wert) && preg_match('/^[0-9a-f]{64}$/', $wert) ? $wert : null;
    }

    public static function setzen(string $name, string $wert, int $ablauf): void
    {
        if (PHP_SAPI === 'cli') {
            $_COOKIE[$name] = $wert;
            return;
        }
        setcookie($name, $wert, [
            'expires' => $ablauf,
            'path' => Url::basis() === '' ? '/' : Url::basis() . '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }

    public static function loeschen(string $name): void
    {
        if (PHP_SAPI === 'cli') {
            unset($_COOKIE[$name]);
            return;
        }
        self::setzen($name, '', 1);
    }

    /** Neues Zufallstoken (256 Bit) – im Cookie der Klartext, in der Datenbank nur hash(). */
    public static function token(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
