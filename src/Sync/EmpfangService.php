<?php

declare(strict_types=1);

namespace Wettkampf\Sync;

use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Kern\Version;

/**
 * Online empfängt vom Hallen-Laptop (Schritte 9f–9h, ZIELARCHITEKTUR Kap. 5.3):
 * - stand: Gesamtstand übernehmen (Abgleich per UUID)
 * - antraege: neue, online gestellte Korrekturanträge an die Halle ausliefern (E8)
 * - rueckgabe: letzter Stand samt Logos und Geburtsdaten der Nachmeldungen, danach führt wieder Online
 *
 * Jede Sendung durchläuft dieselbe Prüfung: lesbar → laufende Übergabe mit passender Nummer → Signatur (Schlüssel der
 * Übergabe, Zeitfenster) → Halle-Instanz passt → Nonce einmalig. Erst dann wird der Inhalt geprüft und übernommen.
 */
final class EmpfangService
{
    public const MAX_KOMPRIMIERT = 5 * 1024 * 1024;
    public const MAX_ENTPACKT = 60 * 1024 * 1024;

    public const PFAD_ANTRAEGE = '/api/v1/sync/antraege';
    public const PFAD_RUECKGABE = '/api/v1/sync/rueckgabe';
    public const FORMAT_ANTRAEGE = 'wettkampf-antragsabfrage';
    public const FORMAT_RUECKGABE = 'wettkampf-rueckgabe';

    public function __construct(private readonly Database $db) {}

    /**
     * @param array<string, string|null> $kopf Header in Kleinbuchstaben
     * @return array{revision: int}
     */
    public function annehmen(string $inhalt, array $kopf, ?int $jetzt = null): array
    {
        $jetzt ??= time();
        $daten = self::entpacken($inhalt, SendeService::FORMAT);
        $uebergabe = $this->pruefen(SendeService::PFAD, $inhalt, $daten, $kopf, $jetzt);
        Abgleich::pruefen($daten['stand'] ?? null);

        return $this->db->transaktion(function () use ($daten, $uebergabe, $jetzt) {
            $bisher = $uebergabe['uebernommene_revision'] !== null ? (int) $uebergabe['uebernommene_revision'] : null;
            if ($bisher !== null && $daten['revision'] <= $bisher) {
                $this->db->update('sync_uebergabe', ['letzter_empfang' => date('Y-m-d H:i:s', $jetzt)], ['id' => (int) $uebergabe['id']]);
                return ['revision' => $bisher]; // nichts Neues (doppelt oder überholt)
            }
            $this->uebernehmen($uebergabe, $daten, $jetzt);
            return ['revision' => $daten['revision']];
        });
    }

    /**
     * Online gestellte Korrekturanträge, die die Halle noch nicht kennt (E8). Verweise als UUID, dazu der Vereinsname,
     * falls der Verein in der Halle noch fehlt.
     *
     * @param array<string, string|null> $kopf
     * @return array{antraege: list<array<string, mixed>>}
     */
    public function antraege(string $inhalt, array $kopf, ?int $jetzt = null): array
    {
        $jetzt ??= time();
        $daten = json_decode($inhalt, true);
        if (!is_array($daten) || ($daten['format'] ?? null) !== self::FORMAT_ANTRAEGE || !is_array($daten['bekannt'] ?? null)) {
            throw new SyncAbgewiesen(400, 'inhalt', 'Abfrage unlesbar.');
        }
        self::kopfPruefen($daten);
        $uebergabe = $this->pruefen(self::PFAD_ANTRAEGE, $inhalt, $daten, $kopf, $jetzt);
        $bekannt = array_flip(array_filter($daten['bekannt'], 'is_string'));
        $neu = [];
        foreach ($this->db->alle(
            'SELECT k.*, v.uuid AS verein_uuid, v.name AS verein_name, t.uuid AS teilnahme_uuid
               FROM korrekturantrag k JOIN verein v ON v.id = k.verein_id LEFT JOIN teilnahme t ON t.id = k.teilnahme_id
              WHERE k.turnier_id = ? ORDER BY k.id',
            [(int) $uebergabe['tid']],
        ) as $a) {
            if (!isset($bekannt[$a['uuid']])) {
                unset($a['id'], $a['turnier_id'], $a['verein_id'], $a['teilnahme_id']);
                $neu[] = $a;
            }
        }
        return ['antraege' => $neu];
    }

