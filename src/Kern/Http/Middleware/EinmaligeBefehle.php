<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Http\PsrBruecke;
use Wettkampf\Kern\Http\Response;

/**
 * Jeder Befehl wirkt nur einmal: Schickt das Tablet eine Aktion mit dem Header X-Befehl-Id und geht die
 * Antwort im WLAN verloren, darf es dieselbe Aktion mit derselben ID gefahrlos wiederholen. Ist sie schon
 * ausgeführt, kommt die gespeicherte Antwort zurück (Header X-Befehl-Wiederholt: 1) – nichts wird doppelt
 * eingetragen, gestartet oder abgehakt.
 *
 * Ablauf: ID vor der Ausführung reservieren (Primärschlüssel verhindert Doppelte auch bei gleichzeitigen
 * Anfragen), bei Erfolg (2xx) die Antwort speichern, bei Fehler die Reservierung wieder löschen – dann
 * führt eine Wiederholung den Befehl regulär aus und liefert dieselbe Fehlermeldung.
 * Bewusst ohne umschließende Transaktion: Fehlversuche bei der Leitungs-PIN bleiben gezählt (OWASP A07).
 *
 * Läuft je Route nach Zugang und Führungsprüfung, gilt nur für POST mit Header.
 */
final class EinmaligeBefehle implements MiddlewareInterface
{
    public const HEADER = 'X-Befehl-Id';
    /** Reservierung ohne Antwort gilt nach so vielen Sekunden als verwaist (Abbruch mitten in der Anfrage). */
    private const VERWAIST = 60;
    /** Gespeicherte Antworten so lange aufheben. */
    private const AUFHEBEN = 2 * 86400;

    public function __construct(private readonly Database $db) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = $request->getHeaderLine(self::HEADER);
        if (strtoupper($request->getMethod()) !== 'POST' || $id === '') {
            return $handler->handle($request);
        }
        if (!preg_match('/^[A-Za-z0-9-]{16,64}$/', $id)) {
            return self::json(400, '{"fehler":"Ungültige Befehls-ID."}');
        }

        $vorher = $this->reservieren($id);
        if ($vorher !== null) {
            return $vorher;
        }
        try {
            $antwort = $handler->handle($request);
        } catch (\Throwable $f) {
            $this->db->delete('tisch_befehl', ['befehl_id' => $id]);
            throw $f;
        }
        if ($antwort->getStatusCode() >= 200 && $antwort->getStatusCode() < 300) {
            $body = $antwort->getBody();
            $inhalt = (string) $body;
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $this->db->update('tisch_befehl', ['status' => $antwort->getStatusCode(), 'antwort' => mb_substr($inhalt, 0, 20000)], ['befehl_id' => $id]);
        } else {
            $this->db->delete('tisch_befehl', ['befehl_id' => $id]);
        }
        return $antwort;
    }

    /** Reserviert die ID; liefert die frühere Antwort, wenn der Befehl schon bekannt ist, sonst null. */
    private function reservieren(string $id, int $versuch = 0): ?ResponseInterface
    {
        $jetzt = time();
        $this->db->query('DELETE FROM tisch_befehl WHERE erstellt_am < ?', [date('Y-m-d H:i:s', $jetzt - self::AUFHEBEN)]);
        try {
            $this->db->insert('tisch_befehl', ['befehl_id' => $id, 'status' => 0, 'antwort' => '', 'erstellt_am' => date('Y-m-d H:i:s', $jetzt)]);
            return null;
        } catch (\PDOException $f) {
            if ($versuch > 2) {
                throw $f;
            }
            $alt = $this->db->eine('SELECT status, antwort, erstellt_am FROM tisch_befehl WHERE befehl_id = ?', [$id]);
            if ($alt === null) {
                return $this->reservieren($id, $versuch + 1); // eben gelöscht (Fehler der ersten Anfrage): neu versuchen
            }
            if ((int) $alt['status'] === 0) {
                if ($jetzt - (int) strtotime((string) $alt['erstellt_am']) > self::VERWAIST) {
                    $this->db->delete('tisch_befehl', ['befehl_id' => $id]);
                    return $this->reservieren($id, $versuch + 1);
                }
                return self::json(409, '{"fehler":"Dieser Befehl wird gerade noch verarbeitet – bitte kurz warten."}', ['Retry-After' => '2']);
            }
            return self::json((int) $alt['status'], (string) $alt['antwort'], ['X-Befehl-Wiederholt' => '1']);
        }
    }

    /** @param array<string, string> $header */
    private static function json(int $status, string $inhalt, array $header = []): ResponseInterface
    {
        return PsrBruecke::antwort(new Response($inhalt, $status, ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'] + $header));
    }
}
