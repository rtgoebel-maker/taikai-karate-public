<?php

declare(strict_types=1);

namespace Wettkampf\Meldung\Http;

use Wettkampf\Kern\Girocode;
use Wettkampf\Kern\QrCode;
use Wettkampf\Identitaet\DojoKonten;
use Wettkampf\Identitaet\Konten;
use Wettkampf\Meldung\AnmeldungMails;
use Wettkampf\Meldung\AnmeldungService;
use Wettkampf\Meldung\DojoZugangBindung;
use Wettkampf\Turnier\SystemEinstellungen;
use Wettkampf\Meldung\CoachService;
use Wettkampf\Meldung\RechnungService;
use Wettkampf\Meldung\AnmeldungUngueltig;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Drossel;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Session;

/**
 * Online-Anmeldung der Dojos (öffentlich, mit Bearbeitungscode) und deren
 * Verwaltung durch die Wettkampfleitung (Einstellungen, Freigabe, Ablehnung). Ein angemeldetes
 * Coach-Konto meldet fest für sein Dojo; die Meldung erscheint dann unter „Meine Meldungen“.
 */
final class AnmeldungController extends Controller
{
    /** Höchstens so viele gespeicherte Meldungen bzw. Änderungen je Absender und Stunde (OWASP A06). */
    private const MAX_JE_STUNDE = 10;

    // --- öffentlich ---------------------------------------------------------------

    /** Anmeldeformular über den Anmeldelink mit Zufallsschlüssel. */
    public function melden(Request $request, string $schluessel): Response
    {
        return $this->formularSeite($this->turnier($this->perSchluessel($schluessel)), null, null);
    }

    /**
     * Alte Adresse /anmeldung/{nr} (fortlaufende Nummer, erratbar): nur die Turnierleitung wird zum Anmeldelink
     * weitergeleitet, für alle anderen gibt es das Formular hier nicht (mehr).
     */
    public function formular(Request $request, int $id): Response
    {
        if (Session::leitet($id) && $this->db->wert('SELECT 1 FROM turnier WHERE id = ?', [$id]) !== null) {
            return $this->weiter('melden/' . $this->service()->anmeldeSchluessel($id));
        }
        throw new NichtGefunden('Diese Anmeldeadresse gibt es nicht (mehr). Bitte den Anmeldelink des Ausrichters verwenden.');
    }

    public function absenden(Request $request, string $schluessel): Response
    {
        $id = $this->perSchluessel($schluessel);
        $turnier = $this->turnier($id);
        $kontoAnlegen = $this->kontoMoeglich($turnier, null) !== null && $request->post('konto_anlegen') !== null;
        try {
            $this->einwilligungPruefen($request);
            $this->botPruefen($request);
            $this->bindungPruefen($id);
            $this->drossel()->pruefen($this->absender($request), self::MAX_JE_STUNDE, 3600);
            if ($kontoAnlegen) {
                $this->kontoVorpruefen($request);
            }
            $code = $this->service()->einreichen($id, $request->post, null, Session::coachVerein());
            $this->drossel()->zaehlen($this->absender($request), 3600);
        } catch (AnmeldungUngueltig $f) {
            return $this->formularSeite($turnier, null, $request->post, $f->fehler);
        }
        $anmeldung = $this->perCode($id, $code);
        $hinweis = $this->konto($turnier, $anmeldung, $kontoAnlegen, $request);
        $this->mails()?->eingegangen($turnier, $anmeldung);
        $this->erfolg('Vielen Dank – die Anmeldung ist eingegangen. Wie es weitergeht, zeigt der Bearbeitungsstand unten; die Zahlungsdaten stehen bei „Zahlungsaufforderung“.'
            . ($this->mails() !== null ? ' Eine Bestätigung mit dem Bearbeitungslink ist per E-Mail unterwegs.' : ' Bitte diese Übersicht speichern und den Bearbeitungslink aufbewahren – darüber ist der Stand jederzeit abrufbar.')
            . $hinweis);
        return $this->weiter("anmeldung/$id/$code/uebersicht");
    }

