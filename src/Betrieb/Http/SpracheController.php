<?php

declare(strict_types=1);

namespace Wettkampf\Betrieb\Http;

use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Cookie;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Url;
use Wettkampf\Kern\Sprache;

/**
 * Sprache umschalten (je Person, Cookie für ein Jahr) und die Übersetzungen für die Skripte (app.js: Wettkampf.t).
 */
final class SpracheController extends Controller
{
    /** Umschalten und zurück zur bisherigen Seite – nur eigene Pfade (keine offene Weiterleitung, OWASP A01). */
    public function wechseln(Request $request, string $code): Response
    {
        $code = isset(Sprache::SPRACHEN[$code]) ? $code : Sprache::STANDARD;
        Cookie::setzen(Sprache::COOKIE, $code, time() + 365 * 86400);
        return Response::weiterleiten(self::eigenerPfad($request->text('zurueck')));
    }

    /**
     * Nur ein Pfad dieser Installation (beginnt mit „/“ bzw. der Basis, kein „//“, kein Backslash), sonst die Startseite.
     *
     * @psalm-taint-escape header
     */
    private static function eigenerPfad(string $zurueck): string
    {
        $basis = Url::basis();
        $erlaubt = $zurueck !== '' && str_starts_with($zurueck, '/') && !str_starts_with($zurueck, '//') && !str_contains($zurueck, '\\')
            && !preg_match('/[\r\n]/', $zurueck) && ($basis === '' || str_starts_with($zurueck, $basis . '/') || $zurueck === $basis);
        return $erlaubt ? $zurueck : Url::zu('');
    }

    /** Katalog der aktuellen Sprache als Skript (für Texte, die erst im Browser entstehen). */
    public function texte(Request $request): Response
    {
        $sprache = (string) $request->get('s', Sprache::aktuell());
        $katalog = Sprache::katalog(isset(Sprache::SPRACHEN[$sprache]) ? $sprache : Sprache::STANDARD);
        $json = json_encode((object) $katalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
        return new Response('window.WettkampfTexte = ' . $json . ";\n", 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
