<?php

declare(strict_types=1);

namespace Wettkampf\Identitaet\Http;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Identitaet\Konten;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;

/**
 * Turnierleitung eines Turniers: Administration und jede Turnierleitung dieses Turniers
 * können weitere Turnierleitungen ernennen – ein bestehendes Konto per E-Mail oder ein neues Konto.
 */
final class TurnierleitungController extends Controller
{
    public function index(Request $request, int $id): Response
    {
        return $this->seite('turnier/leitung', [
            'turnier' => $this->turnier($id),
            'leitung' => (new Konten($this->db))->turnierleitung($id),
            'ich' => Session::benutzer(),
            'istAdmin' => Session::istAdmin(),
            'aktiv' => 'einstellungen',
        ]);
    }

    public function hinzufuegen(Request $request, int $id): Response
    {
        $this->turnier($id);
        $konten = new Konten($this->db);
        if ($request->post('neu') !== null) {
            // neues Konto (ohne Administration) anlegen und sofort ernennen
            $benutzerId = $konten->anlegen($request->post, (string) $request->post('passwort', ''), false);
        } else {
            $konto = $konten->perEmail($request->text('email'))
                ?? throw new FachFehler('Es gibt kein aktives Konto mit dieser E-Mail-Adresse. Unten kann ein neues Konto angelegt werden.');
            $benutzerId = (int) $konto['id'];
        }
        $konten->turnierleitungHinzufuegen($id, $benutzerId, Session::akteur());
        $this->erfolg('Zur Turnierleitung ernannt.');
        return $this->weiter("turnier/$id/leitung");
    }

    public function entfernen(Request $request, int $id, int $benutzerid): Response
    {
        $this->turnier($id);
        (new Konten($this->db))->turnierleitungEntfernen($id, $benutzerid, (int) (Session::benutzer()['id'] ?? 0), Session::istAdmin());
        $this->erfolg('Turnierleitung entzogen.');
        return $this->weiter("turnier/$id/leitung");
    }
}
