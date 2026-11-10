<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung\Http;

use Wettkampf\Durchfuehrung\GeraeteKopplung;
use Wettkampf\Identitaet\AnmeldeSchutz;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Drossel;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Controller;

/**
 * Tischbesatzung: Gerät per QR-Code koppeln (Freigabe durch die Leitung) oder – als Notlösung, je Turnier abschaltbar –
 * per Tatami-PIN anmelden; eine Tatami pro Gerät. Zwei Ansichten:
 * „Eingabe“ (Tablet, Ergebnisse) und „Ausruf“ (Handy, Starter aufrufen).
 */
final class TischController extends Controller
{
    public function anmeldung(Request $request): Response
    {
        $tisch = Session::tisch();
        if ($tisch !== null) {
            // Ausruf-Gerät (QR-Kopplung) hat nur die Ausruf-Ansicht
            $ansicht = ($tisch['rolle'] ?? null) === 'ausruf' ? 'ausruf' : $request->text('ansicht', (string) ($_SESSION['tisch_ansicht'] ?? 'app'));
            return $this->weiter($this->ansicht($ansicht));
        }
        $geraet = $this->app->geraeteKopplung()->diesesGeraet();
        if ($geraet !== null && $geraet['status'] === 'wartet') {
            return $this->weiter('tisch/warten');
        }
        // PIN-Anmeldung nur, wo die Leitung sie als Notlösung erlaubt
        $tatamis = $this->db->alle(
            'SELECT ta.id, ta.bezeichnung, ta.pin_hash, tu.name AS turnier, tu.datum
               FROM tatami ta JOIN turnier tu ON tu.id = ta.turnier_id
              WHERE tu.status <> \'abgeschlossen\' AND tu.tisch_pin_erlaubt = 1
              ORDER BY tu.datum DESC, tu.id DESC, ta.nummer',
        );
        return $this->seite('tisch/anmeldung', ['tatamis' => $tatamis, 'ansicht' => $request->text('ansicht', 'app')]);
    }

    public function anmelden(Request $request): Response
    {
        $tatami = $this->db->eine(
            'SELECT ta.*, tu.name AS turnier, tu.tisch_pin_erlaubt FROM tatami ta JOIN turnier tu ON tu.id = ta.turnier_id WHERE ta.id = ?',
            [(int) $request->zahl('tatami_id')],
        );
        if ($tatami === null) {
            throw new FachFehler('Bitte eine Tatami wählen.');
        }
        if (!(int) $tatami['tisch_pin_erlaubt']) {
            throw new FachFehler('Die Anmeldung per PIN ist für dieses Turnier abgeschaltet – bitte den QR-Code der Wettkampfleitung scannen.');
        }
        if (!$tatami['pin_hash']) {
            throw new FachFehler('Für diese Tatami ist noch keine PIN gesetzt (Turnier → Einstellungen).');
        }
        // Fehlversuche je Tatami und IP in der Datenbank (ein neues Cookie hilft nicht)
        $schutz = new AnmeldeSchutz($this->db);
        $ziel = 'tisch:' . $tatami['id'];
        $ip = $this->app->clientIp($request);
        $schutz->pruefen($ziel, $ip);
        if (!password_verify($request->text('pin'), $tatami['pin_hash'])) {
            $schutz->fehlschlag($ziel, $ip, (int) $tatami['turnier_id']);
            throw new FachFehler('PIN ist falsch.');
        }
        $schutz->erfolg($ziel, $ip);
        $_SESSION['tisch_ansicht'] = $request->text('ansicht') === 'ausruf' ? 'ausruf' : 'app';
        Session::tischAnmelden((int) $tatami['turnier_id'], (int) $tatami['id'], $tatami['bezeichnung'] . ' (Tisch)', $tatami['pin_hash']);
        return $this->weiter($this->ansicht($_SESSION['tisch_ansicht']));
    }

    /** QR-Code gescannt: Tatami und Rolle zeigen, Gerätenamen erfragen (GET ändert nichts). */
    public function koppelnFormular(Request $request, int $tatamiid, string $rolle, string $token): Response
    {
        $tatami = $this->app->geraeteKopplung()->qrPruefen($tatamiid, $rolle, $token);
        return $this->seite('tisch/koppeln', ['tatami' => $tatami, 'rolle' => $rolle, 'token' => $token]);
    }

    /** Kopplung anfragen: Gerät bekommt sein Cookie und wartet auf die Freigabe der Leitung. */
    public function koppeln(Request $request, int $tatamiid, string $rolle, string $token): Response
    {
        $kopplung = $this->app->geraeteKopplung();
        $tatami = $kopplung->qrPruefen($tatamiid, $rolle, $token);
        // Anfragen je IP begrenzen (sonst lässt sich die Freigabeliste der Leitung fluten)
        $drossel = new Drossel($this->db);
        $ziel = 'koppeln:' . $this->app->clientIp($request);
        $drossel->pruefen($ziel, 10, 600);
        $drossel->zaehlen($ziel, 600);
        unset($_SESSION['tisch']);
        $kopplung->anfragen($tatami, $rolle, $request->text('name'));
        return $this->weiter('tisch/warten');
    }

