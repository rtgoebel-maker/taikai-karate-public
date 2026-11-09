<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Mail;

use Wettkampf\Kern\Db\Database;

/**
 * E-Mail-Warteschlange: Nachrichten werden eingereiht und nach der Antwort an den Browser verschickt
 * (App, wie die automatische Sicherung). Schlägt der Versand fehl, folgen bis zu 5 Versuche mit wachsendem
 * Abstand. Aktiv nur, wenn in config.php ein Transport und die öffentliche Adresse (für Links) eingetragen
 * sind – auf dem Hallen-Laptop also in der Regel nicht; dann wird auch nichts eingereiht.
 */
final class Postausgang
{
    public const MAX_VERSUCHE = 5;

    public function __construct(
        private readonly Database $db,
        private readonly Transport $transport,
        private readonly string $oeffentlicheUrl,
        private readonly ?string $sperrDatei = null,
    ) {}

    /** @param array<string, mixed> $config */
    public static function ausKonfiguration(Database $db, array $config, string $wurzel): ?self
    {
        $url = rtrim((string) ($config['oeffentliche_url'] ?? ''), '/');
        if ($url === '' || !preg_match('#^https?://#', $url)) {
            return null;
        }
        $transport = self::transport($config, $wurzel);
        return $transport === null ? null : new self($db, $transport, $url, $wurzel . '/data/mail.sperre');
    }

    /**
     * Transport aus der Konfiguration (auch für die Testmail ohne Warteschlange); null = kein Versand eingerichtet.
     *
     * @param array<string, mixed> $config
     */
    public static function transport(array $config, string $wurzel): ?Transport
    {
        $mail = (array) ($config['mail'] ?? []);
        return match ($mail['transport'] ?? null) {
            'smtp' => ($mail['host'] ?? '') !== '' && filter_var($mail['absender'] ?? '', FILTER_VALIDATE_EMAIL) ? new SmtpTransport($mail) : null,
            'datei' => new DateiTransport($wurzel . '/data/mail'),
            default => null,
        };
    }

    /** Absolute Adresse für Links in Mails, z. B. link('anmeldung/3/abc') – nie aus dem Host-Header der Anfrage (OWASP A07). */
    public function link(string $pfad): string
    {
        return $this->oeffentlicheUrl . '/' . ltrim($pfad, '/');
    }

    /** @param list<array{name: string, mime: string, daten: string}> $anhaenge Inhalt roh, z. B. ein Screenshot */
    public function einreihen(string $empfaenger, string $betreff, string $text, string $anlass, ?int $turnierId = null, array $anhaenge = []): void
    {
        $empfaenger = trim($empfaenger);
        if (filter_var($empfaenger, FILTER_VALIDATE_EMAIL) === false) {
            return; // ungültige Adresse: still übergehen, die Aktion selbst soll nicht scheitern
        }
        $this->db->insert('mail_ausgang', [
            'empfaenger' => mb_substr($empfaenger, 0, 190),
            'betreff' => mb_substr(str_replace(["\r", "\n"], ' ', $betreff), 0, 200),
            'text' => $text,
            'anlass' => mb_substr($anlass, 0, 40),
            'turnier_id' => $turnierId,
            'erstellt_am' => date('Y-m-d H:i:s'),
            'anhaenge' => $anhaenge === [] ? null : json_encode(array_map(static fn(array $a) => [
                'name' => preg_replace('/[^A-Za-z0-9._-]/', '_', $a['name']), 'mime' => $a['mime'], 'daten' => base64_encode($a['daten']),
            ], $anhaenge), JSON_THROW_ON_ERROR),
        ]);
    }

    /** @return list<array{name: string, mime: string, daten: string}> */
    private static function anhaenge(mixed $json): array
    {
        $liste = json_decode((string) $json, true);
        if (!is_array($liste)) {
            return [];
        }
        $ergebnis = [];
        foreach ($liste as $a) {
            if (is_array($a) && isset($a['name'], $a['mime'], $a['daten'])) {
                $ergebnis[] = ['name' => (string) $a['name'], 'mime' => (string) $a['mime'], 'daten' => (string) base64_decode((string) $a['daten'], true)];
            }
        }
        return $ergebnis;
    }

    /** Fällige Nachrichten verschicken. @return int Anzahl verschickter Nachrichten */
    public function versenden(int $max = 10, ?int $jetzt = null): int
    {
        $jetzt ??= time();
        $zeit = date('Y-m-d H:i:s', $jetzt);
        $offen = $this->db->alle(
            'SELECT * FROM mail_ausgang WHERE gesendet_am IS NULL AND versuche < ? AND (naechster_versuch IS NULL OR naechster_versuch <= ?) ORDER BY id LIMIT ' . max(1, $max),
            [self::MAX_VERSUCHE, $zeit],
        );
        if ($offen === []) {
            return 0;
        }
        $griff = $this->sperrDatei !== null ? @fopen($this->sperrDatei, 'c') : null;
        if ($griff === false || ($griff !== null && !flock($griff, LOCK_EX | LOCK_NB))) {
            return 0; // verschickt gerade eine andere Anfrage
        }
        $anzahl = 0;
        try {
            foreach ($offen as $m) {
                // inzwischen von einer anderen Anfrage verschickt?
                if ($this->db->wert('SELECT gesendet_am FROM mail_ausgang WHERE id = ?', [$m['id']]) !== null) {
                    continue;
                }
                try {
                    $this->transport->senden((string) $m['empfaenger'], (string) $m['betreff'], (string) $m['text'], self::anhaenge($m['anhaenge'] ?? null));
                    $this->db->update('mail_ausgang', ['gesendet_am' => date('Y-m-d H:i:s', $jetzt), 'versuche' => (int) $m['versuche'] + 1, 'fehler' => null], ['id' => $m['id']]);
                    $anzahl++;
                } catch (\Throwable $f) {
                    $versuche = (int) $m['versuche'] + 1;
                    $this->db->update('mail_ausgang', [
                        'versuche' => $versuche,
                        'naechster_versuch' => date('Y-m-d H:i:s', $jetzt + 300 * 2 ** ($versuche - 1)),
                        'fehler' => mb_substr($f->getMessage(), 0, 500),
                    ], ['id' => $m['id']]);
                    error_log('Mailversand fehlgeschlagen: ' . $f->getMessage());
                }
            }
            // Aufräumen: verschickte Nachrichten nach 30 Tagen löschen (enthalten Bearbeitungslinks)
            $this->db->query('DELETE FROM mail_ausgang WHERE gesendet_am IS NOT NULL AND gesendet_am < ?', [date('Y-m-d H:i:s', $jetzt - 30 * 86400)]);
        } finally {
            if (is_resource($griff)) {
                flock($griff, LOCK_UN);
                fclose($griff);
            }
        }
        return $anzahl;
    }
}
