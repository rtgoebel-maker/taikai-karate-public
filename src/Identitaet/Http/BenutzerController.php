<?php

declare(strict_types=1);

namespace Wettkampf\Identitaet\Http;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Identitaet\AnmeldeSchutz;
use Wettkampf\Identitaet\Konten;
use Wettkampf\Identitaet\KontoVertrauen;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;

/**
 * Konten verwalten (nur Administration): E-Mail als Benutzername, Name, Telefon, Dojo,
 * Administration. Turnierleitungen werden je Turnier vergeben (TurnierleitungController).
 * Die Tischbesatzung braucht kein Konto – sie meldet sich mit der Tatami-PIN an.
 */
final class BenutzerController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->seite('benutzer/index', [
            'konten' => $this->konten()->alle(),
            'vereine' => $this->db->alle('SELECT id, name FROM verein ORDER BY name'),
            'ich' => Session::benutzer(),
        ]);
    }

    public function anlegen(Request $request): Response
    {
        $this->konten()->anlegen($request->post, (string) $request->post('passwort', ''), $request->post('admin') !== null);
        $this->erfolg('Konto für ' . Konten::email($request->text('email')) . ' angelegt.');
        return $this->weiter('benutzer');
    }

    public function bearbeiten(Request $request, int $id): Response
    {
        $this->konten()->aendern($id, $request->post);
        $this->erfolg('Konto gespeichert.');
        return $this->weiter('benutzer');
    }

    /**
     * Neues Passwort setzen. Für fremde Konten setzt die Administration es ohne das alte Passwort
     * zu kennen (Entscheidung 29.10.2026, z. B. vergessenes Passwort). Das eigene Passwort lässt
     * sich nur mit dem bisherigen ändern; Fehlversuche zählen im AnmeldeSchutz.
     */
    public function passwort(Request $request, int $id): Response
    {
        $konten = $this->konten();
        $neu = (string) $request->post('passwort', '');
        if ($neu !== (string) $request->post('passwort2', '')) {
            throw new FachFehler('Die beiden Eingaben des neuen Passworts stimmen nicht überein.');
        }
        Konten::passwortPruefen($neu);
        if ($id === $this->ich()) {
            $schutz = new AnmeldeSchutz($this->db);
            $ziel = 'passwort-bestaetigung:' . $id;
            $ip = $this->app->clientIp($request);
            $schutz->pruefen($ziel, $ip);
            if (!$konten->passwortStimmt($id, (string) $request->post('bisheriges_passwort', ''))) {
                $schutz->fehlschlag($ziel, $ip);
                throw new FachFehler('Das bisherige Passwort ist falsch.');
            }
            $schutz->erfolg($ziel, $ip);
        }
        $konten->passwortSetzen($id, $neu);
        $this->erfolg('Passwort geändert.');
        return $this->weiter('benutzer');
    }

    /** Authenticator-App eines Kontos zurücksetzen (Handy verloren, keine Wiederherstellungscodes mehr). */
    public function appZuruecksetzen(Request $request, int $id): Response
    {
        $this->app->authenticatorApp()->entfernen($id, 'administration');
        (new KontoVertrauen($this->db))->widerrufen($id);
        $this->erfolg('Authenticator-App zurückgesetzt – bei der nächsten Anmeldung Code per E-Mail bzw. neue Einrichtung der App.');
        return $this->weiter('benutzer');
    }

    /** Vertraute Geräte eines Kontos vergessen (z. B. Gerät verloren): beim nächsten Login wieder Code per E-Mail. */
    public function vertrauenZuruecksetzen(Request $request, int $id): Response
    {
        (new KontoVertrauen($this->db))->widerrufen($id);
        (new Protokoll($this->db))->eintragen(null, 'benutzer', $id, 'vertraute_geraete_zurueckgesetzt');
        $this->erfolg('Vertraute Geräte zurückgesetzt – beim nächsten Login ist wieder der Code nötig.');
        return $this->weiter('benutzer');
    }

    public function aktivUmschalten(Request $request, int $id): Response
    {
        $this->konten()->aktivUmschalten($id, $this->ich());
        $this->erfolg('Konto geändert.');
        return $this->weiter('benutzer');
    }

    public function loeschen(Request $request, int $id): Response
    {
        $this->konten()->loeschen($id, $this->ich(), $request->text('bestaetigung'));
        $this->erfolg('Konto endgültig gelöscht – die E-Mail-Adresse ist wieder frei.');
        return $this->weiter('benutzer');
    }

    public function adminUmschalten(Request $request, int $id): Response
    {
        $this->konten()->adminSetzen($id, $request->post('admin') === '1', $this->ich());
        $this->erfolg('Administrationsrecht geändert.');
        return $this->weiter('benutzer');
    }

    private function konten(): Konten
    {
        return new Konten($this->db);
    }

    private function ich(): int
    {
        return (int) (Session::benutzer()['id'] ?? 0);
    }
}
