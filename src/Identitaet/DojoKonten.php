<?php

declare(strict_types=1);

namespace Wettkampf\Identitaet;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Mail\Postausgang;
use Wettkampf\Kern\Protokoll;

/**
 * Selbst angelegte Dojo-Konten (bei der Online-Anmeldung), E-Mail-Bestätigung und „Passwort vergessen“.
 *
 * Sicherheit (OWASP A01/A07):
 * - Ein neues Konto ist erst nach Bestätigung der E-Mail-Adresse nutzbar; unbestätigte Konten verfallen nach 7 Tagen.
 * - Es sieht nur die eigenen Anmeldungen. Zugriff auf ein Dojo (Starter, Ergebnisse, Portal) erst, wenn die
 *   Wettkampfleitung es bei der Freigabe zuordnet – sonst könnte sich jeder als bestehendes Dojo ausgeben.
 * - Antworten verraten nie, ob es eine E-Mail-Adresse schon gibt (gleiche Meldung, Hinweis nur per Mail).
 * - Einmal-Links: zufälliges Token, gespeichert nur als SHA-256, begrenzt gültig, nach Gebrauch gelöscht.
 * - Selbst angelegte Konten gelten bis zu einer festen Zahl Monate nach dem (letzten) Turnier und werden danach
 *   gelöscht; je Turnier ist eine neue Anmeldung nötig.
 */
final class DojoKonten
{
    public const ZWECK_BESTAETIGEN = 'bestaetigen';
    public const ZWECK_PASSWORT = 'passwort';
    private const BESTAETIGEN_STUNDEN = 72;
    private const PASSWORT_MINUTEN = 60;
    private const UNBESTAETIGT_TAGE = 7;

    public function __construct(private readonly Database $db) {}

    /**
     * Konto bei der Online-Anmeldung anlegen bzw. mit einem noch unbestätigten Konto derselben Adresse verbinden.
     * Gibt es schon ein bestätigtes Konto zur Adresse, geht nur ein Hinweis per Mail hinaus (keine Verknüpfung –
     * die Anmeldung erscheint dort trotzdem, weil sie dieselbe E-Mail-Adresse trägt).
     *
     * @param array<string, mixed> $daten konto_vorname, konto_nachname, konto_passwort, konto_passwort2
     * @param string $ablauf Konto gültig bis (Y-m-d)
     */
    public function anlegenFuerAnmeldung(array $daten, string $email, string $anmeldungUuid, string $ablauf, string $turnierName, Postausgang $post): void
    {
        $passwort = (string) ($daten['konto_passwort'] ?? '');
        if ($passwort !== (string) ($daten['konto_passwort2'] ?? '')) {
            throw new FachFehler('Die beiden Passwörter stimmen nicht überein.');
        }
        Konten::passwortPruefen($passwort);
        $email = Konten::email($email);
        $this->aufraeumen();
        $vorhanden = $this->db->eine('SELECT id, bestaetigung_offen, aktiv FROM benutzer WHERE email = ?', [$email]);

        if ($vorhanden !== null && !(int) $vorhanden['bestaetigung_offen']) {
            if ((int) $vorhanden['aktiv']) {
                $post->einreihen($email, 'Ihr Konto – ' . $turnierName, "Guten Tag,\n\n"
                    . "bei der Online-Anmeldung zu „{$turnierName}“ wurde ein Konto für diese E-Mail-Adresse gewünscht. "
                    . "Für diese Adresse gibt es bereits ein Konto, deshalb wurde kein neues angelegt. Ihre Anmeldung ist trotzdem eingegangen – "
                    . "den Bearbeitungsstand sehen Sie jederzeit über den Bearbeitungslink aus der Eingangsbestätigung.\n\n"
                    . 'Zum bestehenden Konto: ' . $post->link('login') . ' (Passwort vergessen? ' . $post->link('passwort-vergessen') . ")\n" . self::fuss(), 'konto_vorhanden');
            }
            return;
        }

        $id = $this->db->transaktion(function () use ($vorhanden, $daten, $email, $passwort, $ablauf) {
            if ($vorhanden !== null) {
                $id = (int) $vorhanden['id']; // unbestätigtes Konto: Angaben erneuern, neuer Link
                $this->db->update('benutzer', ['passwort_hash' => password_hash($passwort, PASSWORD_DEFAULT)], ['id' => $id]);
            } else {
                $id = (new Konten($this->db))->anlegen([
                    'email' => $email, 'vorname' => $daten['konto_vorname'] ?? '', 'nachname' => $daten['konto_nachname'] ?? '',
                ], $passwort, false);
                $this->db->update('benutzer', ['bestaetigung_offen' => 1], ['id' => $id]);
            }
            $this->verlaengern($id, $ablauf);
            return $id;
        });
        $this->verknuepfen($id, $anmeldungUuid);
        $token = $this->token($id, self::ZWECK_BESTAETIGEN, self::BESTAETIGEN_STUNDEN * 60);
        $post->einreihen($email, 'Bitte E-Mail-Adresse bestätigen – ' . $turnierName, "Guten Tag,\n\n"
            . "bei der Online-Anmeldung zu „{$turnierName}“ wurde ein Konto für diese E-Mail-Adresse angelegt. "
            . "Bitte bestätigen Sie die Adresse innerhalb von " . self::BESTAETIGEN_STUNDEN . " Stunden mit diesem Link:\n\n"
            . $post->link('konto/bestaetigen/' . $token) . "\n\n"
            . "Danach können Sie sich mit Ihrer E-Mail-Adresse und Ihrem Passwort anmelden und sehen unter „Meine Meldungen“ "
            . "jederzeit den Bearbeitungsstand Ihrer Anmeldung.\n\n"
            . 'Das Konto gilt bis ' . date('d.m.Y', (int) strtotime($ablauf)) . " und wird danach automatisch gelöscht. "
            . "Für jedes weitere Turnier ist eine neue Anmeldung nötig (angemeldet verlängert sie das Konto).\n\n"
            . "Sie haben kein Konto angelegt? Dann ignorieren Sie diese E-Mail – ohne Bestätigung wird es nach "
            . self::UNBESTAETIGT_TAGE . " Tagen gelöscht.\n" . self::fuss(), 'konto_bestaetigen');
    }

