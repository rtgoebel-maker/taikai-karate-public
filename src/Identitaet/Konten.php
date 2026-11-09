<?php

declare(strict_types=1);

namespace Wettkampf\Identitaet;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;

/**
 * Konten und Rechte (KONZEPT-ONLINE-OFFLINE.md Kap. 3): E-Mail als Benutzername, globale
 * Administration (mehrere möglich) und Turnierleitung je Turnier. Ein Konto mit Dojo ist ein
 * Coach-Konto (Coach-Bereich folgt mit der Online-Meldung). Kampfrichter haben keine Konten –
 * sie melden sich mit der Tatami-PIN an.
 */
final class Konten
{
    /** Mindestlänge neuer Passwörter (OWASP A07; bestehende kürzere Passwörter gelten weiter). */
    public const MIN_PASSWORT = 12;
    public const ROLLE_LEITUNG = 'leitung';

    /** Vergleichs-Hash, wenn es kein Konto gibt: gleiche Antwortzeit, E-Mail nicht per Zeitmessung erkennbar. */
    public const KEIN_KONTO_HASH = '$2y$12$Di1AeObWAuWN2Y0x.aF4y.Ebrutvqb0LuMaP7rEdvP4j/f8MbMrIu';

    public function __construct(private readonly Database $db) {}

    /**
     * Rechte eines aktiven Kontos.
     *
     * @return array{name: string, admin: bool, turniere: list<int>, verein: int|null}|null null = Konto fehlt, ist deaktiviert
     *         oder die E-Mail-Adresse ist noch nicht bestätigt; verein = Dojo (Coach)
     */
    public function rechte(int $id): ?array
    {
        $konto = $this->db->eine('SELECT name, ist_admin, aktiv, verein_id, bestaetigung_offen FROM benutzer WHERE id = ?', [$id]);
        if ($konto === null || !(int) $konto['aktiv'] || (int) $konto['bestaetigung_offen']) {
            return null;
        }
        $turniere = array_map('intval', $this->db->spalte(
            'SELECT turnier_id FROM turnier_rolle WHERE benutzer_id = ? AND rolle = ? ORDER BY turnier_id',
            [$id, self::ROLLE_LEITUNG],
        ));
        return [
            'name' => (string) $konto['name'],
            'admin' => (bool) (int) $konto['ist_admin'],
            'turniere' => $turniere,
            'verein' => $konto['verein_id'] === null ? null : (int) $konto['verein_id'],
        ];
    }

    /** @return array<string, mixed>|null aktives Konto zur E-Mail (bei Altkonten: bisheriger Benutzername) */
    public function perEmail(string $email): ?array
    {
        return $this->db->eine('SELECT * FROM benutzer WHERE email = ? AND aktiv = 1', [self::email($email)]);
    }

    /**
     * Neues Konto.
     *
     * @param array<string, mixed> $daten email, vorname, nachname, telefon, verein_id
     */
    public function anlegen(array $daten, string $passwort, bool $admin): int
    {
        $felder = $this->felder($daten, null);
        self::passwortPruefen($passwort);
        return $this->db->transaktion(function () use ($felder, $passwort, $admin) {
            $id = $this->db->insert('benutzer', $felder + [
                'login' => 'k' . bin2hex(random_bytes(12)), // interner Schlüssel, angemeldet wird mit der E-Mail
                'passwort_hash' => password_hash($passwort, PASSWORD_DEFAULT),
                'rolle' => $admin ? 'admin' : 'konto',
                'ist_admin' => $admin ? 1 : 0,
                'aktiv' => 1,
                'erstellt_am' => date('Y-m-d H:i:s'),
            ]);
            $this->protokoll($id, 'angelegt', ['email' => $felder['email'], 'admin' => $admin, 'verein_id' => $felder['verein_id']]);
            return $id;
        });
    }

    /** @param array<string, mixed> $daten */
    public function aendern(int $id, array $daten): void
    {
        $this->konto($id);
        $felder = $this->felder($daten, $id);
        $this->db->update('benutzer', $felder, ['id' => $id]);
        $this->protokoll($id, 'geaendert', ['email' => $felder['email'], 'verein_id' => $felder['verein_id']]);
    }

