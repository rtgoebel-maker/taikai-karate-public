<?php

declare(strict_types=1);

namespace Wettkampf\Identitaet\Http;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Identitaet\AnmeldeSchutz;
use Wettkampf\Identitaet\Konten;
use Wettkampf\Identitaet\KontoVertrauen;
use Wettkampf\Kern\Drossel;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\QrCode;
use Wettkampf\Kern\Totp;

/**
 * „Konto & Sicherheit“ für jedes angemeldete Konto: eigene Angaben und Passwort ändern, Authenticator-App einrichten bzw.
 * entfernen, neue Wiederherstellungscodes, vertraute Geräte vergessen. Passwort, E-Mail-Adresse, App entfernen und neue
 * Codes nur mit dem bisherigen Passwort (Fehlversuche gesperrt wie beim Login, OWASP A07).
 */
final class SicherheitController extends Controller
{
    private const GEHEIMNIS = 'konto_app_geheimnis';

    public function index(Request $request): Response
    {
        $id = $this->ich();
        $app = $this->app->authenticatorApp();
        $daten = [
            'appSeit' => $app->seit($id),
            'codesOffen' => $app->wiederherstellungOffen($id),
            'vertraut' => (new KontoVertrauen($this->db))->anzahl($id),
            'pflicht' => $this->app->zweiterFaktorPflicht(),
            'mailMoeglich' => $this->app->postausgang() !== null,
            'einrichten' => null,
            'konto' => $this->db->eine(
                'SELECT b.email, b.vorname, b.nachname, b.telefon, v.name AS verein FROM benutzer b LEFT JOIN verein v ON v.id = b.verein_id WHERE b.id = ?',
                [$id],
            ) ?? [],
            'min' => Konten::MIN_PASSWORT,
        ];
        if ($daten['appSeit'] === null) {
            $_SESSION[self::GEHEIMNIS] ??= Totp::geheimnis();
            $email = (string) ($this->db->wert('SELECT COALESCE(email, login) FROM benutzer WHERE id = ?', [$id]) ?? '');
            $daten['einrichten'] = [
                'qr' => QrCode::svg(Totp::uri((string) $_SESSION[self::GEHEIMNIS], $email)),
                'schluessel' => Totp::lesbar((string) $_SESSION[self::GEHEIMNIS]),
                'ziel' => url('konto/sicherheit/app'),
                'imLogin' => false,
            ];
        }
        return $this->seite('auth/sicherheit', $daten);
    }

    public function appEinrichten(Request $request): Response
    {
        $id = $this->ich();
        $geheimnis = $_SESSION[self::GEHEIMNIS] ?? throw new FachFehler('Bitte die Seite neu laden und den QR-Code erneut scannen.');
        $drossel = new Drossel($this->db);
        $drossel->pruefen('app-einrichten:' . $id, 10, 600);
        $drossel->zaehlen('app-einrichten:' . $id, 600);
        $codes = $this->app->authenticatorApp()->einrichten($id, (string) $geheimnis, $request->text('code'));
        unset($_SESSION[self::GEHEIMNIS]);
        return $this->seite('auth/wiederherstellung', ['codes' => $codes, 'weiter' => url('konto/sicherheit')]);
    }

    public function appEntfernen(Request $request): Response
    {
        $id = $this->passwortBestaetigt($request);
        $this->app->authenticatorApp()->entfernen($id, 'selbst');
        $this->erfolg('Authenticator-App entfernt.' . ($this->app->zweiterFaktorPflicht() && $this->app->postausgang() === null
            ? ' Bei der nächsten Anmeldung muss sie neu eingerichtet werden (zweiter Faktor ist Pflicht).' : ''));
        return $this->weiter('konto/sicherheit');
    }

    public function codesNeu(Request $request): Response
    {
        $id = $this->passwortBestaetigt($request);
        if (!$this->app->authenticatorApp()->aktiv($id)) {
            throw new FachFehler('Bitte zuerst die Authenticator-App einrichten.');
        }
        return $this->seite('auth/wiederherstellung', ['codes' => $this->app->authenticatorApp()->wiederherstellungNeu($id), 'weiter' => url('konto/sicherheit')]);
    }

