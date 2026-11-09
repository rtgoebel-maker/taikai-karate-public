<?php

declare(strict_types=1);

namespace Wettkampf\Identitaet;

use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Http\Cookie;

/**
 * „Diesem Gerät 30 Tage vertrauen“ beim zweiten Faktor: Das Gerät bekommt ein Zufallstoken als HttpOnly-Cookie, der Server
 * speichert nur dessen SHA-256 mit Konto und Ablauf. Das Passwort braucht es weiterhin – vertraut wird nur dem Gerät beim Code.
 *
 * Widerruf: Passwortänderung, Deaktivieren/Löschen des Kontos und „Vertraute Geräte zurücksetzen“ in der Benutzerverwaltung.
 */
final class KontoVertrauen
{
    public const COOKIE = 'taikai_vertrauen';
    public const TAGE = 30;

    public function __construct(private readonly Database $db) {}

    /** Gerät für das Konto merken (nach richtig eingegebenem Code). */
    public function merken(int $benutzerId, string $geraet, ?int $jetzt = null): void
    {
        $jetzt ??= time();
        $token = Cookie::token();
        $ablauf = $jetzt + self::TAGE * 86400;
        $this->db->query('DELETE FROM konto_vertrauen WHERE ablauf < ?', [date('Y-m-d H:i:s', $jetzt)]);
        $this->db->insert('konto_vertrauen', [
            'benutzer_id' => $benutzerId,
            'token_hash' => Cookie::hash($token),
            'geraet' => mb_substr($geraet, 0, 200),
            'ablauf' => date('Y-m-d H:i:s', $ablauf),
            'erstellt_am' => date('Y-m-d H:i:s', $jetzt),
        ]);
        Cookie::setzen(self::COOKIE, $token, $ablauf);
    }

    /** Vertraut dieses Gerät (Cookie) dem Konto noch? */
    public function vertraut(int $benutzerId, ?int $jetzt = null): bool
    {
        $token = Cookie::lesen(self::COOKIE);
        if ($token === null) {
            return false;
        }
        return $this->db->wert(
            'SELECT 1 FROM konto_vertrauen WHERE token_hash = ? AND benutzer_id = ? AND ablauf >= ?',
            [Cookie::hash($token), $benutzerId, date('Y-m-d H:i:s', $jetzt ?? time())],
        ) !== null;
    }

    public function anzahl(int $benutzerId): int
    {
        return (int) $this->db->wert('SELECT COUNT(*) FROM konto_vertrauen WHERE benutzer_id = ? AND ablauf >= ?', [$benutzerId, date('Y-m-d H:i:s')]);
    }

    /** Alle vertrauten Geräte des Kontos vergessen – beim nächsten Login wieder Code. */
    public function widerrufen(int $benutzerId): void
    {
        $this->db->delete('konto_vertrauen', ['benutzer_id' => $benutzerId]);
    }
}
