<?php

declare(strict_types=1);

namespace Wettkampf\Turnier\Http;

use Wettkampf\Turnier\StammdatenService;
use Wettkampf\Turnier\VereinService;
use Wettkampf\Kern\Uuid;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Controller;

/** Vereine/Dojos über alle Turniere (nur Administration, siehe VereinService). */
final class VereinController extends Controller
{
    public function index(Request $request): Response
    {
        $service = new VereinService($this->db);
        $vereine = $service->uebersicht();
        return $this->seite('verein/index', [
            'vereine' => $vereine,
            'doppelt' => $service->vermutlichDoppelt($vereine),
            'leer' => count((new StammdatenService($this->db))->leereVereine()),
        ]);
    }

    /** Vereinsseite: Teilnehmer und ihre Turniere, Coach-Konten, Online-Anmeldungen. */
    public function zeigen(Request $request, int $id): Response
    {
        return $this->seite('verein/zeigen', (new VereinService($this->db))->seite($id));
    }

    /** Vereine ohne Athleten, Teams und Anmeldungen auf einmal entfernen. */
    public function leereLoeschen(Request $request): Response
    {
        $anzahl = (new StammdatenService($this->db))->leereVereineLoeschen();
        $this->erfolg($anzahl === 1 ? 'Ein leerer Verein gelöscht.' : "$anzahl leere Vereine gelöscht.");
        return $this->weiter('vereine');
    }

    public function speichern(Request $request): Response
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $request->text('name')));
        if ($name === '') {
            throw new FachFehler('Bitte einen Vereinsnamen angeben.');
        }
        $felder = ['name' => mb_substr($name, 0, 200), 'kurzname' => mb_substr($request->text('kurzname'), 0, 30) ?: null];
        $id = $request->zahl('id');
        $doppelt = $this->db->wert('SELECT id FROM verein WHERE LOWER(name) = LOWER(?) AND id <> ?', [$name, $id ?? 0]);
        if ($doppelt !== null) {
            throw new FachFehler("Ein Verein „{$name}“ existiert bereits – bei Bedarf unten zusammenführen.");
        }
        if ($id) {
            $this->db->update('verein', $felder, ['id' => $id]);
        } else {
            $this->db->insert('verein', $felder + ['uuid' => Uuid::v4()]);
        }
        $this->erfolg('Verein gespeichert.');
        return $this->weiter($request->post('zurueck') === 'zeigen' && $id ? "verein/$id" : 'vereine');
    }

    /** Vorschau vor dem Zusammenführen (ändert nichts): was zieht um, Rückfrage. */
    public function zusammenfuehrenVorschau(Request $request): Response
    {
        $vorschau = (new VereinService($this->db))->vorschau((int) $request->zahl('von'), (int) $request->zahl('nach'));
        return $this->seite('verein/zusammenfuehren', $vorschau);
    }

    /** Führt einen doppelt angelegten Verein in einen anderen über (nach der Vorschau). */
    public function zusammenfuehren(Request $request): Response
    {
        $service = new VereinService($this->db);
        $vorschau = $service->vorschau((int) $request->zahl('von'), (int) $request->zahl('nach'));
        $service->zusammenfuehren((int) $vorschau['von']['id'], (int) $vorschau['nach']['id']);
        $this->erfolg("„{$vorschau['von']['name']}“ in „{$vorschau['nach']['name']}“ zusammengeführt.");
        return $this->weiter('vereine');
    }

    public function loeschen(Request $request, int $id): Response
    {
        if (!in_array($id, (new StammdatenService($this->db))->leereVereine(), true)) {
            throw new FachFehler('Der Verein hat noch Athleten, Teams, Anmeldungen oder Konten (Coaches) und kann nicht gelöscht werden.');
        }
        $this->db->delete('verein', ['id' => $id]);
        $this->erfolg('Verein gelöscht.');
        return $this->weiter('vereine');
    }
}
