<?php

declare(strict_types=1);

namespace Wettkampf\Sync;

use Wettkampf\Domain\FachFehler;

/**
 * Übergabe-Code (z. B. „K7QM-2XPA-R9“): wird online beim Übergeben einmal angezeigt und am Hallen-Laptop
 * eingegeben. Er belegt die Echtheit der Übergabe-Datei (HMAC) und berechtigt zusammen mit dem Login zum Abholen.
 * 10 Zeichen aus 31 gut lesbaren Zeichen (ohne 0/O, 1/I/L) ≈ 49 Bit; Fehlversuche sind zusätzlich begrenzt.
 */
final class Uebergabecode
{
    private const ZEICHEN = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    private const LAENGE = 10;

    public static function neu(): string
    {
        $code = '';
        for ($i = 0; $i < self::LAENGE; $i++) {
            $code .= self::ZEICHEN[random_int(0, strlen(self::ZEICHEN) - 1)];
        }
        return substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8);
    }

    /** Eingabe vereinheitlichen (Groß-/Kleinschreibung, Bindestriche, Leerzeichen); wirft bei offensichtlich falscher Form. */
    public static function normalisieren(string $eingabe): string
    {
        $code = strtoupper((string) preg_replace('/[\s-]+/', '', $eingabe));
        if (strlen($code) !== self::LAENGE || strspn($code, self::ZEICHEN) !== self::LAENGE) {
            throw new FachFehler('Der Übergabe-Code hat nicht das richtige Format (z. B. K7QM-2XPA-R9).');
        }
        return $code;
    }

    /** Gespeicherter Hash (online), um einen eingegebenen Code wiederzufinden. */
    public static function hash(string $code): string
    {
        return hash('sha256', 'wk-uebergabe-code:' . self::normalisieren($code));
    }

    /** Schlüssel für die Prüfsumme der Übergabe-Datei. */
    public static function dateiSchluessel(string $code): string
    {
        return hash('sha256', 'wk-uebergabe-datei:' . self::normalisieren($code));
    }
}
