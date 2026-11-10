<?php

declare(strict_types=1);

namespace Wettkampf\Meldung\Http;

use Wettkampf\Meldung\ImportService;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Meldung\TabellenLeser;
use Wettkampf\Kern\Http\Controller;

/**
 * Teilnehmer-Import: Hochladen → Vorschau → Übernehmen. Die analysierten
 * Zeilen liegen zwischen den Schritten in einer temporären Datei unter data/.
 */
final class ImportController extends Controller
{
    public function formular(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        return $this->seite('import/formular', ['turnier' => $turnier, 'kopf' => ImportService::VORLAGE_KOPF, 'aktiv' => 'teilnehmer']);
    }

    public function vorlage(Request $request, int $id): Response
    {
        return Response::download(ImportService::vorlageCsv(), 'Meldeliste-Vorlage.csv', 'text/csv; charset=utf-8');
    }

    public function hochladen(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $datei = $request->files['datei'] ?? null;
        if (!is_array($datei) || ($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new FachFehler('Bitte eine Datei auswählen.');
        }
        if ($datei['size'] > 5 * 1024 * 1024) {
            throw new FachFehler('Die Datei ist zu groß (max. 5 MB).');
        }
        $zeilen = TabellenLeser::lesen($datei['tmp_name'], (string) $datei['name']);
        $analyse = (new ImportService($this->db))->analysieren($id, $zeilen);

        $token = bin2hex(random_bytes(12));
        file_put_contents($this->tempPfad($token), json_encode($analyse['zeilen'], JSON_UNESCAPED_UNICODE));
        $this->aufraeumen();

        return $this->seite('import/vorschau', [
            'turnier' => $turnier,
            'analyse' => $analyse,
            'token' => $token,
            'dateiname' => $datei['name'],
            'aktiv' => 'teilnehmer',
        ]);
    }

    public function uebernehmen(Request $request, int $id): Response
    {
        $this->turnier($id);
        $token = $request->text('token');
        $pfad = preg_match('/^[a-f0-9]{24}$/', $token) === 1 ? $this->tempPfad($token) : '';
        if ($pfad === '' || !is_file($pfad)) {
            throw new FachFehler('Die Import-Vorschau ist abgelaufen. Bitte die Datei erneut hochladen.');
        }
        $zeilen = json_decode((string) file_get_contents($pfad), true);
        unlink($pfad);
        $ergebnis = (new ImportService($this->db))->uebernehmen($id, is_array($zeilen) ? $zeilen : []);
        $this->erfolg(sprintf(
            '%d Teilnehmer importiert, %d übersprungen, %d Teams.%s',
            $ergebnis['angelegt'],
            $ergebnis['uebersprungen'],
            $ergebnis['teams'],
            $ergebnis['hinweise'] ? ' ' . count($ergebnis['hinweise']) . ' Zuordnungshinweise – siehe Übersicht.' : '',
        ));
        return $this->weiter("turnier/$id/teilnehmer");
    }

    /**
     * Token ist nur hexadezimal (sonst Ausnahme) – kein Pfadwechsel, keine fremde Adresse möglich.
     *
     * @psalm-taint-escape file
     * @psalm-taint-escape ssrf
     */
    private function tempPfad(string $token): string
    {
        if (preg_match('/^[a-f0-9]{24}$/', $token) !== 1) {
            throw new FachFehler('Die Import-Vorschau ist abgelaufen. Bitte die Datei erneut hochladen.');
        }
        $verzeichnis = $this->app->wurzel . '/data/tmp';
        if (!is_dir($verzeichnis)) {
            mkdir($verzeichnis, 0775, true);
        }
        return "$verzeichnis/import_$token.json";
    }

    /** Entfernt liegengebliebene Vorschau-Dateien (älter als 1 Tag). */
    private function aufraeumen(): void
    {
        foreach (glob($this->app->wurzel . '/data/tmp/import_*.json') ?: [] as $datei) {
            if (filemtime($datei) < time() - 86400) {
                @unlink($datei);
            }
        }
    }
}
