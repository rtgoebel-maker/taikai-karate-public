<?php

declare(strict_types=1);

namespace Wettkampf\Sync;

use Throwable;
use Wettkampf\Betrieb\SicherungService;
use Wettkampf\Betrieb\Turnierpaket;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Instanz;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Kern\Uuid;
use Wettkampf\Kern\Version;

/**
 * Halle sendet den Gesamtstand an Online (Schritt 9e, Entscheidung E7).
 *
 * Gesendet wird nur, wenn diese Installation das Turnier führt und sich die Revision seit der letzten bestätigten
 * Sendung geändert hat – höchstens alle ABSTAND_SEKUNDEN. Jede Sendung ersetzt die vorige vollständig, doppelte oder
 * verlorene Sendungen schaden nicht. Ohne Netz läuft die Halle ungestört weiter und sendet später.
 * Ausgelöst wird das Senden vom Hintergrundprozess bin/sync.php, nie aus einer Seitenanfrage: der PHP-Server des
 * Laptops bearbeitet Anfragen nacheinander, ein wartender Aufruf würde sonst die Tablets anhalten.
 */
final class SendeService
{
    public const PFAD = '/api/v1/sync/stand';
    public const FORMAT = 'wettkampf-hallenstand';
    public const ABSTAND_SEKUNDEN = 20;
    /** Neue Korrekturanträge abfragen, auch wenn sich in der Halle nichts ändert (E8). */
    public const ABFRAGE_SEKUNDEN = 30;

    public const GESENDET = 'gesendet';
    public const AKTUELL = 'aktuell';
    public const WARTET = 'wartet';
    public const FEHLER = 'fehler';
    public const FUEHRT_NICHT = 'fuehrt_nicht';
    public const ENTZOGEN = 'entzogen';

    public function __construct(private readonly Database $db, private readonly Instanz $instanz) {}

    /** @return array<int, string> Turnier-ID => Ergebnis, für alle Turniere mit Sync-Auftrag */
    public function alleSenden(Transport $transport, ?int $jetzt = null): array
    {
        $ergebnisse = [];
        foreach ($this->db->spalte('SELECT turnier_id FROM sync_auftrag ORDER BY turnier_id') as $id) {
            $ergebnisse[(int) $id] = $this->senden((int) $id, $transport, $jetzt);
            if (!in_array($ergebnisse[(int) $id], [self::FUEHRT_NICHT, self::ENTZOGEN], true)) {
                $this->antraegeAbfragen((int) $id, $transport, $jetzt);
            }
        }
        return $ergebnisse;
    }

    public function senden(int $turnierId, Transport $transport, ?int $jetzt = null): string
    {
        $jetzt ??= time();
        $auftrag = $this->db->eine('SELECT a.*, t.revision, t.uuid, t.fuehrung_instanz FROM sync_auftrag a JOIN turnier t ON t.id = a.turnier_id WHERE a.turnier_id = ?', [$turnierId]);
        if ($auftrag === null || !hash_equals($this->instanz->id(), (string) $auftrag['fuehrung_instanz'])) {
            return self::FUEHRT_NICHT;
        }
        if ($auftrag['bestaetigte_revision'] !== null && (int) $auftrag['bestaetigte_revision'] === (int) $auftrag['revision']) {
            return self::AKTUELL;
        }
        // Senderecht für dieses Zeitfenster beanspruchen (atomar: mehrere Auslöser senden nicht doppelt)
        $grenze = date('Y-m-d H:i:s', $jetzt - self::ABSTAND_SEKUNDEN);
        $beansprucht = $this->db->query(
            'UPDATE sync_auftrag SET letzter_versuch = ? WHERE id = ? AND (letzter_versuch IS NULL OR letzter_versuch <= ?)',
            [date('Y-m-d H:i:s', $jetzt), (int) $auftrag['id'], $grenze],
        )->rowCount();
        if ($beansprucht === 0) {
            return self::WARTET;
        }

        [$revision, $inhalt] = $this->sendung($turnierId, $auftrag, $jetzt);
        $kopf = Signatur::kopf((string) $auftrag['schluessel'], $this->instanz->id(), self::PFAD, $inhalt, $jetzt) + ['Content-Type' => 'application/gzip'];
        try {
            $antwort = $transport->post(rtrim((string) $auftrag['online_url'], '/') . self::PFAD, $inhalt, $kopf);
            $daten = json_decode($antwort['body'], true);
            if (self::entzogen($antwort, $daten)) {
                $this->entziehen($auftrag, $jetzt);
                return self::ENTZOGEN;
            }
            if ($antwort['status'] !== 200 || !is_array($daten) || !is_int($daten['revision'] ?? null)) {
                $text = is_array($daten) && is_string($daten['fehler'] ?? null) ? $daten['fehler'] : 'Antwort ' . $antwort['status'];
                throw new FachFehler('Online hat den Stand nicht angenommen: ' . $text);
            }
        } catch (Throwable $e) {
            $this->db->update('sync_auftrag', ['gesendete_revision' => $revision, 'letzter_fehler' => mb_substr($e->getMessage(), 0, 300)], ['id' => (int) $auftrag['id']]);
            return self::FEHLER;
        }
        $this->db->update('sync_auftrag', [
            'gesendete_revision' => $revision, 'bestaetigte_revision' => min($revision, $daten['revision']),
            'letzter_kontakt' => date('Y-m-d H:i:s', $jetzt), 'letzter_fehler' => null,
        ], ['id' => (int) $auftrag['id']]);
        return self::GESENDET;
    }

