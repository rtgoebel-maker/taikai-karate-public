<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung\Http;

use Wettkampf\Durchfuehrung\GeraeteKopplung;
use Wettkampf\Kern\QrCode;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Url;
use Wettkampf\Kern\Protokoll;

/**
 * Leitung: QR-Codes je Tatami und Rolle zeigen/drucken, wartende Geräte freigeben, gekoppelte Geräte sperren,
 * PIN-Anmeldung als Notlösung ein-/ausschalten.
 */
final class GeraeteController extends Controller
{
    public function index(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        [$basis, $adressen] = $this->basisAdresse($request);
        return $this->seite('turnier/geraete', [
            'turnier' => $turnier,
            'qr' => $this->qrCodes($id, $basis),
            'geraete' => $this->app->geraeteKopplung()->liste($id),
            'basis' => $basis,
            'adressen' => $adressen,
            'aktiv' => 'einstellungen',
        ]);
    }

    /** Stand der Geräteliste für das automatische Neuladen der Seite (neue Kopplungsanfragen sofort sehen). */
    public function stand(Request $request, int $id): Response
    {
        $this->turnier($id);
        return Response::json(['revision' => $this->app->geraeteKopplung()->stand($id)]);
    }

    /** Druckblatt: je Tatami die beiden QR-Codes groß zum Aushängen bzw. Auslegen am Tisch. */
    public function druck(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        [$basis] = $this->basisAdresse($request);
        return Response::html($this->app->view->render('turnier/geraete_druck', [
            'turnier' => $turnier,
            'qr' => $this->qrCodes($id, $basis),
        ]));
    }

    public function freigeben(Request $request, int $id, int $geraetid): Response
    {
        $this->turnier($id);
        $this->app->geraeteKopplung()->freigeben($id, $geraetid, Session::akteur());
        $this->erfolg('Gerät freigegeben – es meldet sich in wenigen Sekunden selbst an.');
        return $this->weiter("turnier/$id/geraete");
    }

    public function sperren(Request $request, int $id, int $geraetid): Response
    {
        $this->turnier($id);
        $this->app->geraeteKopplung()->sperren($id, $geraetid);
        $this->erfolg('Gerät abgemeldet und gesperrt.');
        return $this->weiter("turnier/$id/geraete");
    }

    public function aufraeumen(Request $request, int $id): Response
    {
        $this->turnier($id);
        $this->app->geraeteKopplung()->gesperrteEntfernen($id);
        return $this->weiter("turnier/$id/geraete");
    }

    public function qrErneuern(Request $request, int $id): Response
    {
        $this->turnier($id);
        $this->app->geraeteKopplung()->qrErneuern($id, (int) $request->zahl('tatami_id'));
        $this->erfolg('Neue QR-Codes erzeugt – die alten (auch ausgedruckte) gelten nicht mehr. Gekoppelte Geräte bleiben angemeldet.');
        return $this->weiter("turnier/$id/geraete");
    }

    /** PIN-Anmeldung am Tisch erlauben (Notlösung) oder abschalten – abgeschaltet werden PIN-Sitzungen sofort beendet. */
    public function pin(Request $request, int $id): Response
    {
        $this->turnier($id);
        $erlaubt = $request->post('erlaubt') !== null;
        $this->db->update('turnier', ['tisch_pin_erlaubt' => $erlaubt ? 1 : 0], ['id' => $id]);
        (new Protokoll($this->db))->eintragen($id, 'turnier', $id, 'tisch_pin', ['erlaubt' => $erlaubt]);
        $this->erfolg($erlaubt ? 'Anmeldung per PIN ist wieder möglich.' : 'Anmeldung per PIN abgeschaltet – Geräte nur noch per QR-Code mit Freigabe.');
        return $this->weiter("turnier/$id/geraete");
    }

    /**
     * QR-Codes aller Tatamis als SVG (vom Server erzeugt, kein externer Dienst).
     *
     * @return list<array{id: int, bezeichnung: string, codes: array<string, array{url: string, svg: string}>}>
     */
    private function qrCodes(int $turnierId, string $basis): array
    {
        $kopplung = $this->app->geraeteKopplung();
        $liste = [];
        foreach ($this->db->alle('SELECT id, bezeichnung FROM tatami WHERE turnier_id = ? ORDER BY nummer', [$turnierId]) as $t) {
            $codes = [];
            foreach (array_keys(GeraeteKopplung::ROLLEN) as $rolle) {
                $url = $basis . '/tisch/koppeln/' . $t['id'] . '/' . $rolle . '/' . $kopplung->qrToken((int) $t['id'], $rolle);
                $codes[$rolle] = ['url' => $url, 'svg' => QrCode::svg($url)];
            }
            $liste[] = ['id' => (int) $t['id'], 'bezeichnung' => (string) $t['bezeichnung'], 'codes' => $codes];
        }
        return $liste;
    }

    /**
     * Adresse, unter der die Geräte diesen Server erreichen. Ruft die Leitung die Seite über localhost auf (Hallen-Laptop),
     * taugt das für Tablets nicht – dann die Adressen im lokalen Netz anbieten (Auswahl nur aus den ermittelten, OWASP A03).
     *
     * @return array{0: string, 1: list<string>} Basis-URL ohne Schrägstrich am Ende, Auswahl im LAN
     */
    private function basisAdresse(Request $request): array
    {
        // HTTP_HOST enthält den Port (Slim reicht den Host-Header ohne Port weiter)
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $request->header('host') ?? 'localhost'));
        if (!preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$|^\[[0-9a-f:]+\](:\d{1,5})?$/', $host)) {
            $host = 'localhost';
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string) $request->header('x-forwarded-proto')) === 'https';
        $name = (string) preg_replace('/:\d+$/', '', $host);
        $port = preg_match('/:(\d+)$/', $host, $p) ? ':' . $p[1] : '';
        $adressen = [];
        if (in_array($name, ['localhost', '127.0.0.1', '[::1]'], true)) {
            foreach (gethostbynamel((string) gethostname()) ?: [] as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !str_starts_with($ip, '127.')) {
                    $adressen[] = 'http://' . $ip . $port . Url::basis();
                }
            }
            $gewaehlt = $request->text('adresse');
            $basis = in_array($gewaehlt, $adressen, true) ? $gewaehlt : ($adressen[0] ?? 'http://' . $host . Url::basis());
            return [$basis, $adressen];
        }
        return [($https ? 'https://' : 'http://') . $host . Url::basis(), []];
    }
}
