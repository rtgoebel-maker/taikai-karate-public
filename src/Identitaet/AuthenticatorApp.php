<?php

declare(strict_types=1);

namespace Wettkampf\Identitaet;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Kern\Totp;
use Wettkampf\Kern\Tresor;

/**
 * Zweiter Faktor per Authenticator-App (OWASP A07) – auch ohne E-Mail-Versand und offline.
 *
 * Das TOTP-Geheimnis liegt nur verschlüsselt in der Datenbank (Tresor, AES-256-GCM, Schlüssel in data/); jeder Code gilt
 * nur einmal. Für ein verlorenes Handy gibt es 10 Wiederherstellungscodes zum einmaligen Gebrauch (nur als Hash
 * gespeichert); zusätzlich kann die Administration die App eines Kontos zurücksetzen.
 */
final class AuthenticatorApp
{
    public const WIEDERHERSTELLUNG_ANZAHL = 10;
    /** ohne leicht verwechselbare Zeichen (0/o, 1/l/i) */
    private const ZEICHEN = 'abcdefghjkmnpqrstuvwxyz23456789';

    public function __construct(private readonly Database $db, private readonly Tresor $tresor) {}

    public function aktiv(int $benutzerId): bool
    {
        return $this->db->wert('SELECT totp_geheimnis FROM benutzer WHERE id = ?', [$benutzerId]) !== null;
    }

    /**
     * App einrichten: Der erste Code aus der App bestätigt, dass das Geheimnis richtig übernommen wurde.
     *
     * @return list<string> neue Wiederherstellungscodes (Klartext nur jetzt)
     */
    public function einrichten(int $benutzerId, string $geheimnis, string $code, ?int $jetzt = null): array
    {
        $schritt = Totp::pruefen($geheimnis, $code, 0, $jetzt);
        if ($schritt === null) {
            throw new FachFehler('Der Code aus der App stimmt nicht. Bitte prüfen, ob die Uhrzeit des Handys stimmt, und den aktuellen Code eingeben.');
        }
        return $this->db->transaktion(function () use ($benutzerId, $geheimnis, $schritt) {
            $this->db->update('benutzer', [
                'totp_geheimnis' => $this->tresor->verschluesseln($geheimnis),
                'totp_seit' => date('Y-m-d H:i:s'),
                'totp_schritt' => $schritt,
            ], ['id' => $benutzerId]);
            (new Protokoll($this->db))->eintragen(null, 'benutzer', $benutzerId, 'authenticator_eingerichtet');
            return $this->wiederherstellungNeu($benutzerId);
        });
    }

    /** Code aus der App oder ein Wiederherstellungscode (verbraucht ihn). */
    public function pruefen(int $benutzerId, string $eingabe, ?int $jetzt = null): bool
    {
        $konto = $this->db->eine('SELECT totp_geheimnis, totp_schritt FROM benutzer WHERE id = ?', [$benutzerId]);
        if ($konto === null || $konto['totp_geheimnis'] === null) {
            return false;
        }
        if (preg_match('/[a-z]/i', $eingabe)) {
            return $this->wiederherstellungEinloesen($benutzerId, $eingabe);
        }
        $geheimnis = $this->tresor->entschluesseln((string) $konto['totp_geheimnis']);
        if ($geheimnis === null) {
            throw new FachFehler('Die Authenticator-App dieses Kontos lässt sich nicht prüfen (Schlüssel der Installation geändert). Bitte einen Wiederherstellungscode verwenden oder die Administration bitten, die App zurückzusetzen.');
        }
        $schritt = Totp::pruefen($geheimnis, $eingabe, (int) $konto['totp_schritt'], $jetzt);
        if ($schritt === null) {
            return false;
        }
        // nur weiterzählen (gleichzeitige Anmeldungen mit demselben Code: die zweite scheitert)
        return $this->db->query('UPDATE benutzer SET totp_schritt = ? WHERE id = ? AND totp_schritt < ?', [$schritt, $benutzerId, $schritt])->rowCount() === 1;
    }

    /**
     * Neue Wiederherstellungscodes – die bisherigen gelten nicht mehr.
     *
     * @return list<string>
     */
    public function wiederherstellungNeu(int $benutzerId): array
    {
        $this->db->delete('konto_wiederherstellung', ['benutzer_id' => $benutzerId]);
        $codes = [];
        for ($i = 0; $i < self::WIEDERHERSTELLUNG_ANZAHL; $i++) {
            $code = '';
            for ($j = 0; $j < 8; $j++) {
                $code .= self::ZEICHEN[random_int(0, strlen(self::ZEICHEN) - 1)];
            }
            $code = substr($code, 0, 4) . '-' . substr($code, 4);
            $codes[] = $code;
            $this->db->insert('konto_wiederherstellung', ['benutzer_id' => $benutzerId, 'code_hash' => self::hash($code)]);
        }
        return $codes;
    }

    public function wiederherstellungOffen(int $benutzerId): int
    {
        return (int) $this->db->wert('SELECT COUNT(*) FROM konto_wiederherstellung WHERE benutzer_id = ? AND verbraucht_am IS NULL', [$benutzerId]);
    }

    /** @return string|null eingerichtet seit (Y-m-d H:i:s) */
    public function seit(int $benutzerId): ?string
    {
        $seit = $this->db->wert('SELECT totp_seit FROM benutzer WHERE id = ? AND totp_geheimnis IS NOT NULL', [$benutzerId]);
        return $seit === null ? null : (string) $seit;
    }

    /** App entfernen (selbst nach Passwortbestätigung oder durch die Administration). */
    public function entfernen(int $benutzerId, string $grund): void
    {
        $this->db->transaktion(function () use ($benutzerId, $grund) {
            $this->db->update('benutzer', ['totp_geheimnis' => null, 'totp_seit' => null, 'totp_schritt' => 0], ['id' => $benutzerId]);
            $this->db->delete('konto_wiederherstellung', ['benutzer_id' => $benutzerId]);
            (new Protokoll($this->db))->eintragen(null, 'benutzer', $benutzerId, 'authenticator_entfernt', ['grund' => $grund]);
        });
    }

    private function wiederherstellungEinloesen(int $benutzerId, string $eingabe): bool
    {
        $code = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $eingabe));
        if (strlen($code) !== 8) {
            return false;
        }
        $treffer = $this->db->query(
            'UPDATE konto_wiederherstellung SET verbraucht_am = ? WHERE benutzer_id = ? AND code_hash = ? AND verbraucht_am IS NULL',
            [date('Y-m-d H:i:s'), $benutzerId, self::hash(substr($code, 0, 4) . '-' . substr($code, 4))],
        )->rowCount() === 1;
        if ($treffer) {
            (new Protokoll($this->db))->eintragen(null, 'benutzer', $benutzerId, 'wiederherstellungscode_genutzt', ['uebrig' => $this->wiederherstellungOffen($benutzerId)]);
        }
        return $treffer;
    }

    private static function hash(string $code): string
    {
        return hash('sha256', 'wiederherstellung|' . $code);
    }
}
