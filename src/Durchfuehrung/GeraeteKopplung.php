<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Http\Cookie;
use Wettkampf\Kern\Protokoll;

/**
 * Geräte-Kopplung für Tischbesatzung und Ausruf (statt PIN):
 *
 *  1. Die Leitung zeigt bzw. druckt je Tatami und Rolle einen QR-Code. Er enthält ein HMAC über Tatami, Rolle und ein Salz –
 *     mit dem Schlüssel dieser Installation, also nicht erratbar und nicht auf eine andere Installation übertragbar.
 *  2. Das Gerät scannt ihn und fragt die Kopplung an. Es bekommt ein Zufallstoken als HttpOnly-Cookie (der Server kennt nur
 *     den Hash) und zeigt ein kurzes Prüfwort.
 *  3. Die Leitung vergleicht das Prüfwort und gibt das Gerät frei. Erst dann darf es etwas – ein abfotografierter QR-Code allein
 *     reicht nicht (OWASP A07).
 *
 * Freigegebene Geräte melden sich über das Cookie selbst wieder an (auch nach Leerlauf), bis die Leitung sie sperrt, neue
 * QR-Codes nichts daran ändern oder das Turnier abgeschlossen ist. Eingaben laufen unter dem Gerätenamen (Änderungsverlauf).
 */
final class GeraeteKopplung
{
    public const COOKIE = 'taikai_geraet';
    public const ROLLEN = ['tisch' => 'Eingabe', 'ausruf' => 'Ausruf'];
    /** offene Anfragen verfallen nach dieser Zeit */
    public const WARTEN_SEKUNDEN = 1800;
    /** ohne „Zeichen, die man verwechselt“ (0/O, 1/I/L) */
    private const PRUEF_ZEICHEN = 'ACDEFGHJKMNPQRTUVWXYZ';

    public function __construct(private readonly Database $db, private readonly string $schluessel) {}

    /** Token für den QR-Code (Tatami, Rolle); legt das Salz beim ersten Mal an. */
    public function qrToken(int $tatamiId, string $rolle): string
    {
        self::rollePruefen($rolle);
        $salz = $this->db->wert('SELECT salz FROM tatami_kopplung WHERE tatami_id = ? AND rolle = ?', [$tatamiId, $rolle]);
        if (!is_string($salz)) {
            $salz = bin2hex(random_bytes(16));
            $this->db->insert('tatami_kopplung', ['tatami_id' => $tatamiId, 'rolle' => $rolle, 'salz' => $salz, 'erstellt_am' => date('Y-m-d H:i:s')]);
        }
        return $this->hmac($tatamiId, $rolle, $salz);
    }

    /** Neue QR-Codes für eine Tatami: die alten taugen nicht mehr zum Koppeln (gekoppelte Geräte bleiben). */
    public function qrErneuern(int $turnierId, int $tatamiId): void
    {
        $this->tatamiImTurnier($turnierId, $tatamiId);
        $this->db->delete('tatami_kopplung', ['tatami_id' => $tatamiId]);
        (new Protokoll($this->db))->eintragen($turnierId, 'tatami', $tatamiId, 'qr_codes_erneuert');
    }

    /**
     * QR-Code prüfen (Tatami noch in einem laufenden Turnier, Token passt).
     *
     * @return array<string, mixed> Tatami mit Turniername
     */
    public function qrPruefen(int $tatamiId, string $rolle, string $token): array
    {
        self::rollePruefen($rolle);
        $zeile = $this->db->eine(
            'SELECT ta.id, ta.turnier_id, ta.bezeichnung, tu.name AS turnier, k.salz
               FROM tatami ta JOIN turnier tu ON tu.id = ta.turnier_id
               JOIN tatami_kopplung k ON k.tatami_id = ta.id AND k.rolle = ?
              WHERE ta.id = ? AND tu.status <> ?',
            [$rolle, $tatamiId, TurnierPhase::ABGESCHLOSSEN],
        );
        if ($zeile === null || !hash_equals($this->hmac($tatamiId, $rolle, (string) $zeile['salz']), $token)) {
            throw new FachFehler('Dieser QR-Code gilt nicht (mehr). Bitte den aktuellen Code bei der Wettkampfleitung scannen.');
        }
        unset($zeile['salz']);
        return $zeile;
    }

