<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wettkampf\Kern\Http\Response;

/** Sicherheits-Header für jede Antwort (OWASP A02), sofern die Antwort sie nicht selbst setzt; HSTS unter HTTPS. */
final class SicherheitsHeader implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $antwort = $handler->handle($request);
        $header = Response::SICHERHEIT;
        $https = (string) ($request->getServerParams()['HTTPS'] ?? '');
        if ($https !== '' && $https !== 'off') {
            $header['Strict-Transport-Security'] = 'max-age=31536000';
        }
        foreach ($header as $name => $wert) {
            if (!$antwort->hasHeader($name)) {
                $antwort = $antwort->withHeader($name, $wert);
            }
        }
        return $antwort;
    }
}
