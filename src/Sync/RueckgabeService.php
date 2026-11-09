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
use Wettkampf\Turnier\StammdatenService;

/**
 * Hallen-Laptop gibt das Turnier an Online zurück (Schritt 9h): letzter Stand, Logos/Unterschriften und Geburtsdaten
 * der in der Halle angelegten Athleten. Danach führt Online, der Laptop ist nur lesend (E10) und kann das Turnier
 * löschen. Läuft bewusst in der Seitenanfrage: einmaliger Schritt am Ende, die Leitung wartet auf die Bestätigung.
 */
final class RueckgabeService
{
    public function __construct(private readonly Database $db, private readonly Instanz $instanz) {}

    public function zurueckgeben(int $turnierId, Transport $transport, ?int $jetzt = null): void
    {
        $jetzt ??= time();
        $auftrag = $this->auftrag($turnierId);
        if (!hash_equals($this->instanz->id(), (string) $auftrag['fuehrung_instanz'])) {
            throw new FachFehler('Dieser Laptop führt das Turnier nicht (mehr) – zurückgeben ist nicht möglich.');
        }
        $sicherung = (new SicherungService($this->db))->exportieren($turnierId);
        $mitgebracht = array_flip(json_decode((string) ($auftrag['athleten_bei_uebergabe'] ?? '[]'), true) ?: []);
        $details = [];
        foreach ($sicherung['athlet'] as $a) {
            if (!isset($mitgebracht[$a['uuid']])) {
                $details[] = ['uuid' => $a['uuid'], 'geburtsdatum' => $a['geburtsdatum'], 'ausweis_nr' => $a['ausweis_nr']];
            }
        }
        $inhalt = (string) gzencode(json_encode([
            'format' => EmpfangService::FORMAT_RUECKGABE,
            'version' => 1,
            'turnier' => (string) $auftrag['uuid'],
            'uebergabe_nr' => (int) $auftrag['uebergabe_nr'],
            'instanz' => $this->instanz->id(),
            'protokoll' => Version::SYNC_PROTOKOLL, 'programm' => Version::aktuell(),
            'revision' => (int) $sicherung['turnier']['revision'],
            'gesendet_am' => date('Y-m-d H:i:s', $jetzt),
            'stand' => Turnierpaket::hallenstand($sicherung),
            'logos' => array_map(static fn(array $l) => array_intersect_key($l, array_flip(['art', 'mime', 'daten', 'breite', 'hoehe', 'geaendert_am'])), $sicherung['turnier_logo']),
            'athlet_details' => $details,
        ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR), 6);
        $kopf = Signatur::kopf((string) $auftrag['schluessel'], $this->instanz->id(), EmpfangService::PFAD_RUECKGABE, $inhalt, $jetzt)
            + ['Content-Type' => 'application/gzip'];

        try {
            $antwort = $transport->post(rtrim((string) $auftrag['online_url'], '/') . EmpfangService::PFAD_RUECKGABE, $inhalt, $kopf);
        } catch (Throwable $e) {
            throw new FachFehler('Rückgabe nicht möglich: ' . $e->getMessage() . ' Der Laptop führt weiter; später erneut versuchen.');
        }
        $daten = json_decode($antwort['body'], true);
        if ($antwort['status'] !== 200 || ($daten['ok'] ?? null) !== true) {
            $text = is_array($daten) && is_string($daten['fehler'] ?? null) ? $daten['fehler'] : 'Antwort ' . $antwort['status'];
            throw new FachFehler('Online hat die Rückgabe nicht angenommen: ' . mb_substr($text, 0, 300) . ($antwort['status'] === 413 || $antwort['status'] === 400
                ? ' (Bei großen Logos ggf. post_max_size auf dem Server erhöhen.)' : ''));
        }

        $zeit = date('Y-m-d H:i:s', $jetzt);
        $this->db->transaktion(function () use ($turnierId, $auftrag, $zeit) {
            $this->db->update('turnier', ['fuehrung_instanz' => Uuid::v4(), 'fuehrung_name' => 'Online', 'fuehrung_seit' => $zeit], ['id' => $turnierId]);
            $this->db->update('sync_auftrag', ['zurueckgegeben_am' => $zeit, 'letzter_fehler' => null, 'letzter_kontakt' => $zeit], ['id' => (int) $auftrag['id']]);
            (new Protokoll($this->db))->eintragen($turnierId, 'turnier', $turnierId, 'zurueckgegeben', ['nr' => (int) $auftrag['uebergabe_nr']]);
        });
    }

    /** Turnier vom Laptop löschen – nur wenn er es nicht mehr führt (zurückgegeben bzw. Führung entzogen). */
    public function loeschen(int $turnierId): void
    {
        $auftrag = $this->auftrag($turnierId);
        if (hash_equals($this->instanz->id(), (string) $auftrag['fuehrung_instanz'])) {
            throw new FachFehler('Erst an Online zurückgeben – dieser Laptop führt das Turnier noch.');
        }
        (new StammdatenService($this->db))->turnierLoeschen($turnierId);
    }

    /** @return array<string, mixed> */
    private function auftrag(int $turnierId): array
    {
        return $this->db->eine('SELECT a.*, t.uuid, t.fuehrung_instanz FROM sync_auftrag a JOIN turnier t ON t.id = a.turnier_id WHERE a.turnier_id = ?', [$turnierId])
            ?? throw new FachFehler('Dieses Turnier wurde nicht von Online übernommen.');
    }
}
