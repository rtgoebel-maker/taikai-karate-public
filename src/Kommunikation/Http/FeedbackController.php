<?php

declare(strict_types=1);

namespace Wettkampf\Kommunikation\Http;

use Wettkampf\Kommunikation\FeedbackMail;
use Wettkampf\Kern\Mail\Postausgang;
use Wettkampf\Kommunikation\MailEinstellungen;
use Wettkampf\Betrieb\DemoTurnier;
use Wettkampf\Kommunikation\FeedbackService;
use Wettkampf\Turnier\SystemEinstellungen;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Drossel;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Controller;

/**
 * Systemeinstellungen (für alle Turniere: Farbschema, Logos der Statusseite, Feedback) und Feedback: Einreichen per Knopf
 * (Leitung und Tablets), Liste mit Bearbeitung und Export für die Leitung.
 */
final class FeedbackController extends Controller
{
    /** Höchstens so viele Feedback-Meldungen je Gerät bzw. Konto und Stunde. */
    private const MAX_JE_STUNDE = 20;

    public function system(Request $request): Response
    {
        $system = new SystemEinstellungen($this->db);
        $wirksam = $this->app->mailKonfiguration();
        return $this->seite('system/index', [
            'feedbackAktiv' => $system->feedbackAktiv(),
            'feedbackAdresse' => FeedbackMail::adresse($this->app->config),
            'farben' => $system->farben(),
            'statusLogos' => $system->statusLogos(),
            'statusBereiche' => $system->statusBereiche(),
            'demoModus' => $system->demoModus(),
            'kontoMonate' => $system->kontoMonate(),
            'dojoMehrereTurniere' => $system->dojoZugangMehrereTurniere(),
            'zweiterFaktorPflicht' => $system->zweiterFaktorPflicht(),
            'zweiterFaktorAktiv' => $this->app->zweiterFaktorAktiv(),
            'mail' => (new MailEinstellungen($this->db, $this->app->tresor()))->anzeige(),
            'mailFestgelegt' => $this->app->mailFestgelegt(),
            'adresseFestgelegt' => $this->app->adresseFestgelegt(),
            'mailWirksam' => [
                'oeffentliche_url' => $wirksam['oeffentliche_url'] ?? null,
                'transport' => $wirksam['mail']['transport'] ?? null,
                'host' => $wirksam['mail']['host'] ?? '',
                'aktiv' => $this->app->postausgang() !== null,
            ],
            'warteschlange' => $this->db->eine(
                'SELECT SUM(CASE WHEN gesendet_am IS NULL AND versuche < ? THEN 1 ELSE 0 END) AS offen,
                        SUM(CASE WHEN gesendet_am IS NULL AND versuche >= ? THEN 1 ELSE 0 END) AS gescheitert,
                        SUM(CASE WHEN gesendet_am IS NOT NULL THEN 1 ELSE 0 END) AS gesendet,
                        MAX(CASE WHEN gesendet_am IS NULL THEN fehler END) AS fehler
                   FROM mail_ausgang',
                [Postausgang::MAX_VERSUCHE, Postausgang::MAX_VERSUCHE],
            ) ?? [],
            // letzte Mails (ohne Inhalt – der enthält Bearbeitungs- und Bestätigungslinks)
            'mailListe' => $this->db->alle('SELECT erstellt_am, empfaenger, betreff, anlass, gesendet_am, versuche, fehler FROM mail_ausgang ORDER BY id DESC LIMIT 15'),
            'turniereMitLogos' => $this->db->alle(
                'SELECT t.id, t.name, t.datum FROM turnier t WHERE EXISTS (SELECT 1 FROM turnier_logo l WHERE l.turnier_id = t.id) ORDER BY t.datum DESC, t.id DESC',
            ),
        ]);
    }

