<?php

declare(strict_types=1);

namespace Wettkampf\Datenschutz\Http;

use Wettkampf\Betrieb\AutoSicherung;
use Wettkampf\Datenschutz\Buchhaltungsexport;
use Wettkampf\Datenschutz\DatenschutzEinstellungen;
use Wettkampf\Datenschutz\Fristen;
use Wettkampf\Datenschutz\Hinweise;
use Wettkampf\Datenschutz\PdfBeleg;
use Wettkampf\Datenschutz\Pseudonymisierung;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Meldung\AnmeldungService;

/**
 * Datenschutz je Turnier (Turnierleitung): Angaben für die Datenschutzhinweise, Baustein für die Ausschreibung,
 * Buchhaltungsexport, Fristen und Pseudonymisierung. Dazu die öffentlichen Datenschutzhinweise des Turniers.
 */
final class DatenschutzController extends Controller
{
    public function index(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $schluessel = (new AnmeldungService($this->db))->anmeldeSchluessel($id);
        // absolute Adresse nur aus der Konfiguration (nie aus dem Host-Header, OWASP A07), sonst relativ
        $basis = rtrim((string) ($this->app->mailKonfiguration()['oeffentliche_url'] ?? ''), '/');
        $link = preg_match('#^https?://#', $basis) ? "$basis/melden/$schluessel/datenschutz" : url("melden/$schluessel/datenschutz");
        return $this->seite('datenschutz/turnier', [
            'turnier' => $turnier,
            'e' => DatenschutzEinstellungen::lesen($turnier),
            'link' => $link,
            'ausschreibung' => (new Hinweise($this->db))->ausschreibung($turnier, $link),
            'loeschenAb' => DatenschutzEinstellungen::loeschenAb($turnier),
            'liveAusAb' => DatenschutzEinstellungen::liveAusAb($turnier),
            'exportErledigt' => $this->fristen()->exportErledigt($turnier),
            'pdf' => PdfBeleg::verfuegbar(),
            'aktiv' => 'einstellungen',
        ]);
    }

    public function speichern(Request $request, int $id): Response
    {
        $this->turnier($id);
        (new DatenschutzEinstellungen($this->db))->speichern($id, $request->post);
        $this->erfolg('Datenschutz-Angaben gespeichert.');
        return $this->weiter("turnier/$id/datenschutz");
    }

    /** ZIP mit Zahlungsliste, Belegen (PDF), versendeten Mails und Prüfsummen. */
    public function buchhaltung(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $datei = (new Buchhaltungsexport($this->db, $this->app->view))->erstellen($id);
        try {
            return Response::download((string) file_get_contents($datei), Buchhaltungsexport::downloadName($turnier), 'application/zip');
        } finally {
            @unlink($datei);
        }
    }

    public function pseudonymisieren(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        if ($request->text('bestaetigung') !== trim((string) $turnier['name'])) {
            throw new FachFehler('Zur Bestätigung bitte den Turniernamen genau so eingeben, wie er oben steht.');
        }
        if (!$this->fristen()->exportErledigt($turnier) && $request->post('ohne_export') === null) {
            throw new FachFehler('Erst den Buchhaltungsexport erstellen – oder bestätigen, dass ohne Export pseudonymisiert werden soll.');
        }
        $ergebnis = (new Pseudonymisierung($this->db))->turnier($id, $this->app->instanz()->id());
        $this->sicherungenErsetzen();
        $this->erfolg("Pseudonymisiert: {$ergebnis['starter']} Starter" . ($ergebnis['behalten'] ? ", {$ergebnis['behalten']} Platzierte mit Namen behalten" : '')
            . ", {$ergebnis['anmeldungen']} Anmeldungen, {$ergebnis['mails']} E-Mails gelöscht.");
        return $this->weiter("turnier/$id/datenschutz");
    }

    /** Öffentliche Datenschutzhinweise über den Anmeldelink. */
    public function hinweiseAnmeldung(Request $request, string $schluessel): Response
    {
        $id = (new AnmeldungService($this->db))->turnierPerSchluessel($schluessel)
            ?? throw new NichtGefunden('Diesen Anmeldelink gibt es nicht (mehr).');
        return $this->hinweise($this->turnier($id), url("melden/$schluessel"));
    }

    /** Öffentliche Datenschutzhinweise von der Statusseite (nur solange sie öffentlich ist, die Leitung immer). */
    public function hinweiseLive(Request $request, int $id): Response
    {
        $turnier = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$id]);
        if ($turnier === null || (!(int) $turnier['oeffentlich'] && !Session::leitet($id))) {
            throw new NichtGefunden('Turnier nicht gefunden.');
        }
        return $this->hinweise($turnier, url("live/$id"));
    }

    /** @param array<string, mixed> $turnier */
    private function hinweise(array $turnier, string $zurueck): Response
    {
        return Response::html($this->app->view->render('datenschutz/hinweise', [
            'turnier' => $turnier,
            'abschnitte' => (new Hinweise($this->db))->abschnitte($turnier),
            'vollstaendig' => DatenschutzEinstellungen::vollstaendig(DatenschutzEinstellungen::lesen($turnier)),
            'zurueck' => $zurueck,
        ]));
    }

    private function fristen(): Fristen
    {
        return new Fristen($this->db, $this->app->instanz()->id());
    }

    private function sicherungenErsetzen(): void
    {
        try {
            AutoSicherung::ausKonfiguration($this->db, $this->app->config, $this->app->wurzel)?->alteErsetzen();
        } catch (\Throwable $f) {
            error_log('Sicherungen nach Pseudonymisierung: ' . $f->getMessage());
        }
    }
}
