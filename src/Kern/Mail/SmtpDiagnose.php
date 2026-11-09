<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Mail;

use PHPMailer\PHPMailer\SMTP;

/**
 * Verbindungsdiagnose für den SMTP-Versand (System → E-Mail-Versand → „Verbindung prüfen“), Schritt für Schritt:
 * PHP-Umgebung, Namensauflösung, TCP-Verbindung zum Port (dazu die anderen Mail-Ports als Gegenprobe),
 * TLS, SMTP-Begrüßung, Anmeldung. Je Schritt ein Ergebnis mit Hinweis auf die wahrscheinliche Ursache.
 *
 * Das SMTP-Protokoll wird ohne Zugangsdaten ausgegeben: Antworten des Clients nach „AUTH“ (Benutzer und Passwort,
 * Base64) werden ersetzt, das Passwort zusätzlich im Klartext und als Base64 maskiert (OWASP A09).
 */
final class SmtpDiagnose
{
    public const PORTS = [465, 587, 25, 2525];

    /** @var list<array{titel: string, ok: bool|null, text: string}> */
    private array $schritte = [];
    /** @var list<string> */
    private array $protokoll = [];

    /** @param array<string, mixed> $c host, port, verschluesselung, benutzer, passwort */
    public function __construct(private readonly array $c, private readonly int $timeout = 8) {}