    /** Demo-Turnier anlegen (nur im Demo-Modus): ausgelost und verteilt, noch nicht gestartet. */
    public function demoTurnier(Request $request): Response
    {
        if (!(new SystemEinstellungen($this->db))->demoModus()) {
            throw new FachFehler('Der Demo-Modus ist ausgeschaltet.');
        }
        $logosVon = (int) $request->zahl('logos_von');
        $demo = (new DemoTurnier($this->db))->anlegen(
            $request->text('datum'),
            $request->text('name'),
            $request->post('teams') === '1',
            $logosVon > 0 ? $logosVon : null,
            $request->text('phase', 'vorbereitung'),
        );
        $this->erfolg("Demo-Turnier „{$demo['name']}“ angelegt: {$demo['teilnehmer']} Teilnehmer"
            . ($demo['teams'] ? ", {$demo['teams']} Teams" : '') . ", {$demo['ausgelost']} Klassen ausgelost"
            . ($demo['ergebnisse'] ? ", {$demo['ergebnisse']} Ergebnisse eingetragen" : ' – noch nicht gestartet') . '. PINs: ' . DemoTurnier::PINS . '.');
        return $this->weiter("turnier/{$demo['turnier']}");
    }

    /** Demo-Modus ein-/ausschalten (eigener Abschnitt „Demo-Turniere“ auf der Systemseite). */
    public function demoModus(Request $request): Response
    {
        (new SystemEinstellungen($this->db))->setzen(SystemEinstellungen::DEMO, $request->post('demo_modus') ? '1' : '0');
        $this->erfolg($request->post('demo_modus') ? 'Demo-Modus eingeschaltet – unten lassen sich jetzt Demo-Turniere anlegen.' : 'Demo-Modus ausgeschaltet.');
        return $this->weiter('system');
    }

    public function systemSpeichern(Request $request): Response
    {
        $system = new SystemEinstellungen($this->db);
        $system->farbenSetzen($request->text('farbschema', 'marine'), $request->text('farbe_eigene', '#1d2b45'));
        $feedbackGewuenscht = $request->post('feedback_aktiv') !== null;
        $system->setzen(SystemEinstellungen::FEEDBACK, $feedbackGewuenscht && $this->app->postausgang() !== null ? '1' : '0');
        $pflicht = $request->post('zweiter_faktor_pflicht') !== null;
        if ($pflicht !== $system->zweiterFaktorPflicht()) {
            $system->setzen(SystemEinstellungen::ZWEITER_FAKTOR_PFLICHT, $pflicht ? '1' : '0');
            (new Protokoll($this->db))->eintragen(null, 'system', null, 'zweiter_faktor_pflicht', ['pflicht' => $pflicht]);
        }
        $system->setzen(SystemEinstellungen::DOJO_MEHRERE_TURNIERE, $request->post('dojo_mehrere_turniere') !== null ? '1' : '0');
        $system->setzen(SystemEinstellungen::KONTO_MONATE, (string) max(1, min(24, (int) $request->post('konto_monate', SystemEinstellungen::KONTO_MONATE_STANDARD))));
        foreach (SystemEinstellungen::STATUS_LOGOS as $art => $schluessel) {
            $system->setzen($schluessel, in_array($art, (array) $request->post('status_logos', []), true) ? '1' : '0');
        }
        foreach (SystemEinstellungen::STATUS_BEREICHE as $bereich => $schluessel) {
            $system->setzen($schluessel, in_array($bereich, (array) $request->post('status_bereiche', []), true) ? '1' : '0');
        }
        $this->erfolg('Systemeinstellungen gespeichert.' . ($feedbackGewuenscht && $this->app->postausgang() === null
            ? ' Der Feedback-Knopf bleibt aus, bis der E-Mail-Versand eingerichtet ist – die Meldungen gehen per Mail an das Taikai-Team.' : ''));
        return $this->weiter('system');
    }