    /** Anmeldung einem Konto zuordnen (angemeldet abgeschickt bzw. bei der Kontoanlage). */
    public function verknuepfen(int $benutzerId, string $anmeldungUuid): void
    {
        if ($this->db->wert('SELECT benutzer_id FROM konto_anmeldung WHERE benutzer_id = ? AND anmeldung_uuid = ?', [$benutzerId, $anmeldungUuid]) === null) {
            $this->db->insert('konto_anmeldung', ['benutzer_id' => $benutzerId, 'anmeldung_uuid' => $anmeldungUuid, 'erstellt_am' => date('Y-m-d H:i:s')]);
        }
    }

    /** Ablaufdatum eines selbst angelegten Kontos nach hinten schieben (nie verkürzen; Konten ohne Ablauf bleiben ohne). */
    public function verlaengern(int $benutzerId, string $ablauf): void
    {
        $alt = $this->db->eine('SELECT ablauf_am, bestaetigung_offen FROM benutzer WHERE id = ?', [$benutzerId]);
        // selbst angelegt = mit Ablaufdatum bzw. noch unbestätigt; von der Administration angelegte Konten laufen nie ab
        if ($alt === null || ($alt['ablauf_am'] === null && !(int) $alt['bestaetigung_offen'])) {
            return;
        }
        if ($alt['ablauf_am'] === null || (string) $alt['ablauf_am'] < $ablauf) {
            $this->db->update('benutzer', ['ablauf_am' => $ablauf], ['id' => $benutzerId]);
        }
    }

    /** E-Mail bestätigen. @return int Konto */
    public function bestaetigen(string $token): int
    {
        $t = $this->tokenPruefen($token, self::ZWECK_BESTAETIGEN)
            ?? throw new FachFehler('Der Bestätigungslink ist ungültig oder abgelaufen. Einen neuen Link gibt es über „Passwort vergessen“.');
        $this->db->update('benutzer', ['bestaetigung_offen' => 0], ['id' => $t['benutzer_id']]);
        $this->db->delete('konto_token', ['id' => $t['id']]);
        (new Protokoll($this->db))->eintragen(null, 'benutzer', (int) $t['benutzer_id'], 'email_bestaetigt');
        return (int) $t['benutzer_id'];
    }