    /**
     * @return array{schritte: list<array{titel: string, ok: bool|null, text: string}>, protokoll: list<string>, ports: array<int, bool|null>}
     */
    public function pruefen(): array
    {
        $host = (string) ($this->c['host'] ?? '');
        $port = (int) ($this->c['port'] ?? 587);
        $art = (string) ($this->c['verschluesselung'] ?? 'tls');

        // 1. Umgebung
        $deaktiviert = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $socketOk = function_exists('stream_socket_client') && !in_array('stream_socket_client', $deaktiviert, true);
        $this->schritt('PHP-Umgebung', extension_loaded('openssl') && $socketOk, 'PHP ' . PHP_VERSION
            . ' · OpenSSL ' . (extension_loaded('openssl') ? (string) OPENSSL_VERSION_TEXT : 'FEHLT – für TLS/SSL nötig, beim Hoster aktivieren')
            . ' · Netzwerkverbindungen ' . ($socketOk ? 'erlaubt' : 'vom Hoster gesperrt (disable_functions)'));

        // 2. DNS
        if ($host === '') {
            $this->schritt('SMTP-Server', false, 'Kein SMTP-Server eingetragen.');
            return $this->ergebnis([]);
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        $ipv6 = !filter_var($host, FILTER_VALIDATE_IP) && function_exists('dns_get_record') ? (@dns_get_record($host, DNS_AAAA) ?: []) : [];
        $this->schritt('Namensauflösung (DNS)', $ips !== [], $ips !== []
            ? "$host → " . implode(', ', $ips) . ($ipv6 !== [] ? ' (zusätzlich IPv6: ' . implode(', ', array_column($ipv6, 'ipv6')) . ')' : '')
            : "„{$host}“ lässt sich nicht auflösen – Schreibweise des Servernamens prüfen (Angabe des Mail-Anbieters).");
        if ($ips === []) {
            return $this->ergebnis([]);
        }

        // 3. Ports (TCP) – der eingestellte und als Gegenprobe die übrigen Mail-Ports
        $ports = [];
        foreach (array_unique([$port, ...self::PORTS]) as $p) {
            $ports[$p] = $this->tcp($ips[0], $p, $p === $port ? $this->timeout : 4);
        }
        $offen = array_keys(array_filter($ports, static fn($o) => $o === true));
        if ($ports[$port] !== true) {
            $this->schritt("Verbindung zu Port $port", false, "Keine Verbindung zu {$ips[0]}:$port (" . $this->letzterFehler . '). '
                . ($offen !== []
                    ? 'Erreichbar sind aber Port ' . implode(', ', $offen) . ' – Port und Verschlüsselung entsprechend umstellen (465 = SSL/TLS, 587 = STARTTLS).'
                    : 'Auch die anderen Mail-Ports sind nicht erreichbar: Der Webhoster blockiert vermutlich ausgehende Verbindungen zu fremden Mailservern. '
                        . 'Abhilfe: den Mailserver des eigenen Hosters verwenden (z. B. das Postfach in Plesk unter „E-Mail“ – Servername steht dort) oder beim Hoster die Freigabe erfragen.'));
            return $this->ergebnis($ports);
        }
        $this->schritt("Verbindung zu Port $port", true, "{$ips[0]}:$port erreichbar." . (count($offen) > 1 ? ' Ebenfalls offen: ' . implode(', ', array_diff($offen, [$port])) . '.' : ''));

        // 4.–6. SMTP: Begrüßung, TLS, Anmeldung (PHPMailer, Protokoll ohne Zugangsdaten)
        $smtp = new SMTP();
        $smtp->do_debug = SMTP::DEBUG_SERVER;
        $smtp->Debugoutput = function (string $zeile): void {
            $this->protokollieren($zeile);
        };
        $smtp->Timeout = $this->timeout;
        $ziel = ($art === 'ssl' ? 'ssl://' : '') . $host;
        if (!$smtp->connect($ziel, $port, $this->timeout)) {
            $this->schritt($art === 'ssl' ? 'TLS-Verbindung (SSL) und Begrüßung' : 'SMTP-Begrüßung', false, $this->fehler($smtp)
                . ($art === 'ssl' ? ' – passt die Verschlüsselung zum Port? 465 = SSL/TLS, 587 = STARTTLS. Ggf. ist das Zertifikat des Servers ungültig bzw. passt nicht zum Namen.' : ''));
            return $this->ergebnis($ports);
        }
        $this->schritt($art === 'ssl' ? 'TLS-Verbindung (SSL) und Begrüßung' : 'SMTP-Begrüßung', true, 'Der Mailserver antwortet.');
        $name = gethostname() ?: 'localhost';
        if (!$smtp->hello($name)) {
            $this->schritt('EHLO', false, $this->fehler($smtp));
            $smtp->close();
            return $this->ergebnis($ports);
        }
        if ($art === 'tls') {
            if (!$smtp->startTLS() || !$this->erneutBegruessen($smtp, $name)) {
                $this->schritt('STARTTLS', false, $this->fehler($smtp) . ' – Server bietet kein STARTTLS bzw. Zertifikat ungültig. Für Port 465 „SSL/TLS“ wählen.');
                $smtp->close();
                return $this->ergebnis($ports);
            }
            $this->schritt('STARTTLS', true, 'Verbindung verschlüsselt.');
        }
        if (($this->c['benutzer'] ?? '') === '') {
            $this->schritt('Anmeldung', null, 'Kein Benutzername eingetragen – ohne Anmeldung nehmen die meisten Server keine Mails an.');
        } elseif (!$smtp->authenticate((string) $this->c['benutzer'], (string) ($this->c['passwort'] ?? ''))) {
            $this->schritt('Anmeldung', false, $this->fehler($smtp) . ' – Benutzername oder Passwort des Postfachs prüfen (Benutzername oft die volle Adresse bzw. die Kennung des Anbieters).');
            $smtp->close();
            return $this->ergebnis($ports);
        } else {
            $this->schritt('Anmeldung', true, 'Anmeldung am Postfach erfolgreich. Ein Versand kann jetzt nur noch an der Absenderadresse scheitern (muss meist zum Postfach gehören) – „Testmail an mich senden“ prüft das.');
        }
        $smtp->quit();
        $smtp->close();
        return $this->ergebnis($ports);
    }

    private string $letzterFehler = '';

    /** Nach STARTTLS verlangt SMTP eine neue Begrüßung (EHLO) über die verschlüsselte Verbindung. */
    private function erneutBegruessen(SMTP $smtp, string $name): bool
    {
        return $smtp->hello($name);
    }

    private function tcp(string $ip, int $port, int $timeout): bool
    {
        $ziel = (str_contains($ip, ':') ? "[$ip]" : $ip) . ":$port";
        $verbindung = @stream_socket_client("tcp://$ziel", $nr, $text, $timeout);
        if ($verbindung === false) {
            $this->letzterFehler = trim(($text !== '' ? $text : 'Zeitüberschreitung') . ($nr ? " #$nr" : ''));
            return false;
        }
        fclose($verbindung);
        return true;
    }

    private function fehler(SMTP $smtp): string
    {
        $f = $smtp->getError();
        $text = trim(($f['error'] ?? '') . ' ' . ($f['detail'] ?? '') . ' ' . ($f['smtp_code'] ?? '') . ' ' . ($f['smtp_code_ex'] ?? ''));
        return $this->maskieren($text !== '' ? $text : 'unbekannter Fehler');
    }

    private bool $nachAuth = false;

    /** Eine Zeile des SMTP-Protokolls übernehmen – Zugangsdaten entfernt. */
    private function protokollieren(string $zeile): void
    {
        foreach (preg_split('/\R/', trim($zeile)) ?: [] as $z) {
            if ($z === '') {
                continue;
            }
            $client = str_contains($z, 'CLIENT -> SERVER:');
            if ($client && preg_match('/CLIENT -> SERVER:\s*AUTH\s+(\S+)/i', $z, $m)) {
                $this->nachAuth = true;
                $z = (string) preg_replace('/(AUTH\s+\S+).*/i', '$1 ***', $z); // AUTH PLAIN enthält die Zugangsdaten direkt
            } elseif ($client && $this->nachAuth) {
                $z = 'CLIENT -> SERVER: *** (Zugangsdaten ausgeblendet)';
            } elseif (!$client && preg_match('/SERVER -> CLIENT:\s*(235|5\d\d)/', $z)) {
                $this->nachAuth = false;
            }
            $this->protokoll[] = $this->maskieren(mb_substr($z, 0, 300));
        }
    }

    private function maskieren(string $text): string
    {
        $passwort = (string) ($this->c['passwort'] ?? '');
        return $passwort === '' ? $text : str_replace([$passwort, base64_encode($passwort)], '***', $text);
    }

    private function schritt(string $titel, ?bool $ok, string $text): void
    {
        $this->schritte[] = ['titel' => $titel, 'ok' => $ok, 'text' => $text];
    }

    /**
     * @param array<int, bool|null> $ports
     * @return array{schritte: list<array{titel: string, ok: bool|null, text: string}>, protokoll: list<string>, ports: array<int, bool|null>}
     */
    private function ergebnis(array $ports): array
    {
        return ['schritte' => $this->schritte, 'protokoll' => $this->protokoll, 'ports' => $ports];
    }
}