    public function index(Request $request): Response
    {
        $status = $request->text('status') ?: null;
        $art = $request->text('art') ?: null;
        return $this->seite('feedback/index', [
            'eintraege' => (new FeedbackService($this->db))->liste($status, $art),
            'status' => $status,
            'art' => $art,
            'aktiv' => $this->app->feedbackAktiv(),
            'anzahlNeu' => (new FeedbackService($this->db))->anzahlNeu(),
        ]);
    }

    /** Einreichen über den Feedback-Knopf (JSON). */
    public function einreichen(Request $request): Response
    {
        if (!$this->app->feedbackAktiv()) {
            throw new FachFehler('Die Feedback-Funktion ist ausgeschaltet bzw. der E-Mail-Versand ist nicht eingerichtet.');
        }
        $daten = $request->json();
        $tisch = Session::tisch();
        $turnierId = $tisch['turnier_id'] ?? null;
        $von = Session::akteur();
        if ($tisch !== null) {
            $von = 'Tisch ' . $tisch['name'];
        } elseif (preg_match('#/turnier/(\d+)|[?&]turnier=(\d+)#', (string) ($daten['seite'] ?? ''), $t)) {
            $turnierId = (int) ($t[1] ?: $t[2]);
        }
        if ($turnierId !== null && $this->db->wert('SELECT id FROM turnier WHERE id = ?', [$turnierId]) === null) {
            $turnierId = null;
        }
        // je Gerät bzw. Konto (in der Halle teilen sich die Tablets oft eine IP): höchstens 20 pro Stunde (OWASP A06)
        $absender = 'feedback:' . ($tisch !== null ? 'tatami:' . $tisch['tatami_id'] : 'konto:' . (Session::benutzer()['id'] ?? 0));
        $drossel = new Drossel($this->db);
        $drossel->pruefen($absender, self::MAX_JE_STUNDE, 3600);
        $id = (new FeedbackService($this->db))->einreichen($daten, $von, $turnierId);
        $drossel->zaehlen($absender, 3600);
        // an das Taikai-Team (Rückfragen an das Konto des Absenders, falls vorhanden)
        $post = $this->app->postausgang();
        if ($post !== null) {
            $email = $tisch === null ? $this->db->wert('SELECT email FROM benutzer WHERE id = ?', [(int) (Session::benutzer()['id'] ?? 0)]) : null;
            (new FeedbackMail($this->db, $post, FeedbackMail::adresse($this->app->config)))
                ->senden($id, is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null);
        }
        return Response::json(['ok' => true, 'id' => $id]);
    }

    /** Status/Notiz ändern (JSON, automatisch gespeichert). */
    public function bearbeiten(Request $request, int $id): Response
    {
        $daten = $request->json();
        (new FeedbackService($this->db))->bearbeiten(
            $id,
            isset($daten['status']) ? (string) $daten['status'] : null,
            isset($daten['notiz']) ? (string) $daten['notiz'] : null,
        );
        return Response::json(['ok' => true]);
    }

    public function loeschen(Request $request, int $id): Response
    {
        (new FeedbackService($this->db))->loeschen($id);
        $this->erfolg('Feedback gelöscht.');
        return $this->weiter('feedback', array_filter(['status' => $request->text('status'), 'art' => $request->text('art')]));
    }

    public function screenshot(Request $request, int $id): Response
    {
        $bild = (new FeedbackService($this->db))->screenshot($id) ?? throw new NichtGefunden('Kein Screenshot vorhanden.');
        return new Response($bild['inhalt'], 200, ['Content-Type' => $bild['mime'], 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function export(Request $request): Response
    {
        $service = new FeedbackService($this->db);
        $name = 'feedback-' . date('Y-m-d-His');
        if ($request->get('format') === 'zip') {
            return Response::download($service->exportZip(), "$name.zip", 'application/zip');
        }
        $json = json_encode(
            ['exportiert_am' => date('Y-m-d H:i:s'), 'feedback' => $service->exportDaten()],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );
        return Response::download($json, "$name.json", 'application/json');
    }
}
