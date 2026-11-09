<?php

declare(strict_types=1);

namespace Wettkampf\Sync;

use Wettkampf\Betrieb\SicherungService;
use Wettkampf\Betrieb\Turnierpaket;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Instanz;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Kern\Version;
use Wettkampf\Turnier\StammdatenService;

/**
 * Hallen-Seite der Übergabe (Schritt 9d): Übergabe-Paket per Datei + Code oder direkt vom Online-Server
 * (Login + Code) einspielen. Danach führt dieser Laptop das Turnier; Online ist nur lesend.
 */
final class HalleService
{
    public function __construct(private readonly Database $db, private readonly Instanz $instanz) {}

    /** @param array<string, mixed> $paket */
    public function einspielen(array $paket, string $code, string $quelle, ?string $onlineUrl = null): int
    {
        $summe = $paket['pruefsumme'] ?? null;
        $block = $paket['uebergabe'] ?? null;
        if (!is_array($summe) || ($summe['verfahren'] ?? null) !== UebergabeService::VERFAHREN || !is_string($summe['wert'] ?? null)) {
            throw new FachFehler('Das ist keine Übergabe-Datei (normale Sicherungen bitte unter „Sicherung“ importieren).');
        }
        if (!hash_equals(Turnierpaket::pruefsummeBerechnen($paket, Uebergabecode::dateiSchluessel($code)), $summe['wert'])) {
            throw new FachFehler('Übergabe-Code passt nicht zur Datei (oder die Datei wurde verändert).');
        }
        if (!is_array($block) || !is_int($block['nr'] ?? null) || !is_string($block['schluessel'] ?? null)
            || !preg_match('/^[0-9a-f]{64}$/', $block['schluessel']) || !is_string($block['online_url'] ?? null)) {
            throw new FachFehler('Die Übergabe-Datei ist unvollständig.');
        }
        if (($block['protokoll'] ?? null) !== Version::SYNC_PROTOKOLL) {
            throw new FachFehler('Dieser Laptop (Version ' . Version::aktuell() . ') passt nicht zur Programmversion des Online-Servers ('
                . (is_string($block['programm'] ?? null) ? mb_substr($block['programm'], 0, 40) : 'älter') . '). Bitte die Software auf dem Laptop aktualisieren.');
        }
        $url = self::onlineUrl($onlineUrl ?? $block['online_url']);
        $uuid = (string) ($paket['turnier']['uuid'] ?? '');
        unset($paket['uebergabe'], $paket['pruefsumme']);

        return $this->db->transaktion(function () use ($paket, $block, $url, $uuid, $quelle) {
            // dasselbe Turnier schon hier (frühere Übergabe): nur durch eine neuere Übergabe ersetzen
            $vorhanden = $this->db->eine('SELECT t.id, t.uebergabe_nr, a.id AS auftrag FROM turnier t LEFT JOIN sync_auftrag a ON a.turnier_id = t.id WHERE t.uuid = ?', [$uuid]);
            if ($vorhanden !== null) {
                if ($vorhanden['auftrag'] === null || (int) $vorhanden['uebergabe_nr'] >= $block['nr']) {
                    throw new FachFehler('Dieses Turnier ist hier schon vorhanden (Übergabe Nr. ' . (int) $vorhanden['uebergabe_nr'] . '). Eingespielt werden kann nur eine neuere Übergabe.');
                }
                (new StammdatenService($this->db))->turnierLoeschen((int) $vorhanden['id']);
            }
            $turnierId = (new SicherungService($this->db, $this->instanz))->importieren($paket, true)['turnier'];
            $jetzt = date('Y-m-d H:i:s');
            $this->db->update('turnier', [
                'fuehrung_instanz' => $this->instanz->id(), 'fuehrung_name' => 'Hallen-Laptop', 'fuehrung_seit' => $jetzt, 'uebergabe_nr' => $block['nr'],
            ], ['id' => $turnierId]);
            $this->db->insert('sync_auftrag', [
                'turnier_id' => $turnierId, 'online_url' => $url, 'uebergabe_nr' => $block['nr'], 'schluessel' => $block['schluessel'],
                'eingespielt_am' => $jetzt, 'quelle' => $quelle,
                // für die Rückgabe: Geburtsdaten nur für danach angelegte Athleten (Nachmeldungen)
                'athleten_bei_uebergabe' => json_encode($this->db->spalte(
                    'SELECT a.uuid FROM athlet a JOIN teilnahme t ON t.athlet_id = a.id WHERE t.turnier_id = ?',
                    [$turnierId],
                )),
            ]);
            (new Protokoll($this->db))->eintragen($turnierId, 'turnier', $turnierId, 'uebernommen', ['nr' => $block['nr'], 'quelle' => $quelle, 'online' => $url]);
            return $turnierId;
        });
    }