    public function bearbeiten(Request $request, int $id, string $code): Response
    {
        $turnier = $this->turnier($id);
        return $this->formularSeite($turnier, $this->perCode($id, $code), null);
    }

    public function aendern(Request $request, int $id, string $code): Response
    {
        $turnier = $this->turnier($id);
        $anmeldung = $this->perCode($id, $code);
        try {
            $this->einwilligungPruefen($request);
            $this->botPruefen($request);
            $this->drossel()->pruefen($this->absender($request), self::MAX_JE_STUNDE, 3600);
            $this->service()->einreichen($id, $request->post, $code);
            $this->drossel()->zaehlen($this->absender($request), 3600);
        } catch (AnmeldungUngueltig $f) {
            return $this->formularSeite($turnier, $anmeldung, $request->post, $f->fehler);
        }
        $neu = $this->perCode($id, $code);
        if ((int) $neu['aufforderung_version'] !== (int) $anmeldung['aufforderung_version']) {
            $this->mails()?->neueAufforderung($turnier, $neu);
        }
        $this->erfolg('Änderungen gespeichert – hier die aktuelle Übersicht. Hat sich das Startgeld geändert, gilt die neue Version der Zahlungsaufforderung.');
        return $this->weiter("anmeldung/$id/$code/uebersicht");
    }

    /** Druckbare Übersicht für das Dojo (per Bearbeitungscode). */
    public function uebersicht(Request $request, int $id, string $code): Response
    {
        $turnier = $this->turnier($id);
        $anmeldung = $this->perCode($id, $code);
        return $this->druckSeite($turnier, $anmeldung, url("anmeldung/$id/$code"), true);
    }

    /** Druckbare Übersicht für die Wettkampfleitung. */
    public function druck(Request $request, int $id, int $anmeldungid): Response
    {
        $turnier = $this->turnier($id);
        return $this->druckSeite($turnier, $this->service()->anmeldung($id, $anmeldungid), url("turnier/$id/anmeldung/$anmeldungid"));
    }

    /**
     * Rechnung zur Anmeldung für das Dojo (per Bearbeitungscode) – nur wenn das Turnier sie anbietet und die
     * Zahlung vollständig eingegangen ist; sonst genügt die Übersicht als Anmelde- und Zahlungsbestätigung.
     */
    public function rechnungDojo(Request $request, int $id, string $code): Response
    {
        $turnier = $this->turnier($id);
        $anmeldung = $this->perCode($id, $code);
        if (!$this->service()->einstellungen($turnier)['rechnung_fuer_dojos'] || !$this->service()->stand($anmeldung, $turnier)['bezahlt_voll']) {
            throw new NichtGefunden('Eine Rechnung gibt es für diese Anmeldung (noch) nicht – bei Bedarf bitte bei der Wettkampfleitung anfragen.');
        }
        return $this->rechnungSeite($turnier, $anmeldung, url("anmeldung/$id/$code/uebersicht"));
    }

    // --- Wettkampfleitung ----------------------------------------------------------

    /** Rechnung zur Anmeldung auf Wunsch (auch vor der Zahlung, dann mit Zahlungsziel). */
    public function rechnung(Request $request, int $id, int $anmeldungid): Response
    {
        $turnier = $this->turnier($id);
        return $this->rechnungSeite($turnier, $this->service()->anmeldung($id, $anmeldungid), url("turnier/$id/anmeldung/$anmeldungid"));
    }

    public function zahlung(Request $request, int $id, int $anmeldungid): Response
    {
        $this->turnier($id);
        $this->service()->zahlungErfassen($id, $anmeldungid, $request->text('betrag'), $request->text('datum'), $request->text('notiz'));
        $mails = $this->mails();
        $mails?->zahlungEingegangen($this->turnier($id), $this->service()->anmeldung($id, $anmeldungid));
        $this->erfolg('Zahlungseingang erfasst – das Dojo sieht ihn auf seiner Anmeldeübersicht' . ($mails !== null ? ' und erhält eine E-Mail.' : '.'));
        return $this->weiter("turnier/$id/anmeldung/$anmeldungid");
    }

