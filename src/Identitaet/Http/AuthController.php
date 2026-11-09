<?php

declare(strict_types=1);

namespace Wettkampf\Identitaet\Http;

use Wettkampf\Identitaet\AnmeldeSchutz;
use Wettkampf\Identitaet\Einrichtung;
use Wettkampf\Identitaet\DojoKonten;
use Wettkampf\Identitaet\Konten;
use Wettkampf\Identitaet\KontoVertrauen;
use Wettkampf\Identitaet\ZweiterFaktor;
use Wettkampf\Kern\Drossel;
use Wettkampf\Kern\Mail\Postausgang;
use Wettkampf\Kern\QrCode;
use Wettkampf\Kern\Totp;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Controller;

final class AuthController extends Controller
{
    /** bcrypt-Hash eines Passworts, das zu keinem Konto gehört (nur für gleiche Antwortzeit beim Login) */

    public function setupFormular(Request $request): Response
    {
        $einrichtung = $this->einrichtung();
        if (!$einrichtung->offen()) {
            return $this->weiter('login');
        }
        $codeNoetig = !Einrichtung::lokalerServer();
        if ($codeNoetig) {
            $einrichtung->code(); // Datei anlegen, damit sie auf dem Server zu finden ist
        }
        return $this->seite('auth/setup', ['codeNoetig' => $codeNoetig]);
    }

    public function setup(Request $request): Response
    {
        $einrichtung = $this->einrichtung();
        if (!$einrichtung->offen()) {
            return $this->weiter('login');
        }
        if (!Einrichtung::lokalerServer()) {
            $schutz = new AnmeldeSchutz($this->db);
            $ip = $this->app->clientIp($request);
            $schutz->pruefen('setup', $ip);
            if (!$einrichtung->codePasst($request->text('code'))) {
                $schutz->fehlschlag('setup', $ip);
                throw new FachFehler('Der Einrichtungscode ist falsch. Er steht in der Datei data/einrichtung.token auf dem Server.');
            }
            $schutz->erfolg('setup', $ip);
        }
        $passwort = (string) $request->post('passwort', '');
        if ($passwort !== $request->post('passwort2')) {
            throw new FachFehler('Die Passwörter stimmen nicht überein.');
        }
        // erstes Konto = globale Administration
        $id = (new Konten($this->db))->anlegen($request->post, $passwort, true);
        $einrichtung->abschliessen();
        $rechte = (new Konten($this->db))->rechte($id) ?? throw new \RuntimeException('Konto fehlt nach dem Anlegen.');
        Session::anmelden($id, $rechte['name'], true, [], $rechte['verein']);
        (new Protokoll($this->db))->eintragen(null, 'benutzer', $id, 'eingerichtet');
        $this->erfolg('Einrichtung abgeschlossen. Willkommen!');
        return $this->weiter('/');
    }

    public function loginFormular(Request $request): Response
    {
        return $this->seite('auth/login', [
            'zurueck' => $request->text('zurueck'),
            'botschutz' => $this->app->botSchutz()->felder('login'),
            'mailAktiv' => $this->app->postausgang() !== null,
        ]);
    }

    public function login(Request $request): Response
    {
        $schutz = new AnmeldeSchutz($this->db);
        $konten = new Konten($this->db);
        $this->app->botSchutz()->pruefen($request->post, 'login', 1);
        $email = Konten::email($request->text('login'));
        $ziel = 'login:' . $email;
        $ip = $this->app->clientIp($request);
        $schutz->pruefen($ziel, $ip);
        $benutzer = $konten->perEmail($email);
        // Auch ohne passendes Konto einen Hash prüfen: gleiche Antwortzeit, E-Mail nicht per Zeitmessung erkennbar
        $passwortOk = password_verify((string) $request->post('passwort', ''), $benutzer['passwort_hash'] ?? Konten::KEIN_KONTO_HASH);
        if ($benutzer === null || !$passwortOk) {
            $schutz->fehlschlag($ziel, $ip);
            throw new FachFehler('E-Mail oder Passwort ist falsch.');
        }
        $schutz->erfolg($ziel, $ip);
        if ((int) ($benutzer['bestaetigung_offen'] ?? 0)) {
            throw new FachFehler('Bitte zuerst die E-Mail-Adresse bestätigen – den Link haben Sie per E-Mail erhalten. Einen neuen Link gibt es unter „Passwort vergessen“.');
        }
        $rechte = $konten->rechte((int) $benutzer['id']) ?? throw new FachFehler('E-Mail oder Passwort ist falsch.');
        $istLeitung = $rechte['admin'] || $rechte['turniere'] !== [];
        // selbst angelegtes Dojo-Konto (Online-Anmeldung) sieht vor der Zuordnung zum Dojo seine eigenen Meldungen
        $selbstAngelegt = $benutzer['ablauf_am'] !== null;
        if (!$istLeitung && $rechte['verein'] === null && !$selbstAngelegt) {
            throw new FachFehler('Für dieses Konto ist noch kein Bereich freigeschaltet. Die Administration kann ihm ein Dojo zuordnen oder eine Turnierleitung es einem Turnier.');
        }
        $zurueck = self::internerPfad($request->text('zurueck'));
        $id = (int) $benutzer['id'];
        // Zweiter Faktor (Notschalter: 'mfa' => false in config.php): App, sonst bei Pflicht Code per E-Mail, sonst App einrichten
        if ($this->app->zweiterFaktorAktiv() && !(new KontoVertrauen($this->db))->vertraut($id)) {
            if ($this->app->authenticatorApp()->aktiv($id)) {
                ZweiterFaktor::warten($id, $zurueck, 'app');
                return $this->weiter('login/code');
            }
            if ($this->app->zweiterFaktorPflicht()) {
                $email = (string) ($benutzer['email'] ?? '');
                if ($this->app->postausgang() !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                    $this->codeSenden($id, $email, $rechte['name'], $zurueck);
                    return $this->weiter('login/code');
                }
                ZweiterFaktor::warten($id, $zurueck, 'einrichten');
                return $this->weiter('login/app-einrichten');
            }
        }
        return $this->anmeldungAbschliessen($id, $zurueck);
    }