    /**
     * Sendung: Hallenstand mit Kopf, als JSON komprimiert. Stand und Revision in einer Transaktion gelesen.
     *
     * @param array<string, mixed> $auftrag
     * @return array{0: int, 1: string} Revision, Inhalt
     */
    private function sendung(int $turnierId, array $auftrag, int $jetzt): array
    {
        return $this->db->transaktion(function () use ($turnierId, $auftrag, $jetzt) {
            $sicherung = (new SicherungService($this->db))->exportieren($turnierId);
            $revision = (int) $sicherung['turnier']['revision'];
            $json = json_encode([
                'format' => self::FORMAT,
                'version' => 1,
                'turnier' => (string) $auftrag['uuid'],
                'uebergabe_nr' => (int) $auftrag['uebergabe_nr'],
                'instanz' => $this->instanz->id(),
                'protokoll' => Version::SYNC_PROTOKOLL, 'programm' => Version::aktuell(),
                'revision' => $revision,
                'gesendet_am' => date('Y-m-d H:i:s', $jetzt),
                'stand' => Turnierpaket::hallenstand($sicherung),
            ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
            return [$revision, (string) gzencode($json, 6)];
        });
    }

    /**
     * Neue, online gestellte Korrekturanträge holen und hier anlegen (Schritt 9g, E8). Die Entscheidung der Halle geht
     * mit dem nächsten Gesamtstand zurück.
     *
     * @return int Anzahl neuer Anträge (−1: nicht abgefragt bzw. Fehler)
     */
    public function antraegeAbfragen(int $turnierId, Transport $transport, ?int $jetzt = null): int
    {
        $jetzt ??= time();
        $auftrag = $this->db->eine('SELECT a.*, t.uuid, t.fuehrung_instanz FROM sync_auftrag a JOIN turnier t ON t.id = a.turnier_id WHERE a.turnier_id = ?', [$turnierId]);
        if ($auftrag === null || !hash_equals($this->instanz->id(), (string) $auftrag['fuehrung_instanz'])) {
            return -1;
        }
        $beansprucht = $this->db->query(
            'UPDATE sync_auftrag SET letzte_abfrage = ? WHERE id = ? AND (letzte_abfrage IS NULL OR letzte_abfrage <= ?)',
            [date('Y-m-d H:i:s', $jetzt), (int) $auftrag['id'], date('Y-m-d H:i:s', $jetzt - self::ABFRAGE_SEKUNDEN)],
        )->rowCount();
        if ($beansprucht === 0) {
            return -1;
        }
        $inhalt = (string) json_encode([
            'format' => EmpfangService::FORMAT_ANTRAEGE, 'turnier' => (string) $auftrag['uuid'], 'uebergabe_nr' => (int) $auftrag['uebergabe_nr'],
            'instanz' => $this->instanz->id(), 'protokoll' => Version::SYNC_PROTOKOLL, 'programm' => Version::aktuell(), 'bekannt' => $this->db->spalte('SELECT uuid FROM korrekturantrag WHERE turnier_id = ?', [$turnierId]),
        ]);
        $kopf = Signatur::kopf((string) $auftrag['schluessel'], $this->instanz->id(), EmpfangService::PFAD_ANTRAEGE, $inhalt, $jetzt);
        try {
            $antwort = $transport->post(rtrim((string) $auftrag['online_url'], '/') . EmpfangService::PFAD_ANTRAEGE, $inhalt, $kopf);
        } catch (Throwable) {
            return -1; // ohne Netz: beim nächsten Mal
        }
        $daten = json_decode($antwort['body'], true);
        if (self::entzogen($antwort, $daten)) {
            $this->entziehen($auftrag, $jetzt);
            return -1;
        }
        if ($antwort['status'] !== 200 || !is_array($daten) || !is_array($daten['antraege'] ?? null)) {
            return -1;
        }
        return $this->db->transaktion(function () use ($turnierId, $daten) {
            $neu = 0;
            foreach ($daten['antraege'] as $a) {
                if (!is_array($a) || !is_string($a['uuid'] ?? null) || $this->db->wert('SELECT id FROM korrekturantrag WHERE uuid = ?', [$a['uuid']]) !== null) {
                    continue;
                }
                $id = $this->db->insert('korrekturantrag', ['turnier_id' => $turnierId, 'verein_id' => $this->verein($a), 'teilnahme_id' => $this->teilnahme($turnierId, $a)] + self::antragFelder($a));
                (new Protokoll($this->db))->eintragen($turnierId, 'korrekturantrag', $id, 'abgeholt', ['von' => $a['erstellt_von'] ?? null]);
                $neu++;
            }
            return $neu;
        });
    }

    /**
     * Felder eines Antrags aus der Online-Antwort (nur bekannte, als Text).
     *
     * @param array<string, mixed> $a
     * @return array<string, string|null>
     */
    private static function antragFelder(array $a): array
    {
        $felder = ['uuid' => 36, 'art' => 20, 'neu' => 200, 'begruendung' => 500, 'status' => 20, 'antwort' => 500,
            'erstellt_am' => 19, 'erstellt_von' => 100, 'bearbeitet_am' => 19, 'bearbeitet_von' => 100];
        $ergebnis = [];
        foreach ($felder as $feld => $laenge) {
            $ergebnis[$feld] = is_scalar($a[$feld] ?? null) ? mb_substr((string) $a[$feld], 0, $laenge) : null;
        }
        $ergebnis['status'] ??= 'offen';
        $ergebnis['erstellt_am'] ??= date('Y-m-d H:i:s');
        $ergebnis['erstellt_von'] ??= 'Online';
        $ergebnis['art'] ??= '';
        $ergebnis['neu'] ??= '';
        $ergebnis['begruendung'] ??= '';
        return $ergebnis;
    }

    /** @param array<string, mixed> $a */
    private function verein(array $a): int
    {
        $id = $this->db->wert('SELECT id FROM verein WHERE uuid = ?', [(string) ($a['verein_uuid'] ?? '')])
            ?? $this->db->wert('SELECT id FROM verein WHERE LOWER(name) = LOWER(?)', [(string) ($a['verein_name'] ?? '')]);
        return $id !== null ? (int) $id : $this->db->insert('verein', [
            'uuid' => is_string($a['verein_uuid'] ?? null) ? $a['verein_uuid'] : Uuid::v4(),
            'name' => mb_substr((string) ($a['verein_name'] ?? 'Verein'), 0, 100),
        ]);
    }

    /** @param array<string, mixed> $a */
    private function teilnahme(int $turnierId, array $a): ?int
    {
        $id = is_string($a['teilnahme_uuid'] ?? null) ? $this->db->wert('SELECT id FROM teilnahme WHERE uuid = ? AND turnier_id = ?', [$a['teilnahme_uuid'], $turnierId]) : null;
        return $id === null ? null : (int) $id;
    }

    /**
     * Online führt diese Übergabe nicht mehr (zurückgenommen, neu übergeben oder ein anderer Laptop führt).
     *
     * @param array{status: int, body: string} $antwort
     */
    private static function entzogen(array $antwort, mixed $daten): bool
    {
        return $antwort['status'] === 409 && is_array($daten) && in_array($daten['grund'] ?? null, ['uebergabe', 'andere_halle'], true);
    }

    /**
     * Führung entzogen: hier nur noch lesend (Konzept Kap. 4.4); die Daten bleiben für eine Sicherung erhalten.
     *
     * @param array<string, mixed> $auftrag
     */
    private function entziehen(array $auftrag, int $jetzt): void
    {
        $zeit = date('Y-m-d H:i:s', $jetzt);
        $this->db->update('turnier', ['fuehrung_instanz' => Uuid::v4(), 'fuehrung_name' => 'Online (Führung entzogen)', 'fuehrung_seit' => $zeit], ['id' => (int) $auftrag['turnier_id']]);
        $this->db->update('sync_auftrag', ['entzogen_am' => $zeit, 'letzter_fehler' => 'Online hat die Führung zurückgeholt – dieser Laptop ist nur noch lesend.'], ['id' => (int) $auftrag['id']]);
        (new Protokoll($this->db))->eintragen((int) $auftrag['turnier_id'], 'turnier', (int) $auftrag['turnier_id'], 'fuehrung_entzogen', ['nr' => (int) $auftrag['uebergabe_nr']]);
    }

    /**
     * Stand für die Anzeige am Laptop; null ohne Sync-Auftrag.
     *
     * @return array{revision: int, bestaetigt: int|null, kontakt: string|null, versuch: string|null, fehler: string|null, laeuft: bool}|null
     */
    public function stand(int $turnierId, ?int $jetzt = null): ?array
    {
        $a = $this->db->eine('SELECT a.*, t.revision FROM sync_auftrag a JOIN turnier t ON t.id = a.turnier_id WHERE a.turnier_id = ?', [$turnierId]);
        if ($a === null) {
            return null;
        }
        $versuch = $a['letzter_versuch'] !== null ? (int) strtotime((string) $a['letzter_versuch']) : null;
        return [
            'revision' => (int) $a['revision'],
            'bestaetigt' => $a['bestaetigte_revision'] !== null ? (int) $a['bestaetigte_revision'] : null,
            'kontakt' => $a['letzter_kontakt'],
            'versuch' => $a['letzter_versuch'],
            'fehler' => $a['letzter_fehler'],
            // Hintergrundprozess aktiv? Er versucht es spätestens alle ABSTAND_SEKUNDEN, solange etwas offen ist.
            'laeuft' => $versuch !== null && ($jetzt ?? time()) - $versuch < 6 * self::ABSTAND_SEKUNDEN,
        ];
    }
}
