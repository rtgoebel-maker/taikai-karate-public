<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http;

/**
 * Sitzung, Anmeldung, CSRF-Schutz und Flash-Meldungen.
 * Rollen: 'admin' (Wettkampfleitung) und 'tisch' (gebunden an ein Tatami).
 */
final class Session
{
    /**
     * Abmeldung nach so langer Zeit ohne Anfrage (OWASP A07). 12 Stunden: ein Turniertag mit
     * Pausen bleibt angemeldet; Tablets fragen ohnehin alle paar Sekunden nach.
     */
    public const LEERLAUF_SEKUNDEN = 12 * 3600;

    /** Name des Sitzungscookies. */
    public const COOKIE = 'wettkampf';

    /**
     * @param bool $nurVorhandene nur eine bestehende Sitzung fortsetzen (Cookie vorhanden), sonst ohne
     *                            Sitzung und ohne Set-Cookie – für öffentliche Abfragen, die Cloudflare zwischenspeichern darf
     */
    public static function starten(bool $nurVorhandene = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            return;
        }
        if ($nurVorhandene && !isset($_COOKIE[self::COOKIE])) {
            $_SESSION = [];
            return;
        }
        session_name(self::COOKIE);
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'use_strict_mode' => true,
            'gc_maxlifetime' => self::LEERLAUF_SEKUNDEN,
        ]);
    }

    /**
     * Leitung bzw. Tisch nach LEERLAUF_SEKUNDEN ohne Anfrage abmelden. Der Zeitstempel wird
     * höchstens einmal pro Minute erneuert, damit die Sitzung nicht bei jedem Polling geschrieben wird.
     *
     * @return bool true, wenn gerade wegen Leerlaufs abgemeldet wurde
     */
    public static function leerlaufPruefen(?int $jetzt = null): bool
    {
        $jetzt ??= time();
        $zuletzt = $_SESSION['aktiv_am'] ?? null;
        $angemeldet = isset($_SESSION['benutzer']) || isset($_SESSION['tisch']);
        $abgelaufen = $angemeldet && is_int($zuletzt) && $jetzt - $zuletzt > self::LEERLAUF_SEKUNDEN;
        if ($abgelaufen) {
            unset($_SESSION['benutzer'], $_SESSION['tisch']);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
        }
        if ($abgelaufen || !is_int($zuletzt) || $jetzt - $zuletzt >= 60) {
            $_SESSION['aktiv_am'] = $jetzt;
        }
        return $abgelaufen;
    }

    /** @return array{id: int, name: string, admin: bool, turniere: list<int>, verein?: int|null}|null */
    public static function benutzer(): ?array
    {
        $b = $_SESSION['benutzer'] ?? null;
        return is_array($b) && isset($b['admin']) ? $b : null;
    }

    /** Globale Administration: alle Turniere, Konten, Vereine, System. */
    public static function istAdmin(): bool
    {
        return (self::benutzer()['admin'] ?? false) === true;
    }

    /** Administration oder Turnierleitung irgendeines Turniers (Zugang zu den Leitungsseiten). */
    public static function istLeitung(): bool
    {
        $b = self::benutzer();
        return $b !== null && ($b['admin'] || $b['turniere'] !== []);
    }

    /** Darf dieses Turnier leiten: Administration oder Turnierleitung genau dieses Turniers. */
    public static function leitet(int $turnierId): bool
    {
        $b = self::benutzer();
        return $b !== null && ($b['admin'] || in_array($turnierId, $b['turniere'], true));
    }

    /** Dojo des angemeldeten Kontos (Coach-Bereich), sonst null. */
    public static function coachVerein(): ?int
    {
        $verein = self::benutzer()['verein'] ?? null;
        return is_int($verein) ? $verein : null;
    }

    /**
     * @param list<int> $turniere geleitete Turniere
     * @param int|null $verein Dojo (Coach-Konto)
     */
    public static function anmelden(int $id, string $name, bool $admin, array $turniere, ?int $verein = null): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['benutzer'] = ['id' => $id, 'name' => $name, 'admin' => $admin, 'turniere' => $turniere, 'verein' => $verein];
    }

    /**
     * Rechte aus der Datenbank übernehmen (bei jeder Anfrage, App::anmeldungenPruefen), damit
     * Ernennungen und Entzug sofort gelten.
     *
     * @param list<int> $turniere
     */
    public static function rechteAktualisieren(string $name, bool $admin, array $turniere, ?int $verein = null): void
    {
        if (isset($_SESSION['benutzer']['id'])) {
            $_SESSION['benutzer'] = ['id' => (int) $_SESSION['benutzer']['id'], 'name' => $name, 'admin' => $admin, 'turniere' => $turniere, 'verein' => $verein];
        }
    }

    /**
     * Tablet-Anmeldung: Gerät wird an ein Tatami gebunden. Ein Merkmal des PIN-Hashes wird
     * gespeichert, damit eine PIN-Änderung angemeldete Geräte abmeldet.
     */
    public static function tischAnmelden(int $turnierId, int $tatamiId, string $bezeichnung, ?string $pinHash = null): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['tisch'] = [
            'turnier_id' => $turnierId,
            'tatami_id' => $tatamiId,
            'name' => $bezeichnung,
            'pin' => self::pinMerkmal($pinHash),
        ];
    }

    /**
     * Anmeldung eines gekoppelten, von der Leitung freigegebenen Geräts (QR-Code). Die Rolle begrenzt, was es darf:
     * 'tisch' = Eingabe (alles am Tisch), 'ausruf' = nur Ausruf.
     */
    public static function geraetAnmelden(int $turnierId, int $tatamiId, string $bezeichnung, int $geraetId, string $rolle): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['tisch'] = [
            'turnier_id' => $turnierId,
            'tatami_id' => $tatamiId,
            'name' => $bezeichnung,
            'geraet' => $geraetId,
            'rolle' => $rolle,
        ];
        $_SESSION['tisch_ansicht'] = $rolle === 'ausruf' ? 'ausruf' : 'app';
    }

    /** @return array{turnier_id: int, tatami_id: int, name: string, pin?: string, geraet?: int, rolle?: string}|null */
    public static function tisch(): ?array
    {
        return $_SESSION['tisch'] ?? null;
    }

    /** Ausruf-Geräte (Rolle 'ausruf') nur für Ausruf-Seite und -Schnittstellen, nicht für die Ergebniseingabe (OWASP A01). */
    public static function tischDarf(string $pfad): bool
    {
        if ((self::tisch()['rolle'] ?? null) !== 'ausruf') {
            return true;
        }
        return (bool) preg_match('#^/(tisch/ausruf|api/v1/tisch/(ausruf(/.*)?|revision|nachricht/\d+/bestaetigen)|api/v1/feedback)$#', $pfad);
    }

    /**
     * Gilt die Tisch-Anmeldung noch für die aktuelle PIN der Tatami? Anmeldungen von vor
     * dieser Prüfung (ohne Merkmal) übernehmen die aktuelle PIN.
     */
    public static function tischPinGueltig(?string $pinHash): bool
    {
        if (!isset($_SESSION['tisch'])) {
            return false;
        }
        $merkmal = self::pinMerkmal($pinHash);
        if (!isset($_SESSION['tisch']['pin'])) {
            $_SESSION['tisch']['pin'] = $merkmal;
            return true;
        }
        return hash_equals((string) $_SESSION['tisch']['pin'], $merkmal);
    }

    private static function pinMerkmal(?string $pinHash): string
    {
        return hash('sha256', (string) $pinHash);
    }

    /** Name für Audit-Log und „erfasst von“. */
    public static function akteur(): string
    {
        return self::benutzer()['name'] ?? (self::tisch()['name'] ?? 'System');
    }

    public static function abmelden(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfPruefen(?string $token): bool
    {
        return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    /** Wert nur für diese Sitzung merken (z. B. Übergabe-Code zur Anzeige und zum Download); null löscht. */
    public static function merken(string $schluessel, ?string $wert): void
    {
        if ($wert === null) {
            unset($_SESSION['gemerkt'][$schluessel]);
            return;
        }
        $_SESSION['gemerkt'][$schluessel] = $wert;
    }

    public static function gemerkt(string $schluessel): ?string
    {
        $wert = $_SESSION['gemerkt'][$schluessel] ?? null;
        return is_string($wert) ? $wert : null;
    }

    public static function flash(string $typ, string $text): void
    {
        $_SESSION['flash'][] = ['typ' => $typ, 'text' => $text];
    }

    /** @return list<array{typ: string, text: string}> */
    public static function flashHolen(): array
    {
        $meldungen = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $meldungen;
    }
}
