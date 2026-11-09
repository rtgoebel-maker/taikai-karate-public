<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Routing\RouteContext;
use Wettkampf\App;
use Wettkampf\Kern\Http\PsrBruecke;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Url;

/**
 * Je Route (nach dem Routing): Zugangsstufe prüfen (OWASP A01) und bei POST das CSRF-Token.
 * Stufen siehe Router::ZUGANG. Angemeldet ohne Recht → 403, nicht angemeldet → Anmeldung bzw. 401.
 */
final class Zugang implements MiddlewareInterface
{
    public function __construct(private readonly App $app, private readonly string $stufe) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $istApi = PsrBruecke::istApi($request);
        $erlaubt = match ($this->stufe) {
            'oeffentlich', 'maschine' => true,
            'tisch' => Session::istLeitung() || (Session::tisch() !== null && Session::tischDarf(PsrBruecke::pfad($request))),
            'coach' => Session::coachVerein() !== null,
            'konto' => Session::benutzer() !== null,
            'leitung' => Session::istLeitung(),
            'turnier' => Session::leitet(self::turnierId($request)),
            default => Session::istAdmin(),
        };
        if (!$erlaubt) {
            if (Session::benutzer() !== null) {
                // angemeldet, aber ohne Recht für diese Seite bzw. dieses Turnier (OWASP A01)
                return PsrBruecke::antwort($this->app->fehlerAntwort($istApi, 403, 'Kein Zugriff: Diese Seite gehört zu einem Turnier oder Bereich, für den dieses Konto keine Rechte hat.'));
            }
            if ($istApi) {
                return PsrBruecke::antwort(Response::json(['fehler' => 'Nicht angemeldet.'], 401));
            }
            $ziel = $this->stufe === 'tisch' ? 'tisch' : 'login';
            return PsrBruecke::antwort(Response::weiterleiten(Url::zu($ziel, ['zurueck' => PsrBruecke::pfad($request)])));
        }

        // 'maschine': kein Browser, keine Sitzung – die Route authentifiziert selbst (Login + Code bzw. Signatur)
        if (strtoupper($request->getMethod()) === 'POST' && $this->stufe !== 'maschine') {
            $post = $request->getParsedBody();
            $token = (is_array($post) ? ($post['_csrf'] ?? null) : null) ?? ($request->getHeaderLine('x-csrf-token') ?: null);
            $laenge = (int) ($request->getServerParams()['CONTENT_LENGTH'] ?? 0);
            if ($token === null && $laenge > 0 && str_contains($request->getHeaderLine('content-type'), 'multipart/form-data')) {
                // post_max_size überschritten: PHP verwirft das ganze Formular (auch das Token)
                return PsrBruecke::antwort($this->app->fehlerAntwort($istApi, 413, 'Die hochgeladene Datei ist größer, als der Server annimmt (post_max_size = '
                    . ini_get('post_max_size') . '). Bitte in der PHP-Konfiguration upload_max_filesize und post_max_size erhöhen.'));
            }
            if (!Session::csrfPruefen(is_string($token) ? $token : null)) {
                return PsrBruecke::antwort($this->app->fehlerAntwort($istApi, 419, 'Sitzung abgelaufen. Bitte Seite neu laden.'));
            }
        }
        return $handler->handle($request);
    }

    /** Turnier-ID aus dem Routenparameter {id} (0, wenn keine gültige Zahl). */
    private static function turnierId(ServerRequestInterface $request): int
    {
        $route = $request->getAttribute(RouteContext::ROUTE);
        $id = $route instanceof RouteInterface ? (string) $route->getArgument('id', '') : '';
        return ctype_digit($id) ? (int) $id : 0;
    }
}