    /** Wartet auf Freigabe: Prüfwort groß zeigen; die Seite lädt sich alle paar Sekunden neu, bis die Leitung freigibt. */
    public function warten(Request $request): Response
    {
        if (Session::tisch() !== null) {
            return $this->weiter($this->ansicht((string) ($_SESSION['tisch_ansicht'] ?? 'app')));
        }
        $geraet = $this->app->geraeteKopplung()->diesesGeraet();
        if ($geraet === null || $geraet['status'] === 'gesperrt') {
            Session::flash('hinweis', $geraet === null
                ? 'Die Anfrage ist abgelaufen oder wurde abgelehnt – bitte den QR-Code erneut scannen.'
                : 'Dieses Gerät wurde gesperrt – bitte den QR-Code erneut scannen und neu freigeben lassen.');
            return $this->weiter('tisch');
        }
        return $this->seite('tisch/warten', ['geraet' => $geraet, 'rollen' => GeraeteKopplung::ROLLEN]);
    }

    public function app(Request $request): Response
    {
        [$tatami, $alsLeitung] = $this->tatami($request);
        $_SESSION['tisch_ansicht'] = 'app';
        return Response::html($this->app->view->render('tisch/app', [
            'tatami' => $tatami,
            'alsLeitung' => $alsLeitung,
            // Korrekturen am Tablet: Leitungs-PIN abfragen, sobald eine vergeben ist
            'pinPflicht' => $tatami['leitung_pin_hash'] !== null || !$alsLeitung,
        ]));
    }

    /** Ansicht „Ausruf“: Starter je Kampf aufrufen und als anwesend bestätigen (fürs Handy). */
    public function ausruf(Request $request): Response
    {
        [$tatami, $alsLeitung] = $this->tatami($request);
        if (!$alsLeitung) {
            $_SESSION['tisch_ansicht'] = 'ausruf';
        }
        return Response::html($this->app->view->render('tisch/ausruf', ['tatami' => $tatami, 'alsLeitung' => $alsLeitung]));
    }

    /**
     * Web-App-Manifest je Ansicht (?ansicht=app|ausruf): auf den Startbildschirm gelegt, startet die Ansicht
     * ohne Adress- und Tableiste des Browsers (Vollbild, sonst eigenständiges Fenster).
     */
    public function manifest(Request $request): Response
    {
        $ausruf = $request->text('ansicht') !== 'app';
        return new Response(json_encode([
            'id' => $ausruf ? url('tisch/ausruf') : url('tisch/app'),
            'name' => $ausruf ? 'Taikai – Ausruf' : 'Taikai – Tisch',
            'short_name' => $ausruf ? 'Ausruf' : 'Tisch',
            'start_url' => $ausruf ? url('tisch/ausruf') : url('tisch/app'),
            'scope' => url('tisch'),
            'display' => 'fullscreen',
            'display_override' => ['fullscreen', 'standalone'],
            'background_color' => '#1d2b45',
            'theme_color' => '#1d2b45',
            'icons' => [['src' => asset($ausruf ? 'ausruf-icon.svg' : 'logo/app-symbol.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml']],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/manifest+json']);
    }

    public function abmelden(Request $request): Response
    {
        $ansicht = (string) ($_SESSION['tisch_ansicht'] ?? 'app');
        // gekoppeltes Gerät: Kopplung lösen, sonst meldet das Cookie es gleich wieder an
        $geraetId = Session::tisch()['geraet'] ?? null;
        if ($geraetId !== null) {
            $this->app->geraeteKopplung()->entkoppeln((int) $geraetId);
        }
        unset($_SESSION['tisch']);
        return $this->weiter('tisch', $ansicht === 'ausruf' ? ['ansicht' => 'ausruf'] : []);
    }

    private function ansicht(string $ansicht): string
    {
        return $ansicht === 'ausruf' ? 'tisch/ausruf' : 'tisch/app';
    }

    /**
     * Tatami der angemeldeten Tischbesatzung bzw. (Leitung) aus ?tatami=.
     *
     * @return array{0: array<string, mixed>, 1: bool} Tatami, als Leitung geöffnet
     */
    private function tatami(Request $request): array
    {
        $tisch = Session::tisch();
        $tatamiId = $tisch['tatami_id'] ?? (Session::istLeitung() ? (int) $request->zahl('tatami') : 0);
        $tatami = $this->db->eine(
            'SELECT ta.*, tu.name AS turnier, tu.datum, tu.leitung_pin_hash FROM tatami ta JOIN turnier tu ON tu.id = ta.turnier_id WHERE ta.id = ?',
            [$tatamiId],
        );
        // Leitung: nur Tatamis der eigenen Turniere
        if ($tatami === null || ($tisch === null && !Session::leitet((int) $tatami['turnier_id']))) {
            throw new NichtGefunden('Tatami nicht gefunden – als Leitung bitte über den Tatami-Plan öffnen.');
        }
        return [$tatami, $tisch === null];
    }
}