    /** Eingabe des Codes (E-Mail oder Authenticator-App). */
    public function codeFormular(Request $request): Response
    {
        $offen = ZweiterFaktor::offen();
        if ($offen === null) {
            return $this->weiter('login');
        }
        if ($offen['art'] === 'einrichten') {
            return $this->weiter('login/app-einrichten');
        }
        $email = (string) ($this->db->wert('SELECT email FROM benutzer WHERE id = ?', [$offen['benutzer']]) ?? '');
        return $this->seite($offen['art'] === 'app' ? 'auth/app_code' : 'auth/code', [
            'email' => self::emailVerdeckt($email),
            'mailMoeglich' => $this->app->postausgang() !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
            'minuten' => intdiv(ZweiterFaktor::GUELTIG_SEKUNDEN, 60),
            'tage' => KontoVertrauen::TAGE,
        ]);
    }

    public function code(Request $request): Response
    {
        $offen = ZweiterFaktor::gueltig();
        if (($offen['art'] ?? 'mail') === 'app') {
            $id = (int) $offen['benutzer'];
            if (!$this->app->authenticatorApp()->pruefen($id, $request->text('code'))) {
                ZweiterFaktor::fehlversuch();
            }
            ZweiterFaktor::verwerfen();
            $ergebnis = ['benutzer' => $id, 'zurueck' => (string) $offen['zurueck']];
            $uebrig = $this->app->authenticatorApp()->wiederherstellungOffen($id);
            if (preg_match('/[a-z]/i', $request->text('code'))) {
                Session::flash('hinweis', "Mit Wiederherstellungscode angemeldet – noch $uebrig übrig. Unter „Konto & Sicherheit“ die App neu einrichten bzw. neue Codes erzeugen.");
            }
        } else {
            $ergebnis = ZweiterFaktor::pruefen($request->text('code'));
        }
        if ($request->post('vertrauen') !== null) {
            (new KontoVertrauen($this->db))->merken($ergebnis['benutzer'], self::geraetName($request->header('user-agent') ?? ''));
            (new Protokoll($this->db))->eintragen(null, 'benutzer', $ergebnis['benutzer'], 'geraet_vertraut', ['tage' => KontoVertrauen::TAGE]);
        }
        return $this->anmeldungAbschliessen($ergebnis['benutzer'], $ergebnis['zurueck']);
    }

