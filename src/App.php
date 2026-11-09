<?php

declare(strict_types=1);

namespace Wettkampf;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App as SlimApp;
use Slim\Factory\AppFactory;
use Wettkampf\Betrieb\AutoSicherung;
use Wettkampf\Durchfuehrung\AusrufService;
use Wettkampf\Durchfuehrung\GeraeteKopplung;
use Wettkampf\Identitaet\AuthenticatorApp;
use Wettkampf\Identitaet\Konten;
use Wettkampf\Kommunikation\FeedbackService;
use Wettkampf\Turnier\SystemEinstellungen;
use Wettkampf\Turnier\TurnierPhaseService;
use Wettkampf\Kern\Http\Middleware\Anfragekontext;
use Wettkampf\Kern\Http\Middleware\CloudflareAccessPruefung;
use Wettkampf\Kern\Http\Middleware\EinmaligeBefehle;
use Wettkampf\Kern\Http\Middleware\Fehlerbehandlung;
use Wettkampf\Kern\Http\Middleware\OeffentlicheAbfragen;
use Wettkampf\Kern\Http\Middleware\SicherheitsHeader;
use Wettkampf\Kern\Http\Middleware\Zugang;
use Wettkampf\Kern\BotSchutz;
use Wettkampf\Kern\Instanz;
use Wettkampf\Kern\Mail\Postausgang;
use Wettkampf\Kern\Tresor;
use Wettkampf\Kommunikation\FeedbackMail;
use Wettkampf\Kommunikation\MailEinstellungen;
use Wettkampf\Sync\Http\FuehrungPruefung;
use Wettkampf\Sync\SendeService;
use Wettkampf\Kern\Http\PsrBruecke;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Router;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Url;
use Wettkampf\Kern\Http\View;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Db\Migrator;

/**
 * Anwendung: Konfiguration, Datenbank, Templates und die Slim-Anwendung mit Middleware.
 *
 * Ablauf einer Anfrage (außen → innen): Sicherheits-Header → Cloudflare Access →
 * Fehlerbehandlung → Anfragekontext (Sitzung, Ersteinrichtung) → Routing (Slim) →
 * Zugang/CSRF je Route → Controller.
 */
final class App
{
    /**
     * Fachliche Module (src/<Modul>/, je mit routen.php). Die Reihenfolge der Routen ist für
     * Slim unerheblich, solange sich Muster nicht überdecken – geprüft in AppTest.
     */
    public const MODULE = ['Identitaet', 'Oeffentlich', 'Meldung', 'Durchfuehrung', 'Kommunikation', 'Turnier', 'Ausgaben', 'Betrieb', 'Sync'];

    public readonly Router $router;
    public readonly View $view;
    /** @var SlimApp<ContainerInterface|null>|null */
    private ?SlimApp $slim = null;

    /** @param array<string, mixed> $config */
    public function __construct(
        public readonly string $wurzel,
        public readonly array $config,
        public readonly Database $db,
    ) {
        $this->view = new View($wurzel . '/templates');
        $this->router = self::routen();
    }

    /** Routentabelle aller Module. */
    public static function routen(): Router
    {
        $router = new Router();
        foreach (self::MODULE as $modul) {
            (require __DIR__ . "/$modul/routen.php")($router);
        }
        return $router;
    }

    public static function erstellen(string $wurzel): self
    {
        $config = self::konfigurationLaden($wurzel);
        date_default_timezone_set($config['zeitzone']);
        Url::setzeBasis($config['basis_url'] ?? Url::erkennen());

        $db = Database::ausKonfiguration($config['db']);
        (new Migrator($db, $wurzel . '/migrations'))->migrieren();
        return new self($wurzel, $config, $db);
    }