    /**
     * Gerät meldet sich an (nach gültigem QR-Code): Cookie setzen, auf Freigabe warten.
     *
     * @param array<string, mixed> $tatami aus qrPruefen()
     * @return string Prüfwort, das Gerät und Leitung vergleichen
     */
    public function anfragen(array $tatami, string $rolle, string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $name = mb_substr($name !== '' ? $name : self::ROLLEN[$rolle] . '-Gerät', 0, 60);
        // höchstens 20 offene Anfragen je Turnier – sonst kann man die Freigabeliste zumüllen
        $this->db->query('DELETE FROM tatami_geraet WHERE status = ? AND erstellt_am < ?', ['wartet', date('Y-m-d H:i:s', time() - self::WARTEN_SEKUNDEN)]);
        if ((int) $this->db->wert('SELECT COUNT(*) FROM tatami_geraet WHERE turnier_id = ? AND status = ?', [$tatami['turnier_id'], 'wartet']) >= 20) {
            throw new FachFehler('Zu viele offene Anfragen – bitte die Wettkampfleitung, wartende Geräte freizugeben oder abzulehnen.');
        }
        $token = Cookie::token();
        $pruefwort = self::PRUEF_ZEICHEN[random_int(0, strlen(self::PRUEF_ZEICHEN) - 1)] . random_int(2, 9) . '-' . random_int(10, 99);
        $this->db->insert('tatami_geraet', [
            'turnier_id' => $tatami['turnier_id'],
            'tatami_id' => $tatami['id'],
            'rolle' => $rolle,
            'token_hash' => Cookie::hash($token),
            'pruefwort' => $pruefwort,
            'name' => $name,
            'status' => 'wartet',
            'erstellt_am' => date('Y-m-d H:i:s'),
        ]);
        Cookie::setzen(self::COOKIE, $token, time() + 14 * 86400);
        return $pruefwort;
    }

    /**
     * Gerät zum Cookie dieses Browsers (gleich welcher Status), nur solange das Turnier läuft.
     *
     * @return array<string, mixed>|null
     */
    public function diesesGeraet(): ?array
    {
        $token = Cookie::lesen(self::COOKIE);
        if ($token === null) {
            return null;
        }
        return $this->db->eine(
            'SELECT g.*, ta.bezeichnung AS tatami, tu.name AS turnier
               FROM tatami_geraet g JOIN tatami ta ON ta.id = g.tatami_id JOIN turnier tu ON tu.id = g.turnier_id
              WHERE g.token_hash = ? AND tu.status <> ?',
            [Cookie::hash($token), TurnierPhase::ABGESCHLOSSEN],
        );
    }

    /** Ist das Gerät (Sitzung) noch freigegeben? Merkt sich höchstens minütlich „zuletzt gesehen“. */
    public function gueltig(int $geraetId): bool
    {
        $g = $this->db->eine(
            'SELECT g.status, g.zuletzt_am FROM tatami_geraet g JOIN turnier tu ON tu.id = g.turnier_id WHERE g.id = ? AND tu.status <> ?',
            [$geraetId, TurnierPhase::ABGESCHLOSSEN],
        );
        if ($g === null || $g['status'] !== 'frei') {
            return false;
        }
        if ($g['zuletzt_am'] === null || strtotime((string) $g['zuletzt_am']) < time() - 60) {
            $this->db->update('tatami_geraet', ['zuletzt_am' => date('Y-m-d H:i:s')], ['id' => $geraetId]);
        }
        return true;
    }

    /**
     * Geräte eines Turniers für die Leitung.
     *
     * @return list<array<string, mixed>>
     */
    public function liste(int $turnierId): array
    {
        $this->db->query('DELETE FROM tatami_geraet WHERE status = ? AND erstellt_am < ?', ['wartet', date('Y-m-d H:i:s', time() - self::WARTEN_SEKUNDEN)]);
        return $this->db->alle(
            "SELECT g.id, g.tatami_id, g.rolle, g.pruefwort, g.name, g.status, g.erstellt_am, g.freigegeben_am, g.freigegeben_von, g.zuletzt_am,
                    ta.bezeichnung AS tatami
               FROM tatami_geraet g JOIN tatami ta ON ta.id = g.tatami_id
              WHERE g.turnier_id = ?
              ORDER BY CASE g.status WHEN 'wartet' THEN 0 WHEN 'frei' THEN 1 ELSE 2 END, ta.nummer, g.rolle, g.id",
            [$turnierId],
        );
    }