    /**
     * Rückgabe: letzter Stand, Logos und Geburtsdaten/Ausweisnummern der Nachmeldungen (nur wo online leer); danach
     * ist die Übergabe beendet und Online führt wieder.
     *
     * @param array<string, string|null> $kopf
     */
    public function rueckgabe(string $inhalt, array $kopf, ?int $jetzt = null): void
    {
        $jetzt ??= time();
        $daten = self::entpacken($inhalt, self::FORMAT_RUECKGABE);
        $uebergabe = $this->pruefen(self::PFAD_RUECKGABE, $inhalt, $daten, $kopf, $jetzt);
        Abgleich::pruefen($daten['stand'] ?? null);
        $logos = self::logosPruefen($daten['logos'] ?? null);
        $details = self::detailsPruefen($daten['athlet_details'] ?? null);

        $this->db->transaktion(function () use ($uebergabe, $daten, $jetzt, $logos, $details) {
            $turnierId = (int) $uebergabe['tid'];
            $this->uebernehmen($uebergabe, $daten, $jetzt, false);
            foreach ($logos as $logo) {
                $this->db->query('DELETE FROM turnier_logo WHERE turnier_id = ? AND art = ?', [$turnierId, $logo['art']]);
                $this->db->insert('turnier_logo', ['turnier_id' => $turnierId] + $logo);
            }
            foreach ($details as $d) {
                $this->db->query(
                    'UPDATE athlet SET geburtsdatum = COALESCE(geburtsdatum, ?), ausweis_nr = COALESCE(ausweis_nr, ?)
                      WHERE uuid = ? AND id IN (SELECT athlet_id FROM teilnahme WHERE turnier_id = ?)',
                    [$d['geburtsdatum'], $d['ausweis_nr'], $d['uuid'], $turnierId],
                );
            }
            $zeit = date('Y-m-d H:i:s', $jetzt);
            $this->db->update('sync_uebergabe', ['beendet_am' => $zeit], ['id' => (int) $uebergabe['id']]);
            $this->db->update('turnier', ['fuehrung_instanz' => null, 'fuehrung_name' => null, 'fuehrung_seit' => null, 'fuehrung_stand' => null], ['id' => $turnierId]);
            (new Protokoll($this->db))->eintragen($turnierId, 'turnier', $turnierId, 'zurueckgegeben', ['nr' => (int) $uebergabe['nr'], 'revision' => $daten['revision']]);
        });
    }

    /**
     * Stand übernehmen und vermerken.
     *
     * @param array<string, mixed> $uebergabe
     * @param array<string, mixed> $daten
     */
    private function uebernehmen(array $uebergabe, array $daten, int $jetzt, bool $protokoll = true): void
    {
        $turnierId = (int) $uebergabe['tid'];
        $zeit = date('Y-m-d H:i:s', $jetzt);
        $erster = $uebergabe['uebernommene_revision'] === null;
        (new Abgleich($this->db))->uebernehmen($turnierId, $daten['stand']);
        $this->db->update('sync_uebergabe', [
            'halle_instanz' => $daten['instanz'], 'uebernommene_revision' => $daten['revision'], 'letzter_empfang' => $zeit,
        ], ['id' => (int) $uebergabe['id']]);
        // erst jetzt ist die Halle sicher bekannt: Platzhalter durch ihre Instanz ersetzen
        $this->db->update('turnier', ['fuehrung_instanz' => $daten['instanz'], 'fuehrung_stand' => $zeit], ['id' => $turnierId]);
        if ($protokoll && $erster) {
            (new Protokoll($this->db))->eintragen($turnierId, 'turnier', $turnierId, 'hallenstand_erster', ['nr' => (int) $uebergabe['nr']]);
        } else {
            (new Protokoll($this->db))->revisionErhoehen($turnierId); // Statusseite und Coaching-Portal laden neu
        }
    }