    /** @return array<string, mixed> */
    public static function konfigurationLaden(string $wurzel): array
    {
        $standard = [
            'db' => ['driver' => 'sqlite', 'sqlite_pfad' => $wurzel . '/data/wettkampf.sqlite'],
            'basis_url' => null,
            'zeitzone' => 'Europe/Berlin',
            'debug' => false,
            'cloudflare_access' => ['aktiv' => false, 'team_domain' => '', 'aud' => ''],
            // Header mit der echten Client-IP hinter einem Proxy (z. B. 'cf-connecting-ip' bei Cloudflare);
            // nur setzen, wenn alle Anfragen über diesen Proxy kommen – sonst ist der Header fälschbar
            'client_ip_header' => null,
            // Statusseite und öffentliche API: höchstens so viele Abfragen je IP und Minute (0 = keine Grenze)
            'oeffentlich_max_je_minute' => OeffentlicheAbfragen::MAX_JE_MINUTE,
            // Öffentliche Adresse der Installation für Links in E-Mails (nie aus dem Host-Header der Anfrage)
            'oeffentliche_url' => null,
            'mail' => ['transport' => null],
            'bot_schutz' => ['aktiv' => true, 'schwierigkeit' => 16, 'mindestzeit' => 3],
        ];
        $datei = $wurzel . '/config/config.php';
        if (!is_file($datei)) {
            return $standard;
        }
        $eigene = require $datei;
        return array_replace_recursive($standard, is_array($eigene) ? $eigene : []);
    }

