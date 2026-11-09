<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Throwable;
use Wettkampf\App;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\PsrBruecke;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Url;

/**
 * Fehler zentral beantworten (OWASP A10): Fachfehler als Meldung (Formular) bzw. 422 (API),
 * unbekannte Seiten 404, falsche Methode 405, alles andere 500 ohne Details nach außen.
 */
final class Fehlerbehandlung implements MiddlewareInterface
{
    public function __construct(private readonly App $app) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $istApi = PsrBruecke::istApi($request);
        try {
            return $handler->handle($request);
        } catch (FachFehler $e) {
            if ($istApi || str_contains($request->getHeaderLine('content-type'), 'application/json')) {
                $antwort = Response::json(['fehler' => $e->getMessage()], 422);
            } else {
                Session::flash('fehler', $e->getMessage());
                $ziel = self::eigeneSeite($request->getHeaderLine('referer'), $request->getHeaderLine('host')) ?? Url::zu('/');
                $antwort = Response::weiterleiten($ziel);
            }
        } catch (NichtGefunden $e) {
            $antwort = $this->app->fehlerAntwort($istApi, 404, $e->getMessage());
        } catch (HttpNotFoundException) {
            $antwort = $this->app->fehlerAntwort($istApi, 404, 'Seite nicht gefunden.');
        } catch (HttpMethodNotAllowedException) {
            $antwort = $this->app->fehlerAntwort($istApi, 405, 'Methode nicht erlaubt.');
        } catch (Throwable $e) {
            error_log((string) $e);
            $text = $this->app->config['debug'] ? $e->getMessage() . "\n" . $e->getTraceAsString() : 'Interner Fehler.';
            $antwort = $this->app->fehlerAntwort($istApi, 500, $text);
        }
        return PsrBruecke::antwort($antwort);
    }

    /** Referer nur übernehmen, wenn er auf diese Anwendung zeigt (kein offener Redirect). */
    public static function eigeneSeite(string $referer, string $host): ?string
    {
        if ($referer === '' || $host === '') {
            return null;
        }
        $teile = parse_url($referer);
        return is_array($teile) && strcasecmp((string) ($teile['host'] ?? '') . (isset($teile['port']) ? ':' . $teile['port'] : ''), $host) === 0
            && in_array($teile['scheme'] ?? '', ['http', 'https'], true) ? $referer : null;
    }
}
