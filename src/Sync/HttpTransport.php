<?php

declare(strict_types=1);

namespace Wettkampf\Sync;

use Wettkampf\Domain\FachFehler;

/**
 * HTTP über PHP-Streams (kein curl nötig). Für HTTPS braucht PHP die Erweiterung OpenSSL; Zertifikate werden geprüft.
 */
final class HttpTransport implements Transport
{
    public function __construct(private readonly int $sekunden = 20) {}

    public function post(string $url, string $body, array $header = []): array
    {
        if (str_starts_with($url, 'https://') && !extension_loaded('openssl')) {
            throw new FachFehler('Dieser Rechner kann keine HTTPS-Verbindung aufbauen (PHP-Erweiterung OpenSSL fehlt). Bitte die Übergabe per Datei verwenden.');
        }
        $zeilen = [];
        foreach (array_merge(['Content-Type' => 'application/json', 'Accept' => 'application/json'], $header) as $name => $wert) {
            $zeilen[] = "$name: $wert";
        }
        $kontext = stream_context_create([
            'http' => ['method' => 'POST', 'header' => implode("\r\n", $zeilen), 'content' => $body, 'timeout' => $this->sekunden, 'ignore_errors' => true, 'follow_location' => 0],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $antwort = @file_get_contents($url, false, $kontext);
        // ab PHP 8.4 per Funktion, die Variable $http_response_header entfällt künftig
        $kopf = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : $http_response_header;
        $statuszeile = (string) ($kopf[0] ?? '');
        if ($antwort === false || !preg_match('#^HTTP/\S+ (\d{3})#', $statuszeile, $m)) {
            throw new FachFehler('Der Online-Server ist nicht erreichbar. Bitte Adresse und Internetverbindung prüfen.');
        }
        return ['status' => (int) $m[1], 'body' => $antwort];
    }
}
