<?php

declare(strict_types=1);

namespace Wettkampf\Sync;

use Wettkampf\Betrieb\SicherungService;
use Wettkampf\Betrieb\Turnierpaket;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Identitaet\AnmeldeSchutz;
use Wettkampf\Identitaet\Konten;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Instanz;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Kern\Uuid;
use Wettkampf\Kern\Version;

/**
 * Online-Seite der Übergabe (Schritt 9d): Turnier an einen Hallen-Laptop übergeben, Übergabe-Paket ausliefern
 * (Datei oder Abholen per Login + Code), Übergabe zurücknehmen.
 *
 * Mit dem Übergeben ist Online sofort nur lesend (Konzept Kap. 4.1). Bis die Halle sich meldet (9f), steht in
 * fuehrung_instanz ein Platzhalter; schreiben darf online niemand.
 */
final class UebergabeService
{
    public const VERFAHREN = 'HMAC-SHA256-Uebergabecode';

    public function __construct(private readonly Database $db, private readonly Instanz $instanz) {}

    /** @return array{nr: int, code: string} Code nur hier im Klartext – danach nur noch als Hash gespeichert */
    public function uebergeben(int $turnierId, string $von): array
    {
        return $this->db->transaktion(function () use ($turnierId, $von) {
            $turnier = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$turnierId]) ?? throw new FachFehler('Turnier nicht gefunden.');
            if (($turnier['fuehrung_instanz'] ?? null) !== null) {
                throw new FachFehler(hash_equals($this->instanz->id(), (string) $turnier['fuehrung_instanz'])
                    ? 'Dieses Turnier wurde an diese Installation übergeben – weitergeben kann nur Online.'
                    : 'Das Turnier ist bereits übergeben.');
            }
            $nr = (int) $turnier['uebergabe_nr'] + 1;
            $code = Uebergabecode::neu();
            $jetzt = date('Y-m-d H:i:s');
            $this->db->insert('sync_uebergabe', [
                'turnier_id' => $turnierId, 'nr' => $nr, 'code_hash' => Uebergabecode::hash($code),
                'schluessel' => bin2hex(random_bytes(32)), 'erstellt_am' => $jetzt, 'erstellt_von' => mb_substr($von, 0, 100),
            ]);
            // Platzhalter bis zum ersten Kontakt der Halle: niemand hier führt
            $this->db->update('turnier', [
                'fuehrung_instanz' => Uuid::v4(), 'fuehrung_name' => 'Hallen-Laptop', 'fuehrung_seit' => $jetzt, 'uebergabe_nr' => $nr,
            ], ['id' => $turnierId]);
            (new Protokoll($this->db))->eintragen($turnierId, 'turnier', $turnierId, 'uebergeben', ['nr' => $nr]);
            return ['nr' => $nr, 'code' => $code];
        });
    }

    /** @return array<string, mixed>|null laufende Übergabe */
    public function laufende(int $turnierId): ?array
    {
        return $this->db->eine('SELECT * FROM sync_uebergabe WHERE turnier_id = ? AND beendet_am IS NULL ORDER BY nr DESC LIMIT 1', [$turnierId]);
    }

    /**
     * Übergabe-Paket: vollständige Sicherung samt PINs plus Verbindungsdaten, signiert mit dem Übergabe-Code.
     * Enthält Geburtsdaten und den Sync-Schlüssel – wie eine Sicherung vertraulich behandeln.
     *
     * @return array<string, mixed>
     */
    public function paket(int $turnierId, string $code, string $onlineUrl): array
    {
        $laufend = $this->laufende($turnierId);
        if ($laufend === null || !hash_equals((string) $laufend['code_hash'], Uebergabecode::hash($code))) {
            throw new FachFehler('Keine laufende Übergabe mit diesem Code.');
        }
        $paket = (new SicherungService($this->db))->exportieren($turnierId);
        $paket['uebergabe'] = [
            'nr' => (int) $laufend['nr'],
            'schluessel' => (string) $laufend['schluessel'],
            'online_url' => rtrim($onlineUrl, '/'),
            'erstellt_am' => (string) $laufend['erstellt_am'],
            'protokoll' => Version::SYNC_PROTOKOLL, 'programm' => Version::aktuell(),
        ];
        $paket['pruefsumme'] = ['verfahren' => self::VERFAHREN, 'wert' => Turnierpaket::pruefsummeBerechnen($paket, Uebergabecode::dateiSchluessel($code))];
        return $paket;
    }

    /**
     * Abholen durch den Hallen-Laptop: Login der Turnierleitung (bzw. Administration) und Übergabe-Code.
     * Fehlversuche sperren wie beim Login (AnmeldeSchutz).
     *
     * @return array<string, mixed>
     */
    public function abholen(string $email, string $passwort, string $code, string $ip, string $onlineUrl): array
    {
        $schutz = new AnmeldeSchutz($this->db);
        $ziel = 'uebergabe:' . Konten::email($email);
        $schutz->pruefen($ziel, $ip);
        $konten = new Konten($this->db);
        $konto = $konten->perEmail(Konten::email($email));
        $passwortOk = password_verify($passwort, $konto['passwort_hash'] ?? Konten::KEIN_KONTO_HASH); // immer prüfen: gleiche Antwortzeit
        $rechte = $konto !== null && $passwortOk ? $konten->rechte((int) $konto['id']) : null;
        try {
            $laufend = $this->db->eine('SELECT * FROM sync_uebergabe WHERE code_hash = ? AND beendet_am IS NULL', [Uebergabecode::hash($code)]);
        } catch (FachFehler) {
            $laufend = null; // Code in falscher Form zählt wie ein falscher Code
        }
        $darf = $rechte !== null && $laufend !== null && ($rechte['admin'] || in_array((int) $laufend['turnier_id'], $rechte['turniere'], true));
        if (!$darf) {
            $schutz->fehlschlag($ziel, $ip);
            throw new FachFehler('Anmeldung oder Übergabe-Code ist falsch.');
        }
        $schutz->erfolg($ziel, $ip);
        $turnierId = (int) $laufend['turnier_id'];
        $this->db->update('sync_uebergabe', ['abgeholt_am' => date('Y-m-d H:i:s')], ['id' => (int) $laufend['id']]);
        (new Protokoll($this->db))->eintragen($turnierId, 'turnier', $turnierId, 'uebergabe_abgeholt', ['nr' => (int) $laufend['nr'], 'von' => $konto['email'] ?? null]);
        return $this->paket($turnierId, $code, $onlineUrl);
    }

    /**
     * Übergabe zurücknehmen bzw. Führung zurückholen: Online führt wieder; Sendungen der Halle mit dieser
     * Übergabe-Nummer werden abgewiesen (9f). Ergebnisse, die nur in der Halle stehen, gehen online nicht ein.
     */
    public function zuruecknehmen(int $turnierId, string $von): void
    {
        $this->db->transaktion(function () use ($turnierId, $von) {
            $turnier = $this->db->eine('SELECT fuehrung_instanz, uebergabe_nr FROM turnier WHERE id = ?', [$turnierId]) ?? throw new FachFehler('Turnier nicht gefunden.');
            if ($turnier['fuehrung_instanz'] === null) {
                throw new FachFehler('Das Turnier ist nicht übergeben.');
            }
            if (hash_equals($this->instanz->id(), (string) $turnier['fuehrung_instanz'])) {
                throw new FachFehler('Diese Installation führt das Turnier selbst; zurückgeben folgt mit Schritt 9h.');
            }
            $this->db->query('UPDATE sync_uebergabe SET beendet_am = ? WHERE turnier_id = ? AND beendet_am IS NULL', [date('Y-m-d H:i:s'), $turnierId]);
            $this->db->update('turnier', ['fuehrung_instanz' => null, 'fuehrung_name' => null, 'fuehrung_seit' => null], ['id' => $turnierId]);
            (new Protokoll($this->db))->eintragen($turnierId, 'turnier', $turnierId, 'uebergabe_zurueckgenommen', ['nr' => (int) $turnier['uebergabe_nr'], 'von' => $von]);
        });
    }
}