    /** Paket direkt vom Online-Server holen (Login der Turnierleitung + Übergabe-Code) und einspielen. */
    public function abholen(string $onlineUrl, string $email, string $passwort, string $code, Transport $transport): int
    {
        $url = self::onlineUrl($onlineUrl);
        Uebergabecode::normalisieren($code); // Formfehler sofort melden, ohne Online zu fragen
        $antwort = $transport->post($url . '/api/v1/sync/uebergabe', (string) json_encode(['email' => $email, 'passwort' => $passwort, 'code' => $code]));
        $daten = json_decode($antwort['body'], true);
        if ($antwort['status'] !== 200 || !is_array($daten)) {
            $fehler = is_array($daten) && is_string($daten['fehler'] ?? null) ? $daten['fehler'] : 'Antwort ' . $antwort['status'];
            throw new FachFehler('Abholen nicht möglich: ' . mb_substr($fehler, 0, 300));
        }
        return $this->einspielen($daten, $code, 'online', $url);
    }

    /** Online-Adresse prüfen: nur HTTPS (HTTP nur für einen Server auf diesem Rechner, z. B. zum Testen). */
    public static function onlineUrl(string $eingabe): string
    {
        $url = rtrim(trim($eingabe), '/');
        $teile = parse_url($url);
        $lokal = in_array($teile['host'] ?? '', ['localhost', '127.0.0.1', '::1'], true);
        if (!is_array($teile) || !isset($teile['host']) || isset($teile['user']) || isset($teile['query']) || isset($teile['fragment'])
            || !(($teile['scheme'] ?? '') === 'https' || (($teile['scheme'] ?? '') === 'http' && $lokal))) {
            throw new FachFehler('Bitte die Online-Adresse mit https:// angeben, z. B. https://turnier.example.de');
        }
        return $url;
    }

    /**
     * Hallen-Status aller aktuellen Turniere dieser Installation (Seite „Hallen-Laptop“): wer führt, was ist der
     * nächste Schritt. Aktuell = Turniertag höchstens 60 Tage zurück, übergeben oder hier übernommen.
     *
     * @return list<array{id: int, name: string, datum: string, status: string, art: string, aktion: string, auftrag: bool}>
     */
    public function uebersicht(?int $jetzt = null): array
    {
        $ich = $this->instanz->id();
        $seit = date('Y-m-d', ($jetzt ?? time()) - 7 * 86400);
        $liste = [];
        foreach ($this->db->alle(
            "SELECT t.id, t.name, t.datum, t.fuehrung_instanz, t.fuehrung_name,
                    a.id AS auftrag_id, a.zurueckgegeben_am, a.entzogen_am,
                    (SELECT COUNT(*) FROM sync_uebergabe u WHERE u.turnier_id = t.id AND u.beendet_am IS NULL AND u.abgeholt_am IS NULL) AS offen
               FROM turnier t LEFT JOIN sync_auftrag a ON a.turnier_id = t.id
              WHERE t.datum >= ? OR (t.fuehrung_instanz IS NOT NULL AND t.fuehrung_instanz <> '') OR a.id IS NOT NULL
              ORDER BY t.datum DESC, t.id DESC",
            [$seit],
        ) as $t) {
            $fuehrend = (string) ($t['fuehrung_instanz'] ?? '');
            [$status, $art, $aktion] = match (true) {
                $t['auftrag_id'] !== null && hash_equals($ich, $fuehrend) => ['Dieser Laptop führt das Turnier', 'ok', 'An Online zurückgeben …'],
                $t['auftrag_id'] !== null && $t['zurueckgegeben_am'] !== null => ['An Online zurückgegeben – hier nur lesend', '', 'Ansehen …'],
                $t['auftrag_id'] !== null && $t['entzogen_am'] !== null => ['Führung von Online zurückgenommen – hier nur lesend', 'fehler', 'Ansehen …'],
                $fuehrend === '' && (int) $t['offen'] > 0 => ['Übergabe vorbereitet – Laptop hat noch nicht abgeholt', 'warn', 'Übergabe ansehen …'],
                $fuehrend === '' || hash_equals($ich, $fuehrend) => ['Diese Installation führt', 'info', 'An die Halle übergeben …'],
                default => ['An Hallen-Laptop übergeben' . (($t['fuehrung_name'] ?? '') !== '' ? ' („' . $t['fuehrung_name'] . '“)' : '') . ' – hier nur lesend', 'warn', 'Übergabe ansehen …'],
            };
            $liste[] = ['id' => (int) $t['id'], 'name' => (string) $t['name'], 'datum' => (string) $t['datum'], 'status' => $status, 'art' => $art, 'aktion' => $aktion, 'auftrag' => $t['auftrag_id'] !== null];
        }
        return $liste;
    }
}
