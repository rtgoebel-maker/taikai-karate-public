<?php

declare(strict_types=1);

namespace Wettkampf\Identitaet\Http;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Identitaet\DojoKonten;
use Wettkampf\Identitaet\Konten;
use Wettkampf\Kern\Drossel;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Mail\Postausgang;

/**
 * Öffentliche Kontoseiten: E-Mail-Adresse bestätigen, „Passwort vergessen“, neues Passwort setzen.
 * Nur mit eingerichtetem E-Mail-Versand. Antworten verraten nicht, ob eine Adresse bekannt ist (OWASP A07).
 */
final class KontoController extends Controller
{
    /** Höchstens so viele „Passwort vergessen“ je IP bzw. je Adresse und Stunde. */
    private const MAX_JE_IP = 10;
    private const MAX_JE_ADRESSE = 3;

    public function bestaetigen(Request $request, string $token): Response
    {
        (new DojoKonten($this->db))->bestaetigen($token);
        $this->erfolg('Danke – die E-Mail-Adresse ist bestätigt. Bitte jetzt anmelden; unter „Meine Meldungen“ sehen Sie den Bearbeitungsstand.');
        return $this->weiter('login');
    }

    public function vergessenFormular(Request $request): Response
    {
        $this->post();
        return $this->seite('auth/passwort_vergessen', ['botschutz' => $this->app->botSchutz()->felder('passwort')]);
    }

    public function vergessen(Request $request): Response
    {
        $post = $this->post();
        $this->app->botSchutz()->pruefen($request->post, 'passwort', 2);
        $email = Konten::email($request->text('email'));
        $drossel = new Drossel($this->db);
        $ip = 'passwort-ip:' . $this->app->clientIp($request);
        $adresse = 'passwort-mail:' . hash('sha256', $email);
        $drossel->pruefen($ip, self::MAX_JE_IP, 3600);
        $drossel->zaehlen($ip, 3600);
        // je Adresse still begrenzen: kein Hinweis, ob die Adresse bekannt ist, keine Mailflut für das Postfach
        try {
            $drossel->pruefen($adresse, self::MAX_JE_ADRESSE, 3600);
            $drossel->zaehlen($adresse, 3600);
            if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                (new DojoKonten($this->db))->passwortVergessen($email, $post);
            }
        } catch (FachFehler) {
            // Grenze je Adresse erreicht: nichts verschicken, gleiche Antwort
        }
        $this->erfolg('Falls es ein Konto mit dieser E-Mail-Adresse gibt, ist eine E-Mail mit einem Link unterwegs (bitte auch im Spam-Ordner nachsehen).');
        return $this->weiter('login');
    }

    public function neuFormular(Request $request, string $token): Response
    {
        if (!(new DojoKonten($this->db))->passwortTokenGueltig($token)) {
            throw new NichtGefunden('Der Link ist ungültig oder abgelaufen – bitte unter „Passwort vergessen“ einen neuen anfordern.');
        }
        return $this->seite('auth/passwort_neu', ['token' => $token, 'min' => Konten::MIN_PASSWORT]);
    }

    public function neu(Request $request, string $token): Response
    {
        (new DojoKonten($this->db))->passwortNeu($token, (string) $request->post('passwort', ''), (string) $request->post('passwort2', ''));
        $this->erfolg('Neues Passwort gespeichert – bitte damit anmelden.');
        return $this->weiter('login');
    }

    private function post(): Postausgang
    {
        return $this->app->postausgang() ?? throw new NichtGefunden('Diese Funktion ist hier nicht eingerichtet (kein E-Mail-Versand). Bitte an die Wettkampfleitung wenden.');
    }
}
