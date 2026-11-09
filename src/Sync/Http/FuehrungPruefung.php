<?php

declare(strict_types=1);

namespace Wettkampf\Sync\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Routing\RouteContext;
use Wettkampf\App;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Sync\Fuehrung;

/**
 * Zentrale Sperre für alle schreibenden Aufrufe eines Turniers, das diese Installation nicht führt (Schritt 9c).
 * Gilt für POST auf /turnier/{id}/…, /anmeldung/{id}/…, /coach/turnier/{id}/… und die Tablet-API; das Turnier
 * kommt aus dem Routenparameter {id} bzw. aus der Tablet-Anmeldung (Leitung: ?tatami=…).
 * Läuft nach der Zugangsprüfung; die Meldung erscheint wie jeder Fachfehler (Seite: Hinweis, API: 422).
 */
final class FuehrungPruefung implements MiddlewareInterface
{
    /**
     * Bleiben auf jeder Installation möglich: Konten sind nur online (Konzept Kap. 4.2), Anträge holt die Halle ab (E8),
     * und Online muss eine Übergabe zurücknehmen können, gerade weil es dann nicht führt (prüft der Dienst selbst).
     */
    public const AUSNAHMEN = [
        '/turnier/{id}/leitung',
        '/turnier/{id}/leitung/{benutzerid}/entfernen',
        '/coach/turnier/{id}/antraege',
        '/turnier/{id}/halle/zuruecknehmen',
        '/turnier/{id}/halle/loeschen', // Laptop löscht ein Turnier, das er nicht mehr führt (prüft der Dienst)
    ];

    public function __construct(private readonly App $app) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) === 'POST') {
            $route = $request->getAttribute(RouteContext::ROUTE);
            $muster = $route instanceof RouteInterface ? $route->getPattern() : '';
            $turnierId = in_array($muster, self::AUSNAHMEN, true) ? null : $this->turnierId($muster, $route, $request);
            if ($turnierId !== null) {
                (new Fuehrung($this->app->db, $this->app->instanz()))->pruefen($turnierId);
            }
        }
        return $handler->handle($request);
    }

    private function turnierId(string $muster, mixed $route, ServerRequestInterface $request): ?int
    {
        if (preg_match('#^/(turnier|anmeldung|coach/turnier)/\{id\}(/|$)#', $muster) && $route instanceof RouteInterface) {
            $id = (string) $route->getArgument('id', '');
            return ctype_digit($id) ? (int) $id : null;
        }
        if (str_starts_with($muster, '/api/v1/tisch')) {
            $tisch = Session::tisch();
            if ($tisch !== null) {
                return (int) $tisch['turnier_id'];
            }
            $tatami = (int) ($request->getQueryParams()['tatami'] ?? 0);
            $id = $this->app->db->wert('SELECT turnier_id FROM tatami WHERE id = ?', [$tatami]);
            return $id === null ? null : (int) $id;
        }
        return null;
    }
}