    public function zahlungZuruecknehmen(Request $request, int $id, int $anmeldungid): Response
    {
        $this->turnier($id);
        $this->service()->zahlungZuruecknehmen($id, $anmeldungid);
        $this->erfolg('Zahlungseingang zurückgenommen.');
        return $this->weiter("turnier/$id/anmeldung/$anmeldungid");
    }

    public function index(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $anmeldungen = $this->db->alle('SELECT * FROM anmeldung WHERE turnier_id = ? ORDER BY eingereicht_am DESC', [$id]);
        $service = $this->service();
        foreach ($anmeldungen as &$a) {
            $daten = json_decode((string) $a['daten'], true);
            $a['teilnehmer'] = count($daten['teilnehmer']);
            $a['teams'] = count($daten['teams']);
            $a['eigener_verein'] = $service->eigenerVerein($a, Session::coachVerein());
            $a['stand'] = $service->stand($a, $turnier);
        }
        unset($a);
        return $this->seite('anmeldung/index', [
            'turnier' => $turnier,
            'anmeldungen' => $anmeldungen,
            'einstellungen' => $service->einstellungen($turnier),
            'geschlossen' => $service->geschlossenGrund($turnier),
            'antraegeOffen' => (new CoachService($this->db))->offeneAntraege($id),
            'anmeldelink' => url('melden/' . $service->anmeldeSchluessel($id)),
            'aktiv' => 'anmeldungen',
        ]);
    }

    /** Neuer Anmeldelink, der bisherige gilt nicht mehr. */
    public function linkErneuern(Request $request, int $id): Response
    {
        $this->turnier($id);
        $this->service()->schluesselErneuern($id);
        $this->erfolg('Neuer Anmeldelink erzeugt – der bisherige gilt nicht mehr. Bereits abgeschickte Anmeldungen und ihre Bearbeitungslinks bleiben gültig.');
        return $this->weiter("turnier/$id/anmeldungen");
    }

    public function einstellungen(Request $request, int $id): Response
    {
        $this->turnier($id);
        $this->service()->einstellungenSpeichern($id, $request->post);
        $this->erfolg('Einstellungen der Anmeldung gespeichert.');
        return $this->weiterNach($request, "turnier/$id/anmeldungen");
    }

    public function detail(Request $request, int $id, int $anmeldungid): Response
    {
        $turnier = $this->turnier($id);
        $anmeldung = $this->service()->anmeldung($id, $anmeldungid);
        return $this->seite('anmeldung/detail', $this->ansicht($turnier, $anmeldung) + [
            'aktiv' => 'anmeldungen',
            'zahlungVorFreigabe' => $this->service()->einstellungen($turnier)['zahlung_vor_freigabe'],
            'konten' => (new Konten($this->db))->zurAnmeldung((string) $anmeldung['uuid']),
            'eigenerVerein' => $this->service()->eigenerVerein($anmeldung, Session::coachVerein()),
        ]);
    }

    public function freigeben(Request $request, int $id, int $anmeldungid): Response
    {
        $turnier = $this->turnier($id);
        $hinweise = $this->service()->freigeben($id, $anmeldungid, $request->post('konto_zuordnen') !== null);
        $this->mails()?->freigegeben($turnier, $this->service()->anmeldung($id, $anmeldungid));
        $this->erfolg('Anmeldung freigegeben – Teilnehmer und Teams wurden übernommen und den Klassen zugeordnet.'
            . ($hinweise ? ' Hinweise: ' . implode(' · ', $hinweise) : ''));
        return $this->weiter("turnier/$id/anmeldung/$anmeldungid");
    }

    public function ablehnen(Request $request, int $id, int $anmeldungid): Response
    {
        $this->turnier($id);
        $this->service()->ablehnen($id, $anmeldungid, $request->text('grund'));
        $this->mails()?->abgelehnt($this->turnier($id), $this->service()->anmeldung($id, $anmeldungid));
        $this->erfolg('Anmeldung abgelehnt.');
        return $this->weiter("turnier/$id/anmeldung/$anmeldungid");
    }

    public function oeffnen(Request $request, int $id, int $anmeldungid): Response
    {
        $this->turnier($id);
        $this->service()->wiederOeffnen($id, $anmeldungid);
        $this->erfolg('Anmeldung wieder geöffnet – das Dojo kann sie ändern.');
        return $this->weiter("turnier/$id/anmeldung/$anmeldungid");
    }

