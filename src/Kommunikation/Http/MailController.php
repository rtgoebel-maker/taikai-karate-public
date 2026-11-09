<?php

declare(strict_types=1);

namespace Wettkampf\Kommunikation\Http;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Identitaet\AnmeldeSchutz;
use Wettkampf\Identitaet\Konten;
use Wettkampf\Kern\Drossel;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Mail\Postausgang;
use Wettkampf\Kern\Mail\SmtpDiagnose;
use Wettkampf\Kommunikation\MailEinstellungen;

/**
 * System → E-Mail-Versand (nur Administration): Postfach speichern und Testmail senden.
 * Jede Änderung bestätigt die Administration mit dem eigenen Passwort (Schutz bei übernommener bzw. offen gelassener
 * Sitzung, OWASP A07); Fehlversuche zählen wie beim Login.
 */
final class MailController extends Controller
{
    public function speichern(Request $request): Response
    {
        if ($this->app->mailFestgelegt() && $this->app->adresseFestgelegt()) {
            throw new FachFehler('Der E-Mail-Versand ist in config/config.php festgelegt und lässt sich hier nicht ändern.');
        }
        $this->passwortBestaetigen($request);
        $daten = $request->post;
        $aktuell = (new MailEinstellungen($this->db, $this->app->tresor()))->anzeige();
        // in config.php festgelegte Teile bleiben unverändert
        if ($this->app->mailFestgelegt()) {
            $daten = array_intersect_key($daten, ['url' => 1]) + $aktuell + ['transport' => ''];
        }
        (new MailEinstellungen($this->db, $this->app->tresor()))->speichern($daten, Session::akteur(), $this->app->adresseFestgelegt());
        $this->erfolg('E-Mail-Versand gespeichert. Mit „Testmail senden“ lässt sich prüfen, ob das Postfach erreichbar ist.');
        return $this->weiter('system');
    }

    /** Testmail an die Adresse des angemeldeten Kontos – direkt, ohne Warteschlange, damit Fehler sofort sichtbar sind. */
    public function test(Request $request): Response
    {
        $drossel = new Drossel($this->db);
        $ziel = 'mailtest:' . (int) (Session::benutzer()['id'] ?? 0);
        $drossel->pruefen($ziel, 5, 600);
        $drossel->zaehlen($ziel, 600);
        $config = $this->app->mailKonfiguration();
        $transport = Postausgang::transport($config, $this->app->wurzel) ?? throw new FachFehler('Der E-Mail-Versand ist noch nicht eingerichtet bzw. ausgeschaltet.');
        $email = (string) $this->db->wert('SELECT email FROM benutzer WHERE id = ?', [(int) (Session::benutzer()['id'] ?? 0)]);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new FachFehler('Für das angemeldete Konto ist keine gültige E-Mail-Adresse hinterlegt.');
        }
        try {
            $transport->senden($email, 'Testmail von Taikai – Karate', "Guten Tag,\n\ndiese Testmail bestätigt, dass der E-Mail-Versand funktioniert.\n\n"
                . 'Öffentliche Adresse für Links: ' . ($config['oeffentliche_url'] ?: '(fehlt – bitte eintragen)') . "\nGesendet: " . date('d.m.Y H:i:s') . "\n");
        } catch (\Throwable $f) {
            throw new FachFehler('Testmail fehlgeschlagen: ' . self::fehlertext($f->getMessage(), (string) ($config['mail']['passwort'] ?? '')));
        }
        $this->erfolg("Testmail an $email verschickt – bitte im Postfach (auch im Spam-Ordner) nachsehen.");
        return $this->weiter('system');
    }

    /** Verbindungsdiagnose Schritt für Schritt (ohne Mail zu verschicken), Ergebnis als eigene Seite. */
    public function diagnose(Request $request): Response
    {
        $drossel = new Drossel($this->db);
        $ziel = 'maildiagnose:' . (int) (Session::benutzer()['id'] ?? 0);
        $drossel->pruefen($ziel, 10, 600);
        $drossel->zaehlen($ziel, 600);
        $config = $this->app->mailKonfiguration();
        $mail = (array) ($config['mail'] ?? []);
        if (($mail['transport'] ?? null) !== 'smtp') {
            throw new FachFehler('Die Diagnose prüft den SMTP-Versand – bitte zuerst „SMTP (Postfach)“ einstellen und speichern.');
        }
        @set_time_limit(90);
        return $this->seite('system/mail_diagnose', [
            'ergebnis' => (new SmtpDiagnose($mail))->pruefen(),
            'einstellung' => ['host' => (string) ($mail['host'] ?? ''), 'port' => (int) ($mail['port'] ?? 0), 'verschluesselung' => (string) ($mail['verschluesselung'] ?? ''),
                'benutzer' => (string) ($mail['benutzer'] ?? ''), 'absender' => (string) ($mail['absender'] ?? '')],
            'aktiv' => 'system',
        ]);
    }

    /** Warteschlange: wartende bzw. gescheiterte Mails sofort erneut versuchen oder verwerfen. */
    public function warteschlange(Request $request): Response
    {
        if ($request->text('aktion') === 'verwerfen') {
            $anzahl = $this->db->query('DELETE FROM mail_ausgang WHERE gesendet_am IS NULL')->rowCount();
            $this->erfolg("$anzahl nicht verschickte Mails verworfen.");
            return $this->weiter('system');
        }
        $this->db->query('UPDATE mail_ausgang SET versuche = 0, naechster_versuch = NULL, fehler = NULL WHERE gesendet_am IS NULL');
        $post = $this->app->postausgang() ?? throw new FachFehler('Der E-Mail-Versand ist nicht eingerichtet bzw. ausgeschaltet.');
        $gesendet = $post->versenden(20);
        $offen = (int) $this->db->wert('SELECT COUNT(*) FROM mail_ausgang WHERE gesendet_am IS NULL');
        $fehler = (string) ($this->db->wert('SELECT fehler FROM mail_ausgang WHERE gesendet_am IS NULL AND fehler IS NOT NULL ORDER BY id DESC LIMIT 1') ?? '');
        if ($offen > 0) {
            throw new FachFehler("$gesendet verschickt, $offen weiterhin nicht: " . self::fehlertext($fehler, (string) ($this->app->mailKonfiguration()['mail']['passwort'] ?? ''))
                . ' – „Verbindung prüfen“ zeigt, woran es liegt.');
        }
        $this->erfolg("$gesendet Mails verschickt – die Warteschlange ist leer.");
        return $this->weiter('system');
    }

    private function passwortBestaetigen(Request $request): void
    {
        $id = (int) (Session::benutzer()['id'] ?? 0);
        $schutz = new AnmeldeSchutz($this->db);
        $ziel = 'system-passwort:' . $id;
        $ip = $this->app->clientIp($request);
        $schutz->pruefen($ziel, $ip);
        if (!(new Konten($this->db))->passwortStimmt($id, (string) $request->post('bestaetigung', ''))) {
            $schutz->fehlschlag($ziel, $ip);
            throw new FachFehler('Bitte zur Bestätigung das eigene Passwort richtig eingeben – nichts gespeichert.');
        }
        $schutz->erfolg($ziel, $ip);
    }

    /** Fehlermeldung kurz und ohne Zugangsdaten (kein SMTP-Protokoll, Passwort entfernt). */
    public static function fehlertext(string $text, string $passwort): string
    {
        if ($passwort !== '') {
            $text = str_replace($passwort, '***', $text);
        }
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));
        return mb_substr($text, 0, 200) ?: 'unbekannter Fehler';
    }
}