    /**
     * Kurzer Stand der Geräteliste (welche Geräte mit welchem Status): ändert sich bei neuer Anfrage, Freigabe,
     * Sperre oder Ablauf, nicht aber durch „zuletzt aktiv“ – die Seite der Leitung lädt nur dann neu.
     */
    public function stand(int $turnierId): string
    {
        $zeilen = $this->db->alle(
            'SELECT id, status FROM tatami_geraet WHERE turnier_id = ? AND (status <> ? OR erstellt_am >= ?) ORDER BY id',
            [$turnierId, 'wartet', date('Y-m-d H:i:s', time() - self::WARTEN_SEKUNDEN)],
        );
        return md5(implode(',', array_map(static fn(array $z): string => $z['id'] . ':' . $z['status'], $zeilen)));
    }

    public function freigeben(int $turnierId, int $geraetId, string $von): void
    {
        $g = $this->geraet($turnierId, $geraetId);
        if ($g['status'] !== 'wartet') {
            throw new FachFehler('Dieses Gerät wartet nicht (mehr) auf Freigabe.');
        }
        $this->db->update('tatami_geraet', ['status' => 'frei', 'freigegeben_am' => date('Y-m-d H:i:s'), 'freigegeben_von' => $von], ['id' => $geraetId]);
        (new Protokoll($this->db))->eintragen($turnierId, 'tatami_geraet', $geraetId, 'freigegeben', ['name' => $g['name'], 'rolle' => $g['rolle'], 'tatami_id' => $g['tatami_id']]);
    }

    /** Ablehnen (wartend) bzw. sperren (freigegeben): Das Gerät ist sofort abgemeldet und muss neu gekoppelt werden. */
    public function sperren(int $turnierId, int $geraetId): void
    {
        $g = $this->geraet($turnierId, $geraetId);
        if ($g['status'] === 'wartet') {
            $this->db->delete('tatami_geraet', ['id' => $geraetId]);
        } else {
            $this->db->update('tatami_geraet', ['status' => 'gesperrt'], ['id' => $geraetId]);
        }
        (new Protokoll($this->db))->eintragen($turnierId, 'tatami_geraet', $geraetId, $g['status'] === 'wartet' ? 'abgelehnt' : 'gesperrt', ['name' => $g['name']]);
    }

    /** Gerät meldet sich selbst ab („Abmelden“ am Tablet): Kopplung und Cookie weg. */
    public function entkoppeln(int $geraetId): void
    {
        $g = $this->db->eine('SELECT turnier_id, name FROM tatami_geraet WHERE id = ?', [$geraetId]);
        $this->db->delete('tatami_geraet', ['id' => $geraetId]);
        Cookie::loeschen(self::COOKIE);
        if ($g !== null) {
            (new Protokoll($this->db))->eintragen((int) $g['turnier_id'], 'tatami_geraet', $geraetId, 'entkoppelt', ['name' => $g['name']]);
        }
    }

    /** Gesperrte Geräte aus der Liste entfernen. */
    public function gesperrteEntfernen(int $turnierId): void
    {
        $this->db->delete('tatami_geraet', ['turnier_id' => $turnierId, 'status' => 'gesperrt']);
    }

    /** Anzeigename für Sitzung und Änderungsverlauf. */
    public static function bezeichnung(string $tatami, string $name, int $geraetId): string
    {
        return $tatami . ' · ' . $name . ' (Gerät ' . $geraetId . ')';
    }

    public static function rollePruefen(string $rolle): void
    {
        if (!isset(self::ROLLEN[$rolle])) {
            throw new FachFehler('Unbekannte Rolle.');
        }
    }

    private function hmac(int $tatamiId, string $rolle, string $salz): string
    {
        return hash_hmac('sha256', 'tatami-kopplung|' . $tatamiId . '|' . $rolle . '|' . $salz, $this->schluessel);
    }

    /** @return array<string, mixed> */
    private function geraet(int $turnierId, int $geraetId): array
    {
        return $this->db->eine('SELECT * FROM tatami_geraet WHERE id = ? AND turnier_id = ?', [$geraetId, $turnierId])
            ?? throw new FachFehler('Gerät nicht gefunden.');
    }

    private function tatamiImTurnier(int $turnierId, int $tatamiId): void
    {
        if ($this->db->wert('SELECT 1 FROM tatami WHERE id = ? AND turnier_id = ?', [$tatamiId, $turnierId]) === null) {
            throw new FachFehler('Tatami nicht gefunden.');
        }
    }
}