    // --- Hilfen ------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $turnier
     * @param array<string, mixed> $anmeldung
     */
    private function druckSeite(array $turnier, array $anmeldung, string $zurueck, bool $fuerDojo = false): Response
    {
        $service = $this->service();
        $einstellungen = $service->einstellungen($turnier);
        $stand = $service->stand($anmeldung, $turnier);
        return $this->seite('anmeldung/druck', $this->ansicht($turnier, $anmeldung) + [
            'einstellungen' => $einstellungen,
            'rechnungLink' => $einstellungen['rechnung_fuer_dojos'] && $stand['bezahlt_voll'] && $fuerDojo
                ? url("anmeldung/{$turnier['id']}/{$anmeldung['code']}/rechnung") : null,
            'bearbeitbar' => $anmeldung['status'] === 'eingereicht' && $service->geschlossenGrund($turnier) === null,
            'zurueck' => $zurueck,
            'fuerDojo' => $fuerDojo,
            'rechnungLeitung' => $fuerDojo ? null : url("turnier/{$turnier['id']}/anmeldung/{$anmeldung['id']}/rechnung"),
        ]);
    }

    /**
     * @param array<string, mixed> $turnier
     * @param array<string, mixed> $anmeldung
     */
    private function rechnungSeite(array $turnier, array $anmeldung, string $zurueck): Response
    {
        $blatt = (new RechnungService($this->db))->anmeldungRechnung($turnier, $anmeldung);
        return Response::html($this->app->view->render('anmeldung/rechnung', ['blatt' => $blatt, 'zurueck' => $zurueck]));
    }

    /** Ein angemeldeter, an ein anderes Turnier gebundener Dojo-Zugang meldet hier nicht (DojoZugangBindung). */
    private function bindungPruefen(int $turnierId): void
    {
        $text = $this->bindungHinweis($turnierId);
        if ($text !== null) {
            throw new AnmeldungUngueltig([$text]);
        }
    }

    private function bindungHinweis(int $turnierId): ?string
    {
        $konto = Session::benutzer()['id'] ?? null;
        $bindung = new DojoZugangBindung($this->db);
        if ($konto === null || $bindung->erlaubt((int) $konto, $turnierId)) {
            return null;
        }
        return 'Sie sind mit einem Dojo-Zugang angemeldet, der nur für „' . $bindung->namen((int) $konto) . '“ gilt. '
            . 'Für dieses Turnier bitte abmelden und ohne Anmeldung melden (auf Wunsch mit neuem Zugang über eine andere E-Mail-Adresse).';
    }

    /** E-Mails zum Bearbeitungsstand; null ohne eingerichteten Versand. */
    private function mails(): ?AnmeldungMails
    {
        $post = $this->app->postausgang();
        return $post === null ? null : new AnmeldungMails($this->db, $post);
    }

    /** Bot-Schutz des öffentlichen Formulars; ein Verdacht kommt als Formularfehler (Eingaben bleiben erhalten). */
    private function botPruefen(Request $request): void
    {
        try {
            $this->app->botSchutz()->pruefen($request->post, 'anmeldung');
        } catch (FachFehler $f) {
            throw new AnmeldungUngueltig([$f->getMessage()]);
        }
    }

    /**
     * Kann zu dieser Anmeldung ein Dojo-Zugang angelegt werden? Nur neu, nicht angemeldet, mit E-Mail-Versand.
     *
     * @param array<string, mixed> $turnier
     * @param array<string, mixed>|null $anmeldung
     * @return string|null Ablaufdatum des Zugangs (Y-m-d) bzw. null
     */
    private function kontoMoeglich(array $turnier, ?array $anmeldung): ?string
    {
        if ($anmeldung !== null || Session::benutzer() !== null || $this->app->postausgang() === null) {
            return null;
        }
        return (new SystemEinstellungen($this->db))->kontoAblauf((string) $turnier['datum']);
    }

