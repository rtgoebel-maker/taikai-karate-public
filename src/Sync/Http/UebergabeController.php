<?php

declare(strict_types=1);

namespace Wettkampf\Sync\Http;

use Wettkampf\Betrieb\Turnierpaket;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Url;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Version;
use Wettkampf\Sync\HttpTransport;
use Wettkampf\Sync\RueckgabeService;
use Wettkampf\Sync\UebergabeService;

/**
 * Online: Turnier an einen Hallen-Laptop übergeben (Turnierleitung), Übergabe-Datei, zurücknehmen;
 * dazu der Abruf durch den Laptop (POST /api/v1/sync/uebergabe, Zugang 'maschine').
 */
final class UebergabeController extends Controller
{
    public function anzeigen(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $service = $this->service();
        return $this->seite('sync/turnier', [
            'turnier' => $turnier,
            'laufend' => $service->laufende($id),
            'auftrag' => $this->db->eine('SELECT * FROM sync_auftrag WHERE turnier_id = ?', [$id]),
            'code' => Session::gemerkt("uebergabe:$id"),
            'onlineUrl' => self::onlineUrl($request),
            'selbst' => ($turnier['fuehrung_instanz'] ?? null) !== null && hash_equals($this->app->instanz()->id(), (string) $turnier['fuehrung_instanz']),
            'version' => Version::aktuell(),
            'aktiv' => 'einstellungen',
        ]);
    }

    public function uebergeben(Request $request, int $id): Response
    {
        $this->turnier($id);
        $ergebnis = $this->service()->uebergeben($id, Session::akteur());
        Session::merken("uebergabe:$id", $ergebnis['code']);
        $this->erfolg("Übergabe Nr. {$ergebnis['nr']} angelegt – das Turnier ist hier jetzt nur lesend. Code notieren und Datei herunterladen bzw. am Laptop abholen.");
        return $this->weiter("turnier/$id/halle");
    }

    public function datei(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $code = Session::gemerkt("uebergabe:$id")
            ?? throw new FachFehler('Die Übergabe-Datei gibt es nur direkt nach dem Übergeben in derselben Sitzung. Sonst Übergabe zurücknehmen und neu übergeben – oder am Laptop online abholen.');
        $paket = $this->service()->paket($id, $code, self::onlineUrl($request));
        $name = 'Uebergabe_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $turnier['name']) . '_Nr' . $paket['uebergabe']['nr'] . '.json';
        return Response::download(Turnierpaket::kodieren($paket), $name, 'application/json; charset=utf-8');
    }

    public function zuruecknehmen(Request $request, int $id): Response
    {
        $this->turnier($id);
        $this->service()->zuruecknehmen($id, Session::akteur());
        Session::merken("uebergabe:$id", null);
        $this->erfolg('Übergabe zurückgenommen – das Turnier wird wieder hier geführt. Sendungen der bisherigen Halle werden abgewiesen.');
        return $this->weiter("turnier/$id/halle");
    }

    /** Hallen-Laptop: Turnier an Online zurückgeben (Schritt 9h). */
    public function zurueckgeben(Request $request, int $id): Response
    {
        $this->turnier($id);
        (new RueckgabeService($this->db, $this->app->instanz()))->zurueckgeben($id, HalleController::$transport ?? new HttpTransport());
        $this->erfolg('Turnier an Online zurückgegeben – dort wird jetzt weitergearbeitet. Dieser Laptop ist nur noch lesend; bei Bedarf Sicherung herunterladen und das Turnier hier löschen.');
        return $this->weiter("turnier/$id/halle");
    }

    /** Hallen-Laptop: zurückgegebenes Turnier hier löschen (E10). */
    public function loeschen(Request $request, int $id): Response
    {
        $this->turnier($id);
        (new RueckgabeService($this->db, $this->app->instanz()))->loeschen($id);
        $this->erfolg('Turnier auf diesem Laptop gelöscht.');
        return $this->weiter('halle');
    }

    /** Laptop holt das Paket ab: {email, passwort, code} → Übergabe-Paket (JSON). */
    public function abholen(Request $request): Response
    {
        $daten = $request->json();
        $paket = $this->service()->abholen(
            (string) ($daten['email'] ?? ''),
            (string) ($daten['passwort'] ?? ''),
            (string) ($daten['code'] ?? ''),
            $this->app->clientIp($request),
            self::onlineUrl($request),
        );
        return new Response(Turnierpaket::kodieren($paket), 200, ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    private function service(): UebergabeService
    {
        return new UebergabeService($this->db, $this->app->instanz());
    }

    /** Adresse dieser Installation, wie der Laptop sie aufruft (für die Übergabe-Datei). */
    private static function onlineUrl(Request $request): string
    {
        $https = strtolower((string) $request->header('x-forwarded-proto')) === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $host = (string) preg_replace('/[^A-Za-z0-9.:\[\]-]/', '', (string) ($request->header('host') ?? 'localhost'));
        return ($https ? 'https' : 'http') . '://' . $host . Url::basis();
    }
}
