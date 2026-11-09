<?php

declare(strict_types=1);

namespace Wettkampf\Betrieb\Http;

use Wettkampf\Betrieb\AutoSicherung;
use Wettkampf\Betrieb\SicherungService;
use Wettkampf\Betrieb\Turnierpaket;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Controller;

final class SicherungController extends Controller
{
    public function export(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $daten = $this->service()->exportieren($id);
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', $turnier['name']) . '_' . date('Y-m-d_Hi') . '.json';
        return Response::download(Turnierpaket::kodieren($daten), $name, 'application/json; charset=utf-8');
    }

    public function importFormular(Request $request): Response
    {
        $auto = $this->auto();
        return $this->seite('sicherung/import', [
            'sqlite' => $this->db->driver() === 'sqlite',
            'grenze' => self::uploadGrenze(),
            'auto' => $auto?->liste(),
            'autoAbstand' => max(30, (int) ($this->app->config['auto_sicherung']['abstand_sekunden'] ?? 120)),
        ]);
    }

    public function import(Request $request): Response
    {
        $datei = $request->files['datei'] ?? null;
        $fehler = is_array($datei) ? (int) ($datei['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if (in_array($fehler, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new FachFehler('Die Sicherungsdatei ist größer, als der Server annimmt (max. ' . self::uploadGrenze() . '). Bitte upload_max_filesize und post_max_size in der PHP-Konfiguration erhöhen (z. B. 64M).');
        }
        if ($fehler !== UPLOAD_ERR_OK) {
            throw new FachFehler('Bitte eine Sicherungsdatei (.json) auswählen.');
        }
        $daten = json_decode((string) file_get_contents($datei['tmp_name']), true);
        if (!is_array($daten)) {
            throw new FachFehler('Die Datei enthält kein gültiges JSON.');
        }
        $ergebnis = $this->service()->importieren($daten);
        $this->erfolg($ergebnis['herkunft'] === Turnierpaket::EIGENE
            ? 'Turnier importiert (Prüfsumme dieser Installation gültig).'
            : 'Turnier importiert. Die Sicherung stammt nicht von dieser Installation'
                . ($ergebnis['herkunft'] === Turnierpaket::OHNE ? ' bzw. hat keine Prüfsumme' : '')
                . ' – Tatami-PINs und Leitungs-PIN wurden deshalb nicht übernommen. Bitte unter Einstellungen neu vergeben.');
        return $this->weiter('turnier/' . $ergebnis['turnier'] . ($ergebnis['herkunft'] === Turnierpaket::EIGENE ? '' : '/einstellungen'));
    }

    /** Download einer konsistenten Kopie der SQLite-Datenbank (Vollsicherung). */
    public function datenbank(Request $request): Response
    {
        $ziel = $this->app->wurzel . '/data/tmp/sicherung_' . bin2hex(random_bytes(6)) . '.sqlite';
        if (!is_dir(dirname($ziel))) {
            mkdir(dirname($ziel), 0775, true);
        }
        $this->service()->sqliteKopie($ziel);
        $inhalt = (string) file_get_contents($ziel);
        unlink($ziel);
        return Response::download($inhalt, 'wettkampf_' . date('Y-m-d_Hi') . '.sqlite', 'application/vnd.sqlite3');
    }

    /** Sofort einen automatischen Stand anlegen (z. B. vor einem riskanten Schritt). */
    public function autoJetzt(Request $request): Response
    {
        $name = ($this->auto() ?? throw new FachFehler('Automatische Sicherungen gibt es nur mit SQLite und wenn sie nicht abgeschaltet sind.'))->sichern();
        $this->erfolg("Sicherung $name angelegt.");
        return $this->weiter('import-turnier');
    }

    /** Ein Stand: enthaltene Turniere zum Wiederherstellen. */
    public function autoStand(Request $request, string $name): Response
    {
        $auto = $this->autoPflicht();
        $stand = array_values(array_filter($auto->liste(), static fn(array $s) => $s['name'] === $name))[0] ?? throw new FachFehler('Diese Sicherung gibt es nicht.');
        return $this->seite('sicherung/stand', ['stand' => $stand, 'turniere' => $auto->turniere($name, $this->arbeitsordner())]);
    }

    public function autoDownload(Request $request, string $name): Response
    {
        $pfad = $this->autoPflicht()->pfad($name);
        return Response::download((string) file_get_contents($pfad), 'wettkampf_' . $name, 'application/vnd.sqlite3');
    }

    /** Ein Turnier aus einem Stand als Kopie neu anlegen (der aktuelle Stand bleibt unberührt). */
    public function autoWiederherstellen(Request $request, string $name, int $turnierid): Response
    {
        $paket = $this->autoPflicht()->turnierPaket($name, $turnierid, $this->arbeitsordner(), $this->app->wurzel . '/migrations', $this->app->instanz());
        $ergebnis = $this->service()->importieren($paket);
        $this->erfolg("Turnier aus der Sicherung $name wiederhergestellt – als eigenes Turnier neben dem bisherigen. Bitte prüfen und das nicht mehr benötigte Turnier löschen bzw. archivieren.");
        return $this->weiter('turnier/' . $ergebnis['turnier']);
    }

    private function auto(): ?AutoSicherung
    {
        return AutoSicherung::ausKonfiguration($this->db, $this->app->config, $this->app->wurzel);
    }

    private function autoPflicht(): AutoSicherung
    {
        return $this->auto() ?? throw new FachFehler('Automatische Sicherungen sind hier nicht aktiv.');
    }

    private function arbeitsordner(): string
    {
        return $this->app->wurzel . '/data/tmp';
    }

    private function service(): SicherungService
    {
        return new SicherungService($this->db, $this->app->instanz());
    }

    /** Kleinere der beiden PHP-Grenzen für Uploads, z. B. „2 MB“. */
    private static function uploadGrenze(): string
    {
        $bytes = static function (string $wert): int {
            $zahl = (int) $wert;
            return match (strtolower(substr(trim($wert), -1))) {
                'g' => $zahl << 30, 'm' => $zahl << 20, 'k' => $zahl << 10, default => $zahl,
            };
        };
        $grenzen = array_filter([$bytes((string) ini_get('upload_max_filesize')), $bytes((string) ini_get('post_max_size'))]);
        return $grenzen ? round(min($grenzen) / 1048576, 1) . ' MB' : 'unbegrenzt';
    }
}