    /**
     * Slim-Anwendung mit allen Routen und der Middleware (einmal je Anfrage aufgebaut).
     * Ohne DI-Container: Controller werden direkt mit der App erzeugt (Zielarchitektur Kap. 4.3).
     *
     * @return SlimApp<ContainerInterface|null>
     */
    public function slim(): SlimApp
    {
        if ($this->slim !== null) {
            return $this->slim;
        }
        $slim = AppFactory::create();
        $slim->setBasePath(Url::basis());

        $zugang = [];
        foreach (Router::ZUGANG as $stufe) {
            $zugang[$stufe] = new Zugang($this, $stufe);
        }
        $fuehrung = new FuehrungPruefung($this);
        $einmalig = new EinmaligeBefehle($this->db);
        foreach ($this->router->alle() as $route) {
            $ziel = $route['ziel'];
            $slim->map([$route['methode']], $route['muster'], function (ServerRequestInterface $request, ResponseInterface $response, array $werte) use ($ziel): ResponseInterface {
                [$klasse, $methode] = $ziel;
                $antwort = (new $klasse($this))->$methode(PsrBruecke::request($request), ...Router::parameter($werte));
                return PsrBruecke::antwort($antwort, $response);
            })->add($einmalig)->add($fuehrung)->add($zugang[$route['zugang']]); // zuletzt hinzugefügt läuft zuerst: Zugang, Führung, Befehls-ID
        }

        // Slim führt zuletzt hinzugefügte Middleware zuerst aus: Reihenfolge hier von innen nach außen
        $slim->addRoutingMiddleware();
        // nach der Antwort: automatische Sicherung, wenn fällig (Hallen-Laptop, SQLite)
        $slim->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
            $antwort = $handler->handle($request);
            AutoSicherung::ausKonfiguration($this->db, $this->config, $this->wurzel)?->beiBedarf();
            try {
                $this->postausgang()?->versenden();
            } catch (\Throwable $f) {
                error_log('Mailversand: ' . $f->getMessage()); // die Antwort ist schon fertig – nicht daran scheitern
            }
            return $antwort;
        });
        $slim->add(new OeffentlicheAbfragen($this));
        $slim->add(new Anfragekontext($this));
        $slim->add(new Fehlerbehandlung($this));
        $slim->add(new CloudflareAccessPruefung((array) ($this->config['cloudflare_access'] ?? []), $this->wurzel . '/data/cloudflare-access-certs.json'));
        $slim->add(new SicherheitsHeader());
        return $this->slim = $slim;
    }

    /** Eine Anfrage vollständig verarbeiten (auch für Tests). */
    public function verarbeiten(ServerRequestInterface $request): ResponseInterface
    {
        return $this->slim()->handle($request);
    }

    /** IP des Aufrufers (für die Begrenzung von Fehlversuchen). */
    public function clientIp(Request $request): string
    {
        $header = $this->config['client_ip_header'] ?? null;
        $ip = is_string($header) && $header !== '' ? trim(explode(',', (string) $request->header(strtolower($header)))[0]) : '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : (string) ($_SERVER['REMOTE_ADDR'] ?? 'unbekannt');
    }

    /**
     * Anmeldungen bei jeder Anfrage gegen die Datenbank prüfen (OWASP A07): deaktivierte oder
     * gelöschte Konten, gelöschte Tatamis und geänderte Tatami-PINs melden sofort ab.
     */
    public function anmeldungenPruefen(): void
    {
        // Direkt aus der Sitzung lesen: auch Anmeldungen aus der Zeit vor den Rollen je Turnier werden übernommen
        $kontoId = $_SESSION['benutzer']['id'] ?? null;
        if ($kontoId !== null) {
            $rechte = (new Konten($this->db))->rechte((int) $kontoId);
            if ($rechte === null) {
                unset($_SESSION['benutzer']);
                Session::flash('hinweis', 'Das Konto wurde deaktiviert oder gelöscht – bitte neu anmelden.');
            } else {
                Session::rechteAktualisieren($rechte['name'], $rechte['admin'], $rechte['turniere'], $rechte['verein']);
            }
        }
        $tisch = Session::tisch();
        if ($tisch === null) {
            // gekoppeltes, freigegebenes Gerät meldet sich über sein Cookie selbst wieder an (nicht, solange ein Konto angemeldet ist)
            $geraet = $kontoId === null ? $this->geraeteKopplung()->diesesGeraet() : null;
            if ($geraet !== null && $geraet['status'] === 'frei' && $this->geraeteKopplung()->gueltig((int) $geraet['id'])) {
                Session::geraetAnmelden(
                    (int) $geraet['turnier_id'],
                    (int) $geraet['tatami_id'],
                    GeraeteKopplung::bezeichnung((string) $geraet['tatami'], (string) $geraet['name'], (int) $geraet['id']),
                    (int) $geraet['id'],
                    (string) $geraet['rolle'],
                );
            }
            return;
        }
        if (isset($tisch['geraet'])) {
            if (!$this->geraeteKopplung()->gueltig((int) $tisch['geraet'])) {
                unset($_SESSION['tisch']);
                Session::flash('hinweis', 'Dieses Gerät wurde von der Wettkampfleitung gesperrt oder das Turnier ist abgeschlossen – bitte bei Bedarf neu koppeln.');
            }
            return;
        }
        $tatami = $this->db->eine(
            'SELECT ta.pin_hash, tu.tisch_pin_erlaubt FROM tatami ta JOIN turnier tu ON tu.id = ta.turnier_id WHERE ta.id = ? AND ta.turnier_id = ?',
            [$tisch['tatami_id'], $tisch['turnier_id']],
        );
        if ($tatami === null) {
            unset($_SESSION['tisch']);
            Session::flash('hinweis', 'Die Tatami gibt es nicht mehr (Turnier gelöscht) – bitte die Tatami des aktuellen Turniers wählen und neu anmelden.');
        } elseif (!(int) $tatami['tisch_pin_erlaubt']) {
            unset($_SESSION['tisch']);
            Session::flash('hinweis', 'Die Anmeldung per PIN ist für dieses Turnier abgeschaltet – bitte das Gerät per QR-Code koppeln.');
        } elseif (!Session::tischPinGueltig($tatami['pin_hash'])) {
            unset($_SESSION['tisch']);
            Session::flash('hinweis', 'Die PIN dieser Tatami wurde geändert – bitte mit der neuen PIN anmelden.');
        }
    }

    /**
     * Daten, die jedes Template braucht (Feedback-Knopf, Farbschema, Bereiche der Statusseite).
     * Hier statt im Kern, weil sie aus Fachmodulen kommen – App ist der Einstiegspunkt, der alle Module kennt.
     */
    public function gemeinsameDatenTeilen(): void
    {
        $system = new SystemEinstellungen($this->db);
        $this->view->teilen('feedbackAktiv', $this->feedbackAktiv());
        $this->view->teilen('feedbackAdresse', FeedbackMail::adresse($this->config));
        $this->view->teilen('farbenCss', $system->farbenCss());
        $this->view->teilen('statusBereiche', $system->statusBereiche());
        $this->view->teilen('feedbackNeu', Session::istAdmin() ? (new FeedbackService($this->db))->anzahlNeu() : 0);
        $this->view->teilen('instanzId', $this->instanz()->id()); // Hinweisband „nur lesend“ (Schritt 9c)
    }

    /**
     * Kopfzeile der Leitungsseiten eines Turniers: Rückmeldungen „nicht erschienen“ aus dem
     * Ausruf und Hinweis „alle Kata gelaufen – Pause möglich“.
     *
     * @return array{nichtErschienenAnzahl: int, kataBeendet: array<mixed>|null, syncStand: array<string, mixed>|null}
     */
    public function turnierKopf(int $turnierId): array
    {
        return [
            'syncStand' => (new SendeService($this->db, $this->instanz()))->stand($turnierId), // Hallen-Laptop: Übertragung an Online
            'nichtErschienenAnzahl' => (new AusrufService($this->db))->anzahlNichtErschienen($turnierId),
            'kataBeendet' => (new TurnierPhaseService($this->db))->kataBeendet($turnierId),
        ];
    }

    /** E-Mail-Warteschlange; null, wenn kein Versand eingerichtet ist (config 'mail' und 'oeffentliche_url'). */
    public function postausgang(): ?Postausgang
    {
        return Postausgang::ausKonfiguration($this->db, $this->mailKonfiguration(), $this->wurzel);
    }

    /** Versand in config.php festgelegt (Betreiber)? Dann ist er in der Oberfläche nur sichtbar, nicht änderbar. */
    public function mailFestgelegt(): bool
    {
        return ($this->config['mail']['transport'] ?? null) !== null;
    }

    /** Öffentliche Adresse in config.php festgelegt? */
    public function adresseFestgelegt(): bool
    {
        return ($this->config['oeffentliche_url'] ?? null) !== null && $this->config['oeffentliche_url'] !== '';
    }

    /**
     * Wirksame Konfiguration für den Mailversand: Was in config.php steht, gilt; sonst die Einstellungen aus
     * System → E-Mail-Versand (Passwort dort verschlüsselt gespeichert).
     *
     * @return array<string, mixed>
     */
    public function mailKonfiguration(): array
    {
        $config = $this->config;
        if ($this->mailFestgelegt() && $this->adresseFestgelegt()) {
            return $config;
        }
        $oberflaeche = (new MailEinstellungen($this->db, $this->tresor()))->fuerVersand();
        if (!$this->mailFestgelegt()) {
            $config['mail'] = $oberflaeche['mail'];
        }
        if (!$this->adresseFestgelegt()) {
            $config['oeffentliche_url'] = $oberflaeche['oeffentliche_url'];
        }
        return $config;
    }

    /**
     * Zweiter Faktor bei der Anmeldung überhaupt eingeschaltet (Authenticator-App bzw. Code per E-Mail).
     * Notschalter, falls sich niemand mehr anmelden kann (z. B. Versand gestört): 'mfa' => false in config.php.
     */
    public function zweiterFaktorAktiv(): bool
    {
        return ($this->config['mfa'] ?? true) !== false;
    }

    /**
     * Zweiter Faktor für alle Konten Pflicht (System → Anmeldung, Standard: ja): ohne App gibt es den Code per E-Mail,
     * ohne E-Mail-Versand wird die App bei der Anmeldung eingerichtet. Sonst fragt nur, wer eine App eingerichtet hat.
     */
    public function zweiterFaktorPflicht(): bool
    {
        return $this->zweiterFaktorAktiv() && (new SystemEinstellungen($this->db))->zweiterFaktorPflicht();
    }

    public function authenticatorApp(): AuthenticatorApp
    {
        return new AuthenticatorApp($this->db, $this->tresor());
    }

    /**
     * Hinweis für die Turnier-Übersicht: Auf dem Hallen-Laptop gibt es meist keinen E-Mail-Versand – bei Pflicht braucht dort
     * jedes Konto eine Authenticator-App. Wer noch keine hat (ich bzw. die Turnierleitung), sollte sie vorher einrichten.
     *
     * Konten werden nicht zum Hallen-Laptop übertragen: Ohne E-Mail-Versand (meist der Laptop selbst) nennt der Hinweis die Konten
     * ohne App; online erscheint er nur als Vorab-Erinnerung in den 14 Tagen vor dem Turnier.
     *
     * @return array{ich: bool, andere: list<string>, ohneMail: bool}|null null, wenn nichts zu tun ist
     */
    public function zweiterFaktorHinweis(int $turnierId): ?array
    {
        $ich = (int) (Session::benutzer()['id'] ?? 0);
        if (!$this->zweiterFaktorPflicht() || $ich === 0) {
            return null;
        }
        if ($this->postausgang() !== null) {
            $datum = (string) ($this->db->wert('SELECT datum FROM turnier WHERE id = ?', [$turnierId]) ?? '');
            $bald = $datum >= date('Y-m-d') && $datum <= date('Y-m-d', strtotime('+14 days'));
            return $bald ? ['ich' => false, 'andere' => [], 'ohneMail' => false] : null;
        }
        $ohneApp = $this->db->alle(
            'SELECT b.id, b.name FROM turnier_rolle r JOIN benutzer b ON b.id = r.benutzer_id
              WHERE r.turnier_id = ? AND r.rolle = ? AND b.aktiv = 1 AND b.totp_geheimnis IS NULL ORDER BY b.name',
            [$turnierId, Konten::ROLLE_LEITUNG],
        );
        $ichOhne = $this->db->wert('SELECT 1 FROM benutzer WHERE id = ? AND totp_geheimnis IS NULL', [$ich]) !== null;
        $andere = array_values(array_map(static fn($b) => (string) $b['name'], array_filter($ohneApp, static fn($b) => (int) $b['id'] !== $ich)));
        if (!$ichOhne && $andere === []) {
            return null;
        }
        return ['ich' => $ichOhne, 'andere' => $andere, 'ohneMail' => true];
    }

    /**
     * Feedback-Knopf wirksam: in den Systemeinstellungen eingeschaltet UND E-Mail-Versand eingerichtet –
     * die Meldungen gehen per Mail an das Taikai-Team (FeedbackMail).
     */
    public function feedbackAktiv(): bool
    {
        return (new SystemEinstellungen($this->db))->feedbackAktiv() && $this->postausgang() !== null;
    }

    /** Verschlüsselung von Geheimnissen in der Datenbank mit dem Schlüssel dieser Installation (data/). */
    public function tresor(): Tresor
    {
        return new Tresor($this->instanz()->schluessel());
    }

    /** QR-Kopplung von Tisch- und Ausruf-Geräten (HMAC mit dem Schlüssel dieser Installation). */
    public function geraeteKopplung(): GeraeteKopplung
    {
        return new GeraeteKopplung($this->db, $this->instanz()->schluessel());
    }

    /** Schutz öffentlicher Formulare (config 'bot_schutz'). */
    public function botSchutz(): BotSchutz
    {
        return BotSchutz::ausKonfiguration($this->db, $this->config, $this->instanz()->schluessel());
    }

    /** Kennung und Schlüssel dieser Installation (config instanz_ordner, Standard data/). */
    public function instanz(): Instanz
    {
        return new Instanz((string) ($this->config['instanz_ordner'] ?? $this->wurzel . '/data'));
    }

    public function fehlerAntwort(bool $istApi, int $status, string $text): Response
    {
        if ($istApi) {
            return Response::json(['fehler' => $text], $status);
        }
        return Response::html($this->view->render('fehler', ['status' => $status, 'text' => $text]), $status);
    }

    public function hatBenutzer(): bool
    {
        return (int) $this->db->wert('SELECT COUNT(*) FROM benutzer') > 0;
    }
}