    /** Neuen Code per E-Mail schicken – auch statt der App (höchstens 3 je 10 Minuten und Konto). */
    public function codeNeu(Request $request): Response
    {
        $offen = ZweiterFaktor::offen() ?? throw new FachFehler('Bitte erneut mit E-Mail und Passwort anmelden.');
        if ($offen['art'] === 'einrichten') {
            throw new FachFehler('Bitte zuerst die Authenticator-App einrichten.');
        }
        $drossel = new Drossel($this->db);
        $drossel->pruefen('mfa-neu:' . $offen['benutzer'], 3, 600);
        $drossel->zaehlen('mfa-neu:' . $offen['benutzer'], 600);
        $konto = $this->db->eine('SELECT email, name FROM benutzer WHERE id = ?', [$offen['benutzer']]) ?? throw new FachFehler('Konto nicht gefunden.');
        if (filter_var((string) $konto['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new FachFehler('Für dieses Konto ist keine E-Mail-Adresse hinterlegt.');
        }
        $this->codeSenden($offen['benutzer'], (string) $konto['email'], (string) $konto['name'], $offen['zurueck']);
        $this->erfolg($offen['art'] === 'app' ? 'Code per E-Mail ist unterwegs.' : 'Neuer Code ist unterwegs – der vorige gilt nicht mehr.');
        return $this->weiter('login/code');
    }

    /** Pflicht ohne App und ohne E-Mail: Authenticator-App jetzt einrichten (Teil der Anmeldung). */
    public function appEinrichtenFormular(Request $request): Response
    {
        $offen = ZweiterFaktor::offen();
        if ($offen === null || $offen['art'] !== 'einrichten') {
            return $this->weiter('login');
        }
        $email = (string) ($this->db->wert('SELECT COALESCE(email, login) FROM benutzer WHERE id = ?', [$offen['benutzer']]) ?? '');
        $geheimnis = ZweiterFaktor::einrichtGeheimnis();
        return $this->seite('auth/app_einrichten', [
            'qr' => QrCode::svg(Totp::uri($geheimnis, $email)),
            'schluessel' => Totp::lesbar($geheimnis),
            'ziel' => url('login/app-einrichten'),
            'imLogin' => true,
        ]);
    }

    public function appEinrichten(Request $request): Response
    {
        $offen = ZweiterFaktor::gueltig();
        if (($offen['art'] ?? '') !== 'einrichten' || !isset($offen['geheimnis'])) {
            throw new FachFehler('Bitte erneut mit E-Mail und Passwort anmelden.');
        }
        $id = (int) $offen['benutzer'];
        try {
            $codes = $this->app->authenticatorApp()->einrichten($id, (string) $offen['geheimnis'], $request->text('code'));
        } catch (FachFehler $f) {
            ZweiterFaktor::fehlversuch();
        }
        ZweiterFaktor::verwerfen();
        $ziel = $this->anmeldungAbschliessen($id, (string) $offen['zurueck']);
        return $this->seite('auth/wiederherstellung', ['codes' => $codes, 'weiter' => $ziel->headers['Location'] ?? url('/')]);
    }

    private function codeSenden(int $benutzerId, string $email, string $name, string $zurueck): void
    {
        $transport = Postausgang::transport($this->app->mailKonfiguration(), $this->app->wurzel)
            ?? throw new FachFehler('Der E-Mail-Versand ist nicht eingerichtet.');
        $code = ZweiterFaktor::starten($benutzerId, $zurueck);
        try {
            $transport->senden($email, 'Ihr Anmeldecode – Taikai – Karate', ZweiterFaktor::mailText($code, $name));
        } catch (\Throwable $f) {
            ZweiterFaktor::verwerfen();
            error_log('Anmeldecode nicht verschickt: ' . $f->getMessage());
            throw new FachFehler('Der Anmeldecode konnte nicht per E-Mail verschickt werden (E-Mail-Versand gestört). Bitte die Administration informieren.');
        }
    }

    /** Angemeldet: Sitzung setzen, aufräumen, weiterleiten. */
    private function anmeldungAbschliessen(int $benutzerId, string $zurueck): Response
    {
        $konten = new Konten($this->db);
        $rechte = $konten->rechte($benutzerId) ?? throw new FachFehler('Das Konto ist nicht mehr aktiv.');
        $istLeitung = $rechte['admin'] || $rechte['turniere'] !== [];
        Session::anmelden($benutzerId, $rechte['name'], $rechte['admin'], $rechte['turniere'], $rechte['verein']);
        (new Protokoll($this->db))->aufraeumen(); // Aufbewahrungsfrist globaler Einträge (ohne Cronjob)
        (new DojoKonten($this->db))->aufraeumen(); // abgelaufene Dojo-Konten (ohne Cronjob)
        // reines Coach-Konto: Startseite der Leitung ist gesperrt, also zu „Meine Meldungen“
        return $this->weiter(!$istLeitung && $zurueck === '/' ? 'coach' : $zurueck);
    }

    /** Grobe Gerätebezeichnung aus dem User-Agent (nur zur Anzeige, kein Sicherheitsmerkmal). */
    public static function geraetName(string $agent): string
    {
        $system = match (true) {
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'unbekanntes System',
        };
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };
        return $browser . ' auf ' . $system;
    }

    /** „r***@gmail.com“ – Hinweis, wohin der Code ging, ohne die Adresse ganz zu zeigen. */
    public static function emailVerdeckt(string $email): string
    {
        [$lokal, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($lokal, 0, 1) . '***@' . $domain;
    }

    /** Nur interne Pfade zulassen – kein offener Redirect (auch nicht „//fremd“ oder „/\fremd“). */
    public static function internerPfad(string $pfad): string
    {
        return preg_match('#^/(?!/)[\w/\-]*$#', $pfad) ? $pfad : '/';
    }

    public function logout(Request $request): Response
    {
        Session::abmelden();
        return $this->weiter('login');
    }

    private function einrichtung(): Einrichtung
    {
        return new Einrichtung($this->db, $this->app->wurzel . '/data/einrichtung.token');
    }
}
