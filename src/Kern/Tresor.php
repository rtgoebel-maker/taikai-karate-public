<?php

declare(strict_types=1);

namespace Wettkampf\Kern;

/**
 * Verschlüsselt Geheimnisse, die in der Datenbank liegen müssen (z. B. das SMTP-Passwort aus der Oberfläche),
 * mit AES-256-GCM (OpenSSL; authentifiziert – Veränderungen fallen auf). Der Schlüssel wird aus dem Schlüssel der
 * Installation abgeleitet (Datei in data/, nicht in der Datenbank): Ein Datenbank-Dump oder der Datenbank-Download
 * allein verrät das Geheimnis also nicht (OWASP A02).
 */
final class Tresor
{
    private const PRAEFIX = 'v1:';

    public function __construct(private readonly string $installationsSchluessel) {}

    public function verschluesseln(string $klartext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $geheim = openssl_encrypt($klartext, 'aes-256-gcm', $this->schluessel(), OPENSSL_RAW_DATA, $iv, $tag, 'tresor', 16);
        if ($geheim === false) {
            throw new \RuntimeException('Verschlüsseln nicht möglich (OpenSSL).');
        }
        return self::PRAEFIX . base64_encode($iv . $tag . $geheim);
    }

    /** null, wenn der Wert fehlt, verändert wurde oder mit einem anderen Schlüssel verschlüsselt ist. */
    public function entschluesseln(string $wert): ?string
    {
        if (!str_starts_with($wert, self::PRAEFIX)) {
            return null;
        }
        $roh = base64_decode(substr($wert, strlen(self::PRAEFIX)), true);
        if ($roh === false || strlen($roh) < 28) {
            return null;
        }
        $klar = openssl_decrypt(substr($roh, 28), 'aes-256-gcm', $this->schluessel(), OPENSSL_RAW_DATA, substr($roh, 0, 12), substr($roh, 12, 16), 'tresor');
        return $klar === false ? null : $klar;
    }

    private function schluessel(): string
    {
        return hash_hmac('sha256', 'tresor-v1', $this->installationsSchluessel, true);
    }
}
