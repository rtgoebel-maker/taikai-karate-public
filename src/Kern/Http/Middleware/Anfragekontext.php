<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Wettkampf\App;
use Wettkampf\Kern\Http\PsrBruecke;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Url;
use Wettkampf\Kern\Sprache;

/**
 * Vor dem Routing: Sitzung starten und prüfen (Leerlauf, Widerruf), Pfad vereinheitlichen,
 * ohne Konto auf die Ersteinrichtung umleiten, gemeinsame Daten für die Templates bereitstellen.
 */
final class Anfragekontext implements MiddlewareInterface
{
    public function __construct(private readonly App $app) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Sprache: Wahl der Person (Cookie), sonst Browsersprache
        $gewaehlt = $request->getCookieParams()[Sprache::COOKIE] ?? ($_COOKIE[Sprache::COOKIE] ?? null);
        Sprache::setzen(Sprache::erkennen(is_string($gewaehlt) ? $gewaehlt : null, $request->getHeaderLine('Accept-Language')));
        Session::starten(OeffentlicheAbfragen::betrifft($request));
        if (Session::leerlaufPruefen()) {
            Session::flash('hinweis', t('Nach 12 Stunden ohne Aktivität abgemeldet – bitte neu anmelden.'));
        }
        $this->app->anmeldungenPruefen();

        // Einheitlicher Pfad fürs Routing: ohne /index.php und ohne Schrägstrich am Ende
        $pfad = PsrBruecke::pfad($request);
        $request = $request
            ->withUri($request->getUri()->withPath(Url::basis() . $pfad))
            ->withAttribute('pfad', $pfad);

        if (!$this->app->hatBenutzer() && $pfad !== '/setup') {
            return PsrBruecke::antwort(Response::weiterleiten(Url::zu('setup')));
        }

        $this->app->gemeinsameDatenTeilen();
        return $handler->handle($request);
    }
}
