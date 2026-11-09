<?php

declare(strict_types=1);

namespace Wettkampf\Sync\Http;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Version;
use Wettkampf\Sync\HalleService;
use Wettkampf\Sync\HttpTransport;
use Wettkampf\Sync\SendeService;
use Wettkampf\Sync\Transport;

/** Hallen-Laptop: Übergabe per Datei + Code oder online abholen (Administration des Laptops). */
final class HalleController extends Controller
{
    /** In Tests ersetzbar (Aufruf einer zweiten App statt HTTP). */
    public static ?Transport $transport = null;

    public function index(Request $request): Response
    {
        $sende = new SendeService($this->db, $this->app->instanz());
        $turniere = $this->db->alle(
            'SELECT t.id, t.name, t.datum, t.uebergabe_nr, t.fuehrung_instanz, a.online_url, a.eingespielt_am, a.quelle, a.zurueckgegeben_am, a.entzogen_am
               FROM sync_auftrag a JOIN turnier t ON t.id = a.turnier_id ORDER BY a.eingespielt_am DESC',
        );
        foreach ($turniere as &$t) {
            $t['stand'] = $sende->stand((int) $t['id']);
        }
        unset($t);
        return $this->seite('sync/halle', [
            'turniere' => $turniere,
            'uebersicht' => $this->service()->uebersicht(),
            'instanzId' => $this->app->instanz()->id(),
            'version' => Version::aktuell(),
            'aktiv' => 'halle',
        ]);
    }

    public function datei(Request $request): Response
    {
        $datei = $request->files['datei'] ?? null;
        if (!is_array($datei) || (int) ($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new FachFehler('Bitte die Übergabe-Datei (.json) auswählen.');
        }
        $paket = json_decode((string) file_get_contents($datei['tmp_name']), true);
        if (!is_array($paket)) {
            throw new FachFehler('Die Datei enthält kein gültiges JSON.');
        }
        $id = $this->service()->einspielen($paket, $request->text('code'), 'datei');
        $this->erfolg('Übergabe eingespielt – dieser Laptop führt jetzt das Turnier.');
        return $this->weiter("turnier/$id");
    }

    public function abholen(Request $request): Response
    {
        $id = $this->service()->abholen(
            $request->text('online_url'),
            $request->text('email'),
            (string) $request->post('passwort', ''),
            $request->text('code'),
            self::$transport ?? new HttpTransport(),
        );
        $this->erfolg('Übergabe online abgeholt und eingespielt – dieser Laptop führt jetzt das Turnier.');
        return $this->weiter("turnier/$id");
    }

    private function service(): HalleService
    {
        return new HalleService($this->db, $this->app->instanz());
    }
}