    /**
     * Eigene Angaben ändern (Konto & Sicherheit): Name, Telefon, E-Mail – die Dojo-Zuordnung bleibt, wie sie ist.
     *
     * @param array<string, mixed> $daten
     * @return string|null bisherige E-Mail-Adresse, falls sie sich geändert hat
     */
    public function eigeneAngabenAendern(int $id, array $daten): ?string
    {
        $this->konto($id);
        $bisher = $this->db->eine('SELECT email, verein_id FROM benutzer WHERE id = ?', [$id]) ?? [];
        $felder = $this->felder(['verein_id' => $bisher['verein_id'] ?? null] + $daten, $id);
        $this->db->update('benutzer', $felder, ['id' => $id]);
        $alt = (string) ($bisher['email'] ?? '');
        $geaendert = $alt !== $felder['email'];
        $this->protokoll($id, 'eigene_angaben', $geaendert ? ['email_alt' => $alt, 'email' => $felder['email']] : null);
        return $geaendert ? $alt : null;
    }

    public function passwortSetzen(int $id, string $passwort): void
    {
        $this->konto($id);
        self::passwortPruefen($passwort);
        $this->db->update('benutzer', ['passwort_hash' => password_hash($passwort, PASSWORD_DEFAULT)], ['id' => $id]);
        (new KontoVertrauen($this->db))->widerrufen($id); // neues Passwort: vertraute Geräte fragen wieder nach dem Code
        $this->protokoll($id, 'passwort_geaendert');
    }

    /** Passwort des angemeldeten Kontos prüfen (Bestätigung vor sicherheitsrelevanten Änderungen). */
    public function passwortStimmt(int $id, string $passwort): bool
    {
        $hash = $this->db->wert('SELECT passwort_hash FROM benutzer WHERE id = ?', [$id]);
        return is_string($hash) && password_verify($passwort, $hash);
    }

    public function adminSetzen(int $id, bool $admin, int $ich): void
    {
        $this->konto($id);
        if (!$admin && $id === $ich) {
            throw new FachFehler('Die eigenen Administrationsrechte können nicht entzogen werden.');
        }
        $this->db->transaktion(function () use ($id, $admin) {
            $this->db->update('benutzer', ['ist_admin' => $admin ? 1 : 0, 'rolle' => $admin ? 'admin' : 'konto'], ['id' => $id]);
            $this->mindestensEinAdmin();
            $this->protokoll($id, 'admin', ['admin' => $admin]);
        });
    }

    public function aktivUmschalten(int $id, int $ich): void
    {
        $this->konto($id);
        if ($id === $ich) {
            throw new FachFehler('Das eigene Konto kann nicht deaktiviert werden.');
        }
        $this->db->transaktion(function () use ($id) {
            $this->db->query('UPDATE benutzer SET aktiv = 1 - aktiv WHERE id = ?', [$id]);
            (new KontoVertrauen($this->db))->widerrufen($id);
            $this->mindestensEinAdmin();
            $this->protokoll($id, 'aktiv', ['aktiv' => (int) $this->db->wert('SELECT aktiv FROM benutzer WHERE id = ?', [$id])]);
        });
    }

    /**
     * Deaktiviertes Konto endgültig löschen (z. B. damit die E-Mail-Adresse wieder frei wird). Nur deaktivierte Konten,
     * nie das eigene; zur Bestätigung die E-Mail-Adresse des Kontos. Rollen, Zuordnungen und Einmal-Links verschwinden
     * mit (Fremdschlüssel); der Änderungsverlauf behält den Namen als Text.
     */
    public function loeschen(int $id, int $ich, string $bestaetigung): void
    {
        $this->konto($id);
        $konto = $this->db->eine('SELECT * FROM benutzer WHERE id = ?', [$id]) ?? [];
        if ($id === $ich) {
            throw new FachFehler('Das eigene Konto kann nicht gelöscht werden.');
        }
        if ((int) $konto['aktiv']) {
            throw new FachFehler('Bitte das Konto zuerst deaktivieren – gelöscht werden nur deaktivierte Konten.');
        }
        if (self::email($bestaetigung) === '' || self::email($bestaetigung) !== self::email((string) ($konto['email'] ?? $konto['login']))) {
            throw new FachFehler('Zur Bestätigung bitte die E-Mail-Adresse des Kontos eingeben – nichts gelöscht.');
        }
        $this->db->transaktion(function () use ($id, $konto) {
            $this->db->delete('turnier_rolle', ['benutzer_id' => $id]);
            $this->db->delete('konto_anmeldung', ['benutzer_id' => $id]);
            $this->db->delete('konto_token', ['benutzer_id' => $id]);
            $this->db->delete('konto_vertrauen', ['benutzer_id' => $id]);
            $this->db->delete('konto_wiederherstellung', ['benutzer_id' => $id]);
            $this->db->delete('benutzer', ['id' => $id]);
            $this->mindestensEinAdmin();
            $this->protokoll($id, 'geloescht', ['email' => $konto['email'] ?? null, 'name' => $konto['name']]);
        });
    }