    /**
     * Gemeinsame Prüfung jeder Sendung; liefert die laufende Übergabe (mit Online-Turnier-ID in „tid“).
     *
     * @param array<string, mixed> $daten
     * @param array<string, string|null> $kopf
     * @return array<string, mixed>
     */
    private function pruefen(string $pfad, string $inhalt, array $daten, array $kopf, int $jetzt): array
    {
        $uebergabe = $this->db->eine(
            'SELECT u.*, t.id AS tid FROM sync_uebergabe u JOIN turnier t ON t.id = u.turnier_id
              WHERE t.uuid = ? AND u.nr = ? AND u.beendet_am IS NULL',
            [$daten['turnier'], $daten['uebergabe_nr']],
        );
        if ($uebergabe === null) {
            // zurückgenommen, zurückgegeben oder neu übergeben: diese Halle führt nicht (mehr)
            throw new SyncAbgewiesen(409, 'uebergabe', 'Übergabe nicht (mehr) gültig – Online führt das Turnier bzw. es wurde neu übergeben.');
        }
        if (!Signatur::gueltig((string) $uebergabe['schluessel'], $pfad, $inhalt, $kopf, $jetzt)
            || !hash_equals($daten['instanz'], (string) ($kopf['x-sync-instanz'] ?? ''))) {
            throw new SyncAbgewiesen(401, 'signatur', 'Signatur ungültig.');
        }
        if ($uebergabe['halle_instanz'] !== null && !hash_equals((string) $uebergabe['halle_instanz'], $daten['instanz'])) {
            throw new SyncAbgewiesen(409, 'andere_halle', 'Diese Übergabe führt bereits ein anderer Laptop.');
        }
        $programm = is_string($daten['programm'] ?? null) ? mb_substr($daten['programm'], 0, 40) : '';
        if (($daten['protokoll'] ?? null) !== Version::SYNC_PROTOKOLL) {
            throw new SyncAbgewiesen(409, 'version', 'Programmversionen passen nicht zusammen (Laptop ' . ($programm ?: 'älter') . ', Online ' . Version::aktuell()
                . '). Laptop nach der Rückgabe aktualisieren bzw. Online auf den gleichen Stand bringen.');
        }
        $this->db->update('sync_uebergabe', ['halle_version' => $programm], ['id' => (int) $uebergabe['id']]);
        $this->db->transaktion(function () use ($kopf, $jetzt) {
            $this->db->query('DELETE FROM sync_nonce WHERE zeit < ?', [date('Y-m-d H:i:s', $jetzt - 2 * Signatur::ZEITFENSTER_SEKUNDEN)]);
            if ($this->db->wert('SELECT nonce FROM sync_nonce WHERE nonce = ?', [(string) $kopf['x-sync-nonce']]) !== null) {
                throw new SyncAbgewiesen(409, 'wiederholt', 'Sendung wurde bereits angenommen.');
            }
            $this->db->insert('sync_nonce', ['nonce' => (string) $kopf['x-sync-nonce'], 'zeit' => date('Y-m-d H:i:s', $jetzt)]);
        });
        return $uebergabe;
    }

    /** @return array<string, mixed> */
    private static function entpacken(string $inhalt, string $format): array
    {
        if (strlen($inhalt) > self::MAX_KOMPRIMIERT) {
            throw new SyncAbgewiesen(413, 'groesse', 'Sendung zu groß.');
        }
        $json = @gzdecode($inhalt, self::MAX_ENTPACKT);
        $daten = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($daten) || ($daten['format'] ?? null) !== $format || !is_int($daten['revision'] ?? null)) {
            throw new SyncAbgewiesen(400, 'inhalt', 'Sendung unlesbar.');
        }
        self::kopfPruefen($daten);
        return $daten;
    }

    /** @param array<string, mixed> $daten */
    private static function kopfPruefen(array $daten): void
    {
        if (!is_string($daten['turnier'] ?? null) || !is_int($daten['uebergabe_nr'] ?? null) || !is_string($daten['instanz'] ?? null)) {
            throw new SyncAbgewiesen(400, 'inhalt', 'Sendung unlesbar.');
        }
    }

    /** @return list<array{art: string, mime: string, daten: string, breite: int|null, hoehe: int|null, geaendert_am: string|null}> */
    private static function logosPruefen(mixed $logos): array
    {
        $ergebnis = [];
        foreach (is_array($logos) ? $logos : [] as $l) {
            if (!is_array($l) || !is_string($l['art'] ?? null) || !preg_match('/^[a-z_]{1,30}$/', $l['art'])
                || !in_array($l['mime'] ?? null, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true) || !is_string($l['daten'] ?? null)) {
                throw new SyncAbgewiesen(400, 'inhalt', 'Rückgabe: Logo ungültig.');
            }
            $ergebnis[] = [
                'art' => $l['art'], 'mime' => $l['mime'], 'daten' => $l['daten'],
                'breite' => is_int($l['breite'] ?? null) ? $l['breite'] : null, 'hoehe' => is_int($l['hoehe'] ?? null) ? $l['hoehe'] : null,
                'geaendert_am' => is_string($l['geaendert_am'] ?? null) ? mb_substr($l['geaendert_am'], 0, 19) : null,
            ];
        }
        return $ergebnis;
    }

    /** @return list<array{uuid: string, geburtsdatum: string|null, ausweis_nr: string|null}> */
    private static function detailsPruefen(mixed $details): array
    {
        $ergebnis = [];
        foreach (is_array($details) ? $details : [] as $d) {
            $datum = $d['geburtsdatum'] ?? null;
            if (!is_array($d) || !is_string($d['uuid'] ?? null) || ($datum !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $datum))) {
                throw new SyncAbgewiesen(400, 'inhalt', 'Rückgabe: Athletendaten ungültig.');
            }
            $ausweis = $d['ausweis_nr'] ?? null;
            $ergebnis[] = ['uuid' => $d['uuid'], 'geburtsdatum' => $datum, 'ausweis_nr' => is_string($ausweis) ? mb_substr($ausweis, 0, 50) : null];
        }
        return $ergebnis;
    }
}