    /** Angaben zum Zugang vor dem Speichern prüfen – sonst wäre die Anmeldung gespeichert, der Zugang aber nicht. */
    private function kontoVorpruefen(Request $request): void
    {
        $fehler = [];
        if ($request->text('konto_vorname') === '' || $request->text('konto_nachname') === '') {
            $fehler[] = 'Zugang: bitte Vor- und Nachname angeben.';
        }
        $passwort = (string) $request->post('konto_passwort', '');
        try {
            Konten::passwortPruefen($passwort);
        } catch (FachFehler $f) {
            $fehler[] = 'Zugang: ' . $f->getMessage();
        }
        if ($passwort !== (string) $request->post('konto_passwort2', '')) {
            $fehler[] = 'Zugang: Die beiden Passwörter stimmen nicht überein.';
        }
        if ($fehler !== []) {
            throw new AnmeldungUngueltig($fehler);
        }
    }

    /**
     * Nach dem Absenden: angemeldetes Konto verknüpfen (und dessen Zugang verlängern) bzw. neuen Zugang anlegen.
     *
     * @param array<string, mixed> $turnier
     * @param array<string, mixed> $anmeldung
     * @return string Zusatz für die Erfolgsmeldung
     */
    private function konto(array $turnier, array $anmeldung, bool $anlegen, Request $request): string
    {
        $konten = new DojoKonten($this->db);
        $ablauf = (new SystemEinstellungen($this->db))->kontoAblauf((string) $turnier['datum']);
        $ich = Session::benutzer()['id'] ?? null;
        if ($ich !== null) {
            $konten->verknuepfen((int) $ich, (string) $anmeldung['uuid']);
            $konten->verlaengern((int) $ich, $ablauf);
            return '';
        }
        $post = $this->app->postausgang();
        if (!$anlegen || $post === null) {
            return '';
        }
        try {
            $konten->anlegenFuerAnmeldung($request->post, (string) $anmeldung['email'], (string) $anmeldung['uuid'], $ablauf, (string) $turnier['name'], $post);
        } catch (FachFehler $f) {
            return ' Der Zugang konnte nicht angelegt werden: ' . $f->getMessage();
        }
        return ' Für den Zugang kommt eine E-Mail mit einem Bestätigungslink – erst danach ist er nutzbar.';
    }

    private function service(): AnmeldungService
    {
        return new AnmeldungService($this->db);
    }

    private function drossel(): Drossel
    {
        return new Drossel($this->db);
    }

    /** Absender für die Drosselung: Coach-Konto bzw. IP-Adresse. */
    private function absender(Request $request): string
    {
        $konto = Session::benutzer()['id'] ?? null;
        return 'anmeldung:' . ($konto !== null ? 'konto:' . $konto : 'ip:' . $this->app->clientIp($request));
    }

    /** @return array<string, mixed> */
    private function perCode(int $id, string $code): array
    {
        return $this->service()->perCode($id, $code) ?? throw new NichtGefunden('Anmeldung nicht gefunden – bitte den Link prüfen.');
    }

    private function perSchluessel(string $schluessel): int
    {
        return $this->service()->turnierPerSchluessel($schluessel)
            ?? throw new NichtGefunden('Diesen Anmeldelink gibt es nicht (mehr). Bitte den aktuellen Link beim Ausrichter erfragen.');
    }

    private function einwilligungPruefen(Request $request): void
    {
        if ($request->text('webseite') !== '') {
            throw new FachFehler('Anmeldung konnte nicht gespeichert werden.'); // Spam-Falle
        }
        if ($request->post('einwilligung') === null) {
            throw new AnmeldungUngueltig(['Bitte der Verarbeitung der Daten für die Turnierorganisation zustimmen.']);
        }
    }

