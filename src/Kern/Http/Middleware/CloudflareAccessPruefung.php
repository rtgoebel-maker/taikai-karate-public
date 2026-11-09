<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wettkampf\Kern\Http\CloudflareAccess;
use Wettkampf\Kern\Http\PsrBruecke;
use Wettkampf\Kern\Http\Response;

/**
 * Staging/Online hinter Cloudflare Access: nur Anfragen mit gültigem Access-Token
 * zulassen (config: cloudflare_access.aktiv).
 */
final class CloudflareAccessPruefung implements MiddlewareInterface
{
    /** Pfad ohne Access-Prüfung (Zugang 'maschine', eigene Authentifizierung). */
    public const OHNE_ACCESS = '/api/v1/sync/';

    /** @param array<string, mixed> $config Abschnitt cloudflare_access */
    public function __construct(private readonly array $config, private readonly string $cacheDatei) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Sync-Aufrufe des Hallen-Laptops (Server zu Server) schützen sich selbst: Login + Übergabe-Code bzw. HMAC-Signatur
        // je Übergabe. In Cloudflare dafür eine Access-Anwendung mit „Bypass“ nur für /api/v1/sync/ (Staging-Anleitung Kap. 9).
        if (empty($this->config['aktiv']) || str_starts_with(PsrBruecke::pfad($request), self::OHNE_ACCESS)) {
            return $handler->handle($request);
        }
        $pruefer = new CloudflareAccess(
            rtrim((string) ($this->config['team_domain'] ?? ''), '/'),
            (string) ($this->config['aud'] ?? ''),
            $this->cacheDatei,
        );
        $token = $request->getHeaderLine('cf-access-jwt-assertion');
        if ($pruefer->pruefen($token === '' ? null : $token) !== null) {
            return $handler->handle($request);
        }
        return PsrBruecke::antwort(Response::html('Zugriff nur über Cloudflare Access.', 403));
    }
}
