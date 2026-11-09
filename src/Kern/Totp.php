<?php

declare(strict_types=1);

namespace Wettkampf\Kern;

/**
 * Einmalcodes aus einer Authenticator-App (TOTP nach RFC 6238: HMAC-SHA1, 30 Sekunden, 6 Ziffern) – funktioniert mit
 * Google/Microsoft Authenticator, Aegis, 2FAS, FreeOTP, Bitwarden u. a., auch ganz ohne Internet (Hallen-Laptop).
 */
final class Totp
{
    public const SCHRITT = 30;
    public const STELLEN = 6;
    /** akzeptierte Abweichung der Uhren in Zeitschritten (±30 Sekunden) */
    public const FENSTER = 1;
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Neues Geheimnis (160 Bit) in Base32, wie es die Apps erwarten. */
    public static function geheimnis(): string
    {
        return self::base32(random_bytes(20));
    }

    public static function base32(string $daten): string
    {
        $bits = '';
        foreach (str_split($daten) as $zeichen) {
            $bits .= str_pad(decbin(ord($zeichen)), 8, '0', STR_PAD_LEFT);
        }
        $text = '';
        foreach (str_split($bits, 5) as $gruppe) {
            $text .= self::BASE32[bindec(str_pad($gruppe, 5, '0'))];
        }
        return $text;
    }

    public static function base32Dekodieren(string $text): string
    {
        $text = strtoupper((string) preg_replace('/[\s=-]/', '', $text));
        $bits = '';
        foreach (str_split($text) as $zeichen) {
            $wert = strpos(self::BASE32, $zeichen);
            if ($wert === false) {
                throw new \InvalidArgumentException('Ungültiges Base32-Zeichen.');
            }
            $bits .= str_pad(decbin($wert), 5, '0', STR_PAD_LEFT);
        }
        $daten = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $daten .= chr((int) bindec($byte));
            }
        }
        return $daten;
    }

    /** Code für einen Zeitschritt (Unix-Zeit / 30). */
    public static function code(string $geheimnis, int $schritt): string
    {
        $hmac = hash_hmac('sha1', pack('N2', $schritt >> 32 & 0xFFFFFFFF, $schritt & 0xFFFFFFFF), self::base32Dekodieren($geheimnis), true);
        $versatz = ord($hmac[19]) & 0x0F;
        $zahl = (ord($hmac[$versatz]) & 0x7F) << 24 | ord($hmac[$versatz + 1]) << 16 | ord($hmac[$versatz + 2]) << 8 | ord($hmac[$versatz + 3]);
        return str_pad((string) ($zahl % 10 ** self::STELLEN), self::STELLEN, '0', STR_PAD_LEFT);
    }

    /**
     * Eingabe prüfen. Ein Code gilt nur einmal: Zeitschritte bis einschließlich $letzterSchritt werden abgelehnt (Wiederholung).
     *
     * @return int|null der akzeptierte Zeitschritt (als neuen $letzterSchritt speichern), sonst null
     */
    public static function pruefen(string $geheimnis, string $eingabe, int $letzterSchritt, ?int $jetzt = null): ?int
    {
        $code = (string) preg_replace('/\D/', '', $eingabe);
        if (strlen($code) !== self::STELLEN) {
            return null;
        }
        $aktuell = intdiv($jetzt ?? time(), self::SCHRITT);
        for ($s = $aktuell - self::FENSTER; $s <= $aktuell + self::FENSTER; $s++) {
            if ($s > $letzterSchritt && hash_equals(self::code($geheimnis, $s), $code)) {
                return $s;
            }
        }
        return null;
    }

    /** otpauth-URI für den QR-Code der App. */
    public static function uri(string $geheimnis, string $konto, string $aussteller = 'Taikai – Karate'): string
    {
        return 'otpauth://totp/' . rawurlencode($aussteller . ':' . $konto)
            . '?secret=' . $geheimnis . '&issuer=' . rawurlencode($aussteller) . '&digits=' . self::STELLEN . '&period=' . self::SCHRITT;
    }

    /** „ABCD EFGH …“ zum Abtippen. */
    public static function lesbar(string $geheimnis): string
    {
        return implode(' ', str_split($geheimnis, 4));
    }
}