    /**
     * „Passwort vergessen“: Link zum Neusetzen (bzw. bei unbestätigtem Konto ein neuer Bestätigungslink).
     * Gibt nie preis, ob die Adresse bekannt ist.
     */
    public function passwortVergessen(string $email, Postausgang $post): void
    {
        $this->aufraeumen();
        $konto = $this->db->eine('SELECT id, bestaetigung_offen FROM benutzer WHERE email = ? AND aktiv = 1', [Konten::email($email)]);
        if ($konto === null) {
            return;
        }
        if ((int) $konto['bestaetigung_offen']) {
            $token = $this->token((int) $konto['id'], self::ZWECK_BESTAETIGEN, self::BESTAETIGEN_STUNDEN * 60);
            $post->einreihen(Konten::email($email), 'Bitte E-Mail-Adresse bestätigen', "Guten Tag,\n\nIhr Konto ist noch nicht bestätigt. Bitte bestätigen Sie die Adresse mit diesem Link:\n\n"
                . $post->link('konto/bestaetigen/' . $token) . "\n" . self::fuss(), 'konto_bestaetigen');
            return;
        }
        $token = $this->token((int) $konto['id'], self::ZWECK_PASSWORT, self::PASSWORT_MINUTEN);
        $post->einreihen(Konten::email($email), 'Passwort neu setzen', "Guten Tag,\n\n"
            . "für Ihr Konto wurde ein neues Passwort angefordert. Mit diesem Link (gültig " . self::PASSWORT_MINUTEN . " Minuten, nur einmal) setzen Sie es:\n\n"
            . $post->link('passwort-neu/' . $token) . "\n\n"
            . "Sie haben nichts angefordert? Dann ignorieren Sie diese E-Mail – Ihr Passwort bleibt unverändert.\n" . self::fuss(), 'passwort_vergessen');
    }

    /** Gehört das Token zu einem gültigen Passwort-Link? */
    public function passwortTokenGueltig(string $token): bool
    {
        return $this->tokenPruefen($token, self::ZWECK_PASSWORT) !== null;
    }

    public function passwortNeu(string $token, string $passwort, string $wiederholung): void
    {
        $t = $this->tokenPruefen($token, self::ZWECK_PASSWORT)
            ?? throw new FachFehler('Der Link ist ungültig oder abgelaufen – bitte unter „Passwort vergessen“ einen neuen anfordern.');
        if ($passwort !== $wiederholung) {
            throw new FachFehler('Die beiden Passwörter stimmen nicht überein.');
        }
        (new Konten($this->db))->passwortSetzen((int) $t['benutzer_id'], $passwort);
        // alle offenen Passwort-Links dieses Kontos verfallen
        $this->db->query('DELETE FROM konto_token WHERE benutzer_id = ? AND zweck = ?', [$t['benutzer_id'], self::ZWECK_PASSWORT]);
    }

    /**
     * Abgelaufene Konten und Links löschen: selbst angelegte Konten nach ihrem Ablaufdatum (außer mit Leitungs- oder
     * Administrationsrechten), unbestätigte nach 7 Tagen. @return int gelöschte Konten
     */
    public function aufraeumen(?int $jetzt = null): int
    {
        $jetzt ??= time();
        $this->db->query('DELETE FROM konto_token WHERE ablauf < ?', [date('Y-m-d H:i:s', $jetzt)]);
        $ids = array_map('intval', $this->db->spalte(
            'SELECT id FROM benutzer b WHERE ist_admin = 0 AND NOT EXISTS (SELECT 1 FROM turnier_rolle r WHERE r.benutzer_id = b.id)
               AND ((ablauf_am IS NOT NULL AND ablauf_am < ?) OR (bestaetigung_offen = 1 AND erstellt_am < ?))',
            [date('Y-m-d', $jetzt), date('Y-m-d H:i:s', $jetzt - self::UNBESTAETIGT_TAGE * 86400)],
        ));
        foreach ($ids as $id) {
            $this->db->delete('konto_anmeldung', ['benutzer_id' => $id]);
            $this->db->delete('konto_token', ['benutzer_id' => $id]);
            $this->db->delete('benutzer', ['id' => $id]);
            (new Protokoll($this->db))->eintragen(null, 'benutzer', $id, 'abgelaufen_geloescht');
        }
        return count($ids);
    }

    /** Neues Einmal-Token (ältere desselben Zwecks verfallen). @return string Klartext für den Link */
    private function token(int $benutzerId, string $zweck, int $minuten): string
    {
        $this->db->query('DELETE FROM konto_token WHERE benutzer_id = ? AND zweck = ?', [$benutzerId, $zweck]);
        $token = bin2hex(random_bytes(32));
        $this->db->insert('konto_token', [
            'benutzer_id' => $benutzerId, 'zweck' => $zweck, 'token_hash' => hash('sha256', $token),
            'ablauf' => date('Y-m-d H:i:s', time() + $minuten * 60), 'erstellt_am' => date('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    /** @return array<string, mixed>|null */
    private function tokenPruefen(string $token, string $zweck): ?array
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }
        $t = $this->db->eine('SELECT * FROM konto_token WHERE token_hash = ? AND zweck = ?', [hash('sha256', $token), $zweck]);
        return $t !== null && (string) $t['ablauf'] >= date('Y-m-d H:i:s') ? $t : null;
    }

    private static function fuss(): string
    {
        return "\n-- \nDiese Nachricht wurde automatisch von Taikai – Karate verschickt.";
    }
}