    /**
     * @param array<string, mixed> $turnier
     * @param array<string, mixed> $anmeldung
     * @return array<string, mixed>
     */
    private function ansicht(array $turnier, array $anmeldung): array
    {
        $daten = json_decode((string) $anmeldung['daten'], true);
        $service = $this->service();
        $rechnung = new RechnungService($this->db);
        $stand = $service->stand($anmeldung, $turnier);
        $zahlung = $rechnung->einstellungen($turnier) + ['nummer' => $rechnung->nummerAnmeldung($turnier, (int) $anmeldung['id'])];
        // Überweisungs-QR-Code (GiroCode) für Banking-Apps – nur mit Empfänger und gültiger IBAN
        $giro = $stand['offen'] > 0
            ? Girocode::nutzdaten($zahlung['absender'], $zahlung['bank'], $stand['offen'], $zahlung['nummer'] . ' ' . $anmeldung['dojo'])
            : null;
        return [
            'turnier' => $turnier,
            'anmeldung' => $anmeldung,
            'stand' => $stand,
            'zahlungsangaben' => $zahlung,
            'girocode' => $giro === null ? null : QrCode::svg($giro),
            'angaben' => $daten,
            'preis' => $service->preis($service->einstellungen($turnier), $daten),
        ];
    }

    /**
     * Formular (neu oder per Code) bzw. schreibgeschützte Übersicht, wenn die
     * Anmeldung nicht mehr geändert werden kann.
     *
     * @param array<string, mixed> $turnier
     * @param array<string, mixed>|null $anmeldung
     * @param array<string, mixed>|null $eingabe
     * @param list<string> $fehler
     */
    private function formularSeite(array $turnier, ?array $anmeldung, ?array $eingabe, array $fehler = []): Response
    {
        $service = $this->service();
        $geschlossen = $service->geschlossenGrund($turnier);
        $bearbeitbar = $geschlossen === null && ($anmeldung === null || $anmeldung['status'] === 'eingereicht');
        $werte = $eingabe ?? ($anmeldung !== null ? AnmeldungService::formularWerte($anmeldung) : $this->coachVorgaben());
        // Meldung eines Coach-Kontos: Dojo fest (Name aus den Stammdaten)
        $vereinId = $anmeldung !== null && $anmeldung['verein_id'] !== null ? (int) $anmeldung['verein_id'] : ($anmeldung === null ? Session::coachVerein() : null);
        $festesDojo = $vereinId === null ? null : $this->db->wert('SELECT name FROM verein WHERE id = ?', [$vereinId]);
        $einstellungen = $service->einstellungen($turnier);
        return $this->seite('anmeldung/formular', [
            'formularZiel' => $anmeldung !== null
                ? url("anmeldung/{$turnier['id']}/{$anmeldung['code']}")
                : url('melden/' . $service->anmeldeSchluessel((int) $turnier['id'])),
            'botschutz' => $bearbeitbar ? $this->app->botSchutz()->felder('anmeldung') : '',
            'bindungHinweis' => $anmeldung === null ? $this->bindungHinweis((int) $turnier['id']) : null,
            'kontoBis' => $bearbeitbar ? $this->kontoMoeglich($turnier, $anmeldung) : null,
            'kontoMonate' => (new SystemEinstellungen($this->db))->kontoMonate(),
            'angemeldetAls' => Session::benutzer() !== null ? (string) ($this->db->wert('SELECT email FROM benutzer WHERE id = ?', [(int) Session::benutzer()['id']]) ?? '') : null,
            'einstellungen' => $einstellungen,
            'preisFormular' => $service->preis($einstellungen, $service->normalisieren($werte)['daten']),
            'geschlossen' => $geschlossen,
            'bearbeitbar' => $bearbeitbar,
            'werte' => $werte,
            'fehler' => $fehler,
            'festesDojo' => is_string($festesDojo) ? $festesDojo : null,
        ] + ($anmeldung !== null ? $this->ansicht($turnier, $anmeldung) : ['turnier' => $turnier, 'anmeldung' => null]));
    }

    /**
     * Vorbelegung des Formulars für ein angemeldetes Coach-Konto (Ansprechperson, E-Mail, Telefon).
     *
     * @return array<string, mixed>
     */
    private function coachVorgaben(): array
    {
        $konto = Session::coachVerein() === null ? null
            : $this->db->eine('SELECT name, email, telefon FROM benutzer WHERE id = ?', [(int) (Session::benutzer()['id'] ?? 0)]);
        return $konto === null ? [] : ['ansprechpartner' => $konto['name'], 'email' => $konto['email'], 'telefon' => $konto['telefon'] ?? ''];
    }
}