    /** Eigene Angaben ändern; eine neue E-Mail-Adresse (= Anmeldename) nur mit Passwort, die alte Adresse wird informiert. */
    public function angaben(Request $request): Response
    {
        $id = $this->ich();
        $neueEmail = Konten::email($request->text('email'));
        $bisher = (string) ($this->db->wert('SELECT email FROM benutzer WHERE id = ?', [$id]) ?? '');
        if ($neueEmail !== $bisher) {
            $this->passwortBestaetigt($request);
        }
        $konten = new Konten($this->db);
        $alt = $konten->eigeneAngabenAendern($id, $request->post);
        $rechte = $konten->rechte($id);
        if ($rechte !== null) {
            Session::rechteAktualisieren($rechte['name'], $rechte['admin'], $rechte['turniere'], $rechte['verein']);
        }
        if ($alt !== null) {
            $this->app->postausgang()?->einreihen($alt, 'E-Mail-Adresse geändert – Taikai – Karate', self::hinweisText(
                'die E-Mail-Adresse Ihres Kontos wurde gerade auf ' . $neueEmail . ' geändert. Mit ihr melden Sie sich künftig an.',
            ), 'konto_email_geaendert');
        }
        $this->erfolg($alt !== null ? 'Angaben gespeichert – künftig mit ' . $neueEmail . ' anmelden.' : 'Angaben gespeichert.');
        return $this->weiter('konto/sicherheit');
    }

    /** Eigenes Passwort ändern (bisheriges nötig); vertraute Geräte fragen danach wieder nach dem Code. */
    public function passwort(Request $request): Response
    {
        $neu = (string) $request->post('neu', '');
        if ($neu !== (string) $request->post('neu2', '')) {
            throw new FachFehler('Die beiden Eingaben des neuen Passworts stimmen nicht überein.');
        }
        Konten::passwortPruefen($neu);
        $id = $this->passwortBestaetigt($request);
        (new Konten($this->db))->passwortSetzen($id, $neu);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $email = (string) ($this->db->wert('SELECT email FROM benutzer WHERE id = ?', [$id]) ?? '');
        $this->app->postausgang()?->einreihen($email, 'Passwort geändert – Taikai – Karate', self::hinweisText(
            'das Passwort Ihres Kontos wurde gerade geändert. Vertraute Geräte fragen bei der nächsten Anmeldung wieder nach dem Code.',
        ), 'konto_passwort_geaendert');
        $this->erfolg('Passwort geändert.');
        return $this->weiter('konto/sicherheit');
    }

    private static function hinweisText(string $was): string
    {
        return "Guten Tag,\n\n$was\n\n"
            . "Das waren nicht Sie? Dann bitte sofort über „Passwort vergessen“ ein neues Passwort setzen und die Administration informieren.\n"
            . "\n-- \nDiese Nachricht wurde automatisch von Taikai – Karate verschickt.";
    }

    public function vertrauenWiderrufen(Request $request): Response
    {
        (new KontoVertrauen($this->db))->widerrufen($this->ich());
        $this->erfolg('Kein Gerät wird mehr ohne Code angemeldet.');
        return $this->weiter('konto/sicherheit');
    }

    private function ich(): int
    {
        return (int) (Session::benutzer()['id'] ?? throw new FachFehler('Bitte anmelden.'));
    }

    /** Passwort des eigenen Kontos prüfen; Fehlversuche sperren wie beim Login. */
    private function passwortBestaetigt(Request $request): int
    {
        $id = $this->ich();
        $schutz = new AnmeldeSchutz($this->db);
        $ziel = 'passwort-bestaetigung:' . $id;
        $ip = $this->app->clientIp($request);
        $schutz->pruefen($ziel, $ip);
        if (!(new Konten($this->db))->passwortStimmt($id, (string) $request->post('passwort', ''))) {
            $schutz->fehlschlag($ziel, $ip);
            throw new FachFehler('Das Passwort ist falsch.');
        }
        $schutz->erfolg($ziel, $ip);
        return $id;
    }
}