    /** Konto (per E-Mail) zur Turnierleitung eines Turniers ernennen. */
    public function turnierleitungHinzufuegen(int $turnierId, int $benutzerId, string $von): void
    {
        $this->konto($benutzerId);
        $vorhanden = $this->db->wert(
            'SELECT id FROM turnier_rolle WHERE turnier_id = ? AND benutzer_id = ? AND rolle = ?',
            [$turnierId, $benutzerId, self::ROLLE_LEITUNG],
        );
        if ($vorhanden !== null) {
            throw new FachFehler('Dieses Konto gehört schon zur Turnierleitung.');
        }
        $this->db->insert('turnier_rolle', [
            'turnier_id' => $turnierId, 'benutzer_id' => $benutzerId, 'rolle' => self::ROLLE_LEITUNG,
            'erstellt_am' => date('Y-m-d H:i:s'), 'erstellt_von' => mb_substr($von, 0, 100),
        ]);
        (new Protokoll($this->db))->eintragen($turnierId, 'benutzer', $benutzerId, 'turnierleitung_ernannt');
    }

    /** Turnierleitung entziehen. Die eigene Leitung kann nur die Administration entziehen; eine Leitung bleibt immer. */
    public function turnierleitungEntfernen(int $turnierId, int $benutzerId, int $ich, bool $ichAdmin): void
    {
        if ($benutzerId === $ich && !$ichAdmin) {
            throw new FachFehler('Die eigene Turnierleitung kann nur die Administration entziehen.');
        }
        $this->db->transaktion(function () use ($turnierId, $benutzerId) {
            $anzahl = $this->db->delete('turnier_rolle', ['turnier_id' => $turnierId, 'benutzer_id' => $benutzerId, 'rolle' => self::ROLLE_LEITUNG]);
            if ($anzahl === 0) {
                throw new FachFehler('Dieses Konto gehört nicht zur Turnierleitung.');
            }
            (new Protokoll($this->db))->eintragen($turnierId, 'benutzer', $benutzerId, 'turnierleitung_entzogen');
        });
    }

    /** @return list<array<string, mixed>> Turnierleitung eines Turniers */
    public function turnierleitung(int $turnierId): array
    {
        return $this->db->alle(
            'SELECT b.id, b.email, b.vorname, b.nachname, b.name, b.telefon, b.aktiv, b.ist_admin, r.erstellt_am, r.erstellt_von
               FROM turnier_rolle r JOIN benutzer b ON b.id = r.benutzer_id
              WHERE r.turnier_id = ? AND r.rolle = ? ORDER BY b.nachname, b.vorname',
            [$turnierId, self::ROLLE_LEITUNG],
        );
    }

    /** @return list<array<string, mixed>> alle Konten mit Dojo und geleiteten Turnieren */
    public function alle(): array
    {
        $konten = $this->db->alle(
            'SELECT b.id, b.email, b.vorname, b.nachname, b.name, b.telefon, b.verein_id, v.name AS verein,
                    b.ist_admin, b.aktiv, b.erstellt_am,
                    (SELECT COUNT(*) FROM konto_vertrauen kv WHERE kv.benutzer_id = b.id AND kv.ablauf >= ?) AS vertraut,
                    CASE WHEN b.totp_geheimnis IS NULL THEN 0 ELSE 1 END AS app
               FROM benutzer b LEFT JOIN verein v ON v.id = b.verein_id
              ORDER BY b.nachname, b.vorname, b.email',
            [date('Y-m-d H:i:s')],
        );
        $turniere = [];
        foreach ($this->db->alle(
            'SELECT r.benutzer_id, t.name FROM turnier_rolle r JOIN turnier t ON t.id = r.turnier_id WHERE r.rolle = ? ORDER BY t.datum DESC',
            [self::ROLLE_LEITUNG],
        ) as $z) {
            $turniere[(int) $z['benutzer_id']][] = (string) $z['name'];
        }
        foreach ($konten as &$k) {
            $k['turniere'] = $turniere[(int) $k['id']] ?? [];
        }
        return $konten;
    }

