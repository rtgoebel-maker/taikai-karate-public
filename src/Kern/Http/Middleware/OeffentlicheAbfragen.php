<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wettkampf\App;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Drossel;
use Wettkampf\Kern\Http\PsrBruecke;
use Wettkampf\Kern\Http\Session;

/**
 * Öffentliche Abfragen (Statusseite /live/…, öffentliche API /api/v1/turnier/…) entlasten und begrenzen
 * (OWASP API4/API8):
 *
 * - Besucher ohne Sitzung erhalten `Cache-Control: public, max-age=5` – Cloudflare kann so viele Geräte
 *   aus einer Antwort bedienen. Inhalte sind für alle gleich (nur Name und Verein, keine Geburtsdaten),
 *   Sitzung und Set-Cookie gibt es dafür nicht (Session::starten mit $nurVorhandene).
 * - Mit Sitzung (Turnierleitung, auch für nicht öffentliche Turniere) und bei Fehlern: `no-store`.
 * - Grobe Drosselung je IP. Großzügig, weil in der Halle viele Geräte eine IP teilen und die Statusseite
 *   alle 4 Sekunden nachfragt; Antworten aus dem Cloudflare-Zwischenspeicher zählen nicht. Einstellbar mit
 *   config 'oeffentlich_max_je_minute' (0 = keine Grenze). Läuft ohne Cloudflare genauso, nur ohne Zwischenspeicher.
 */
final class OeffentlicheAbfragen implements MiddlewareInterface
{
    public const MAX_JE_MINUTE = 1200;
    public const MAX_ALTER_SEKUNDEN = 5;

    public function __construct(private readonly App $app) {}

    public static function betrifft(ServerRequestInterface $request): bool
    {
        return strtoupper($request->getMethod()) === 'GET'
            && preg_match('#^/(live|api/v1/turnier)/#', PsrBruecke::pfad($request)) === 1;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!self::betrifft($request)) {
            return $handler->handle($request);
        }
        $max = (int) ($this->app->config['oeffentlich_max_je_minute'] ?? self::MAX_JE_MINUTE);
        if ($max > 0) {
            $drossel = new Drossel($this->app->db);
            $ziel = 'oeffentlich:ip:' . $this->app->clientIp(PsrBruecke::request($request));
            try {
                $drossel->pruefen($ziel, $max, 60);
            } catch (FachFehler $e) {
                return PsrBruecke::antwort($this->app->fehlerAntwort(PsrBruecke::istApi($request), 429, $e->getMessage()))
                    ->withHeader('Retry-After', '60')
                    ->withHeader('Cache-Control', 'no-store');
            }
            $drossel->zaehlen($ziel, 60);
        }

        $antwort = $handler->handle($request);
        $cache = $antwort->getHeaderLine('Cache-Control');
        $anonym = !isset($request->getCookieParams()[Session::COOKIE]) && Session::benutzer() === null && Session::tisch() === null;
        if ($anonym && $antwort->getStatusCode() === 200 && in_array($cache, ['', 'no-store'], true)) {
            return $antwort->withHeader('Cache-Control', 'public, max-age=' . self::MAX_ALTER_SEKUNDEN);
        }
        if (!$anonym && str_starts_with($cache, 'public')) {
            // Leitung sieht ggf. mehr (nicht öffentliche Turniere) – nie in einen gemeinsamen Zwischenspeicher
            return $antwort->withHeader('Cache-Control', 'private, no-store');
        }
        return $cache === '' ? $antwort->withHeader('Cache-Control', 'no-store') : $antwort;
    }
}