    /**
     * Konten, denen eine Online-Anmeldung zugeordnet ist (selbst angelegt bzw. angemeldet abgeschickt).
     *
     * @return list<array{id: int, email: string, name: string, verein_id: int|null, bestaetigung_offen: bool}>
     */
    public function zurAnmeldung(string $anmeldungUuid): array
    {
        return array_map(static fn(array $k) => [
            'id' => (int) $k['id'], 'email' => (string) $k['email'], 'name' => (string) $k['name'],
            'verein_id' => $k['verein_id'] === null ? null : (int) $k['verein_id'], 'bestaetigung_offen' => (bool) (int) $k['bestaetigung_offen'],
        ], $this->db->alle(
            'SELECT b.id, b.email, b.name, b.verein_id, b.bestaetigung_offen FROM benutzer b JOIN konto_anmeldung k ON k.benutzer_id = b.id
              WHERE k.anmeldung_uuid = ? AND b.aktiv = 1 ORDER BY b.id',
            [$anmeldungUuid],
        ));
    }

    /**
     * Konto einem Dojo zuordnen (Freigabe einer Online-Anmeldung durch die Leitung). Nur Konten ohne Dojo –
     * ein bestehendes Coach-Konto wechselt dadurch nie das Dojo. @return bool zugeordnet
     */
    public function vereinZuordnen(int $id, int $vereinId, ?int $turnierId = null): bool
    {
        $geaendert = $this->db->query('UPDATE benutzer SET verein_id = ? WHERE id = ? AND verein_id IS NULL AND bestaetigung_offen = 0', [$vereinId, $id])->rowCount() > 0;
        if ($geaendert) {
            (new Protokoll($this->db))->eintragen($turnierId, 'benutzer', $id, 'dojo_zugeordnet', ['verein_id' => $vereinId]);
        }
        return $geaendert;
    }

    public static function email(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function passwortPruefen(string $passwort): void
    {
        if (mb_strlen($passwort) < self::MIN_PASSWORT) {
            throw new FachFehler('Das Passwort muss mindestens ' . self::MIN_PASSWORT . ' Zeichen lang sein.');
        }
        if (strlen($passwort) > 72) {
            throw new FachFehler('Das Passwort darf höchstens 72 Byte lang sein.'); // bcrypt wertet nur 72 Byte aus
        }
    }

    /**
     * Geprüfte Kontofelder.
     *
     * @param array<string, mixed> $daten
     * @return array{email: string, vorname: string, nachname: string, name: string, telefon: string|null, verein_id: int|null}
     */
    private function felder(array $daten, ?int $id): array
    {
        $text = static fn(string $feld, int $max) => mb_substr(trim((string) ($daten[$feld] ?? '')), 0, $max);
        $email = self::email((string) ($daten['email'] ?? ''));
        if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new FachFehler('Bitte eine gültige E-Mail-Adresse angeben.');
        }
        if ($this->db->wert('SELECT id FROM benutzer WHERE email = ? AND id <> ?', [$email, $id ?? 0]) !== null) {
            throw new FachFehler('Für diese E-Mail-Adresse gibt es schon ein Konto.');
        }
        $vorname = $text('vorname', 100);
        $nachname = $text('nachname', 100);
        if ($vorname === '' || $nachname === '') {
            throw new FachFehler('Bitte Vor- und Nachname angeben.');
        }
        $telefon = $text('telefon', 50);
        if ($telefon !== '' && !preg_match('/^\+?[0-9 ()\/\-]{4,50}$/', $telefon)) {
            throw new FachFehler('Die Telefonnummer darf nur Ziffern, Leerzeichen und + ( ) / - enthalten.');
        }
        $vereinId = (int) ($daten['verein_id'] ?? 0);
        if ($vereinId > 0 && $this->db->wert('SELECT id FROM verein WHERE id = ?', [$vereinId]) === null) {
            throw new FachFehler('Das gewählte Dojo gibt es nicht.');
        }
        return [
            'email' => $email,
            'vorname' => $vorname,
            'nachname' => $nachname,
            'name' => $vorname . ' ' . $nachname,
            'telefon' => $telefon === '' ? null : $telefon,
            'verein_id' => $vereinId > 0 ? $vereinId : null,
        ];
    }

    private function konto(int $id): void
    {
        if ($this->db->wert('SELECT id FROM benutzer WHERE id = ?', [$id]) === null) {
            throw new FachFehler('Konto nicht gefunden.');
        }
    }

    private function mindestensEinAdmin(): void
    {
        if ((int) $this->db->wert('SELECT COUNT(*) FROM benutzer WHERE ist_admin = 1 AND aktiv = 1') === 0) {
            throw new FachFehler('Es muss mindestens ein aktives Administrationskonto bleiben.');
        }
    }

    /** @param array<string, mixed>|null $daten */
    private function protokoll(int $id, string $aktion, ?array $daten = null): void
    {
        (new Protokoll($this->db))->eintragen(null, 'benutzer', $id, $aktion, $daten);
    }
}
