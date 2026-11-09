<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung\Http;

use Wettkampf\Identitaet\AnmeldeSchutz;
use Wettkampf\Durchfuehrung\AusrufService;
use Wettkampf\Durchfuehrung\ErgebnisService;
use Wettkampf\Durchfuehrung\KlassenAnsicht;
use Wettkampf\Durchfuehrung\NachrichtService;
use Wettkampf\Durchfuehrung\PlanService;
use Wettkampf\Durchfuehrung\TatamiAnsicht;
use Wettkampf\Turnier\TurnierPhaseService;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Domain\Wertung\Wertung;
use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Controller;

/**
 * REST-API (JSON), Version 1, für die Tischbesatzung.
 *
 * Tischbesatzung (Tatami-Anmeldung) bzw. Leitung:
 *   GET  /api/v1/tisch                       Zustand der eigenen Tatami
 *   POST /api/v1/tisch/begegnung/{bid}/starten
 *   POST /api/v1/tisch/begegnung/{bid}       Ergebnis (Korrektur mit leitung_pin)
 *   POST /api/v1/tisch/begegnung/{bid}/zuruecksetzen  Ergebnis löschen, Kampf sofort neu austragen (leitung_pin)
 *   POST /api/v1/tisch/auftritt/{aid}        Punktwertung
 *   POST /api/v1/tisch/klasse/{kid}/abschliessen  Gruppe auf der Tatami abschließen (Tischbesatzung)
 *   GET  /api/v1/tisch/ausruf                Ausruf: laufende und kommende Kämpfe mit Ausruf-Stand
 *   POST /api/v1/tisch/ausruf/{typ}/{id}     {seite: aka|shiro|starter, status: da|fehlt|null}
 *   POST /api/v1/tisch/ausruf/{typ}/{id}/abschliessen | /oeffnen
 *
 * Datenschutz: Es werden nur Name und Verein ausgegeben, keine Geburtsdaten.
 */
final class TischApiController extends Controller
{
    public function tischZustand(Request $request): Response
    {
        [$turnierId, $tatamiId] = $this->tischKontext($request);
        $zustand = $this->mitAusrufUebernahme($tatamiId);
        $zustand['revision'] = (int) $this->db->wert('SELECT revision FROM turnier WHERE id = ?', [$turnierId]);
        $zustand['letzte'] = $this->letzteErgebnisse($tatamiId);
        $zustand['phase'] = TurnierPhase::von($this->db->eine('SELECT status FROM turnier WHERE id = ?', [$turnierId]) ?? []);
        $ansicht = new TatamiAnsicht($this->db);
        $zustand['abschluss'] = $ansicht->abschlussOffen($tatamiId);
        $zustand['letzter_abschluss'] = $ansicht->letzterAbschluss($tatamiId);
        $zustand['uebergang'] = $ansicht->uebergang($tatamiId, $zustand);
        // Ist im ganzen Turnier noch etwas offen? Sonst zeigt das Tablet „alles erledigt“ statt „nächste Gruppe kommt“.
        $zustand['turnier_rest'] = (int) $this->db->wert(
            "SELECT (SELECT COUNT(*) FROM phase p JOIN klasse k ON k.id = p.klasse_id WHERE k.turnier_id = ? AND p.status <> 'beendet')
                  + (SELECT COUNT(*) FROM klasse k WHERE k.turnier_id = ? AND k.status = 'offen' AND k.zusammengelegt_in IS NULL
                        AND (SELECT COUNT(*) FROM start s WHERE s.klasse_id = k.id AND s.status <> 'zurueckgezogen') >= k.min_starter)",
            [$turnierId, $turnierId],
        );
        return Response::json($zustand + $this->steuerung($turnierId, $tatamiId));
    }

    /** Tischbesatzung startet den Ausruf einer Gruppe ({"an": true}) bzw. nimmt ihn zurück. */
    public function ausrufFreigeben(Request $request, int $klasseid): Response
    {
        $turnierId = $this->gruppePruefen($request, $klasseid);
        (new AusrufService($this->db))->freigeben($turnierId, $klasseid, (bool) ($request->json()['an'] ?? true), $this->tischKontext($request)[1]);
        return Response::json(['ok' => true]);
    }

    /** Tischbesatzung schließt eine fertige Gruppe ab (danach wird die nächste geladen). */
    public function tischAbschliessen(Request $request, int $klasseid): Response
    {
        [$turnierId, $tatamiId] = $this->tischKontext($request);
        $tatami = $this->db->wert('SELECT tatami_id FROM phase WHERE klasse_id = ? ORDER BY id DESC LIMIT 1', [$klasseid]);
        if ((int) $tatami !== $tatamiId) {
            throw new FachFehler('Diese Gruppe gehört nicht zu dieser Tatami.');
        }
        (new TurnierPhaseService($this->db))->ergebnisseErlaubt($turnierId);
        (new ErgebnisService($this->db))->tischAbschliessen($turnierId, $klasseid);
        return Response::json(['ok' => true]);
    }

    /** Günstige Abfrage für das Polling: nur die Revision. */
    public function tischRevision(Request $request): Response
    {
        [$turnierId] = $this->tischKontext($request);
        return Response::json(['revision' => (int) $this->db->wert('SELECT revision FROM turnier WHERE id = ?', [$turnierId])]);
    }

    /** Einzelne Begegnung/Auftritt inkl. bisheriger Wertung (Korrektur). */
    public function tischEintrag(Request $request, string $typ, int $eintragid): Response
    {
        [, $tatamiId] = $this->tischKontext($request);
        $typ = $typ === 'auftritt' ? 'auftritt' : 'begegnung';
        $this->tatamiPruefen($typ, $eintragid, $tatamiId);
        $eintrag = (new TatamiAnsicht($this->db))->eintrag($typ, $eintragid) ?? throw new NichtGefunden('Nicht gefunden.');
        return Response::json($eintrag);
    }

    public function tischStarten(Request $request, int $begegnungid): Response
    {
        [$turnierId, $tatamiId] = $this->tischKontext($request);
        $this->tatamiPruefen('begegnung', $begegnungid, $tatamiId);
        (new TurnierPhaseService($this->db))->ergebnisseErlaubt($turnierId);
        (new ErgebnisService($this->db))->begegnungStarten($turnierId, $begegnungid);
        return Response::json(['ok' => true]);
    }

    /** Laufenden Kampf unterbrechen ({"an": true}) bzw. fortsetzen ({"an": false}). */
    public function tischUnterbrechen(Request $request, int $begegnungid): Response
    {
        [$turnierId, $tatamiId] = $this->tischKontext($request);
        $this->tatamiPruefen('begegnung', $begegnungid, $tatamiId);
        (new TurnierPhaseService($this->db))->ergebnisseErlaubt($turnierId);
        (new ErgebnisService($this->db))->begegnungUnterbrechen($turnierId, $begegnungid, (bool) ($request->json()['an'] ?? true));
        return Response::json(['ok' => true]);
    }

    public function tischBegegnung(Request $request, int $begegnungid): Response
    {
        [$turnierId, $tatamiId] = $this->tischKontext($request);
        $this->tatamiPruefen('begegnung', $begegnungid, $tatamiId);
        (new TurnierPhaseService($this->db))->ergebnisseErlaubt($turnierId);
        $eingabe = $request->json();
        (new ErgebnisService($this->db))->begegnungEintragen($turnierId, $begegnungid, $eingabe, $this->darfKorrigieren($turnierId, $eingabe, $request));
        return Response::json(['ok' => true]);
    }

    /**
     * Setzt ein fälschlich gesendetes Ergebnis zurück – nur mit Korrekturrecht
     * (Leitungs-PIN) – und startet den Kampf sofort wieder auf dieser Tatami;
     * ein dort laufender Kampf wird wieder „bereit“.
     */
    public function tischZuruecksetzen(Request $request, int $begegnungid): Response
    {
        [$turnierId, $tatamiId] = $this->tischKontext($request);
        $this->tatamiPruefen('begegnung', $begegnungid, $tatamiId);
        (new TurnierPhaseService($this->db))->ergebnisseErlaubt($turnierId);
        if (!$this->darfKorrigieren($turnierId, $request->json(), $request)) {
            throw new FachFehler('Zurücksetzen nur mit Leitungs-PIN.');
        }
        $ergebnis = new ErgebnisService($this->db);
        $ergebnis->begegnungZuruecksetzen($turnierId, $begegnungid);
        $ergebnis->begegnungStarten($turnierId, $begegnungid);
        return Response::json(['ok' => true]);
    }

    // --- Ausruf (Ansicht „Ausruf“ der Tischbesatzung) ------------------------------

    public function ausrufZustand(Request $request): Response
    {
        [$turnierId, $tatamiId] = $this->tischKontext($request);
        $zustand = $this->mitAusrufUebernahme($tatamiId);
        $turnier = $this->db->eine('SELECT status, revision FROM turnier WHERE id = ?', [$turnierId]) ?? [];
        $zustand['revision'] = (int) ($turnier['revision'] ?? 0);
        $zustand['phase'] = TurnierPhase::von($turnier);
        $zustand['aka_seite'] = AusrufService::akaSeite($this->db->eine('SELECT einstellungen FROM turnier WHERE id = ?', [$turnierId]) ?? []);
        // Gruppen, die noch nicht aufgerufen werden können (Starter noch auf einer anderen Tatami im Wettkampf)
        $ausruf = new AusrufService($this->db);
        $zustand['konflikte'] = [];
        foreach (array_unique(array_column(array_filter($zustand['naechste'], static fn($e) => !$e['gruppe_aufgerufen']), 'klasse_id')) as $klasseId) {
            if (($k = $ausruf->konflikte($turnierId, (int) $klasseId)) !== []) {
                $zustand['konflikte'][(string) $klasseId] = $k;
            }
        }
        $ansicht = new TatamiAnsicht($this->db);
        $zustand['abschluss'] = $ansicht->abschlussOffen($tatamiId);
        $zustand['letzter_abschluss'] = $ansicht->letzterAbschluss($tatamiId);
        return Response::json($zustand + $this->steuerung($turnierId, $tatamiId));
    }

    public function ausrufMarkieren(Request $request, string $typ, int $eintragid): Response
    {
        $turnierId = $this->ausrufPruefen($request, $typ, $eintragid);
        $eingabe = $request->json();
        $status = (string) ($eingabe['status'] ?? '');
        (new AusrufService($this->db))->markieren($turnierId, $typ, $eintragid, (string) ($eingabe['seite'] ?? ''), $status === '' ? null : $status);
        return Response::json(['ok' => true]);
    }

    public function ausrufAbschliessen(Request $request, string $typ, int $eintragid): Response
    {
        (new AusrufService($this->db))->abschliessen($this->ausrufPruefen($request, $typ, $eintragid), $typ, $eintragid);
        return Response::json(['ok' => true]);
    }

    public function ausrufOeffnen(Request $request, string $typ, int $eintragid): Response
    {
        (new AusrufService($this->db))->oeffnen($this->ausrufPruefen($request, $typ, $eintragid), $typ, $eintragid);
        return Response::json(['ok' => true]);
    }

    /** Gruppen-Ausruf starten/beenden ({an: true|false}). */
    public function ausrufGruppe(Request $request, int $klasseid): Response
    {
        $turnierId = $this->gruppePruefen($request, $klasseid);
        (new AusrufService($this->db))->gruppeAufrufen($turnierId, $klasseid, !empty($request->json()['an']), $this->tischKontext($request)[1]);
        return Response::json(['ok' => true]);
    }

    /** Starter der Gruppe abhaken ({status: da|fehlt|null}) – gilt für alle seine offenen Kämpfe der Gruppe. */
    public function ausrufStarter(Request $request, int $klasseid, int $startid): Response
    {
        $turnierId = $this->gruppePruefen($request, $klasseid);
        $status = (string) ($request->json()['status'] ?? '');
        (new AusrufService($this->db))->starterMarkieren($turnierId, $klasseid, $startid, $status === '' ? null : $status);
        return Response::json(['ok' => true]);
    }

    /** Ausruf der ganzen Gruppe abschließen. */
    public function ausrufGruppeAbschliessen(Request $request, int $klasseid): Response
    {
        $turnierId = $this->gruppePruefen($request, $klasseid);
        $anzahl = (new AusrufService($this->db))->gruppeAbschliessen($turnierId, $klasseid, $this->tischKontext($request)[1]);
        return Response::json(['ok' => true, 'anzahl' => $anzahl]);
    }

    public function ausrufGruppeOeffnen(Request $request, int $klasseid): Response
    {
        $turnierId = $this->gruppePruefen($request, $klasseid);
        (new AusrufService($this->db))->gruppeOeffnen($turnierId, $klasseid, $this->tischKontext($request)[1]);
        return Response::json(['ok' => true]);
    }

    /** Gruppe liegt auf der eigenen Tatami, Turnier läuft. @return int Turnier-ID */
    private function gruppePruefen(Request $request, int $klasseId): int
    {
        [$turnierId, $tatamiId] = $this->tischKontext($request);
        if ($this->db->wert('SELECT id FROM phase WHERE klasse_id = ? AND tatami_id = ? LIMIT 1', [$klasseId, $tatamiId]) === null) {
            throw new FachFehler('Diese Gruppe ist nicht auf dieser Tatami.');
        }
        (new TurnierPhaseService($this->db))->ergebnisseErlaubt($turnierId);
        return $turnierId;
    }

    /** Eintrag gehört zur eigenen Tatami, Turnier läuft. @return int Turnier-ID */
    private function ausrufPruefen(Request $request, string $typ, int $id): int
    {
        [$turnierId, $tatamiId] = $this->tischKontext($request);
        $this->tatamiPruefen($typ === 'auftritt' ? 'auftritt' : 'begegnung', $id, $tatamiId);
        (new TurnierPhaseService($this->db))->ergebnisseErlaubt($turnierId);
        return $turnierId;
    }

    public function tischAuftritt(Request $request, int $auftrittid): Response
    {
        [$turnierId, $tatamiId] = $this->tischKontext($request);
        $this->tatamiPruefen('auftritt', $auftrittid, $tatamiId);
        (new TurnierPhaseService($this->db))->ergebnisseErlaubt($turnierId);
        $eingabe = $request->json();
        (new ErgebnisService($this->db))->auftrittEintragen($turnierId, $auftrittid, $eingabe, $this->darfKorrigieren($turnierId, $eingabe, $request));
        return Response::json(['ok' => true]);
    }

    /**
     * Zustand einer Tatami; wer in einer Klasse schon ausgerufen wurde, muss dort nicht erneut
     * ausgerufen werden (siehe AusrufService::uebernehmen) – auch für bereits wartende Kämpfe.
     *
     * @return array<string, mixed>
     */
    private function mitAusrufUebernahme(int $tatamiId): array
    {
        $ansicht = new TatamiAnsicht($this->db);
        $zustand = $ansicht->fuerTatami($tatamiId, 30);
        $offen = array_unique(array_column(array_filter(
            array_merge($zustand['laufend'] ? [$zustand['laufend']] : [], $zustand['naechste']),
            static fn($e) => !($e['aufruf']['fertig'] ?? false),
        ), 'klasse_id'));
        $ausruf = new AusrufService($this->db);
        $geaendert = 0;
        foreach ($offen as $klasseId) {
            $geaendert += $ausruf->uebernehmen((int) $klasseId);
        }
        return $geaendert > 0 ? $ansicht->fuerTatami($tatamiId, 30) : $zustand;
    }

    /**
     * Sperre der Tatami, Verteilungsart und unbestätigte Nachrichten der Leitung (Tablet und Ausruf).
     *
     * @return array<string, mixed>
     */
    private function steuerung(int $turnierId, int $tatamiId): array
    {
        $t = $this->db->eine('SELECT gesperrt_am, sperr_grund FROM tatami WHERE id = ?', [$tatamiId]) ?? [];
        return [
            'programm' => programm_version(),
            'kampfuhr' => AusrufService::kampfuhr($this->db->eine('SELECT einstellungen FROM turnier WHERE id = ?', [$turnierId]) ?? []),
            'gesperrt' => !empty($t['gesperrt_am']),
            'sperr_grund' => $t['sperr_grund'] ?? null,
            'verteilung' => (new PlanService($this->db))->verteilung($turnierId)['modus'],
            'nachrichten' => (new NachrichtService($this->db))->offene($tatamiId),
        ];
    }

    /** Nachricht der Leitung am Tisch bestätigen (gilt für Tablet und Ausruf dieser Tatami). */
    public function nachrichtBestaetigen(Request $request, int $nachrichtid): Response
    {
        [$turnierId, $tatamiId] = $this->tischKontext($request);
        if (Session::tisch() === null) {
            throw new FachFehler('Nachrichten bestätigt die Tischbesatzung am Tablet.');
        }
        (new NachrichtService($this->db))->bestaetigen($turnierId, $tatamiId, $nachrichtid, Session::akteur());
        return Response::json(['ok' => true]);
    }

    private function tischKontext(Request $request): array
    {
        $tisch = Session::tisch();
        if ($tisch !== null) {
            return [$tisch['turnier_id'], $tisch['tatami_id']];
        }
        // Leitung kann jede Tatami ihrer Turniere bedienen (?tatami=ID)
        $tatami = $this->db->eine('SELECT * FROM tatami WHERE id = ?', [(int) $request->get('tatami')]);
        if ($tatami === null || !Session::leitet((int) $tatami['turnier_id'])) {
            throw new NichtGefunden('Tatami nicht gefunden.');
        }
        return [(int) $tatami['turnier_id'], (int) $tatami['id']];
    }

    private function tatamiPruefen(string $art, int $id, int $tatamiId): void
    {
        $tabelle = $art === 'auftritt' ? 'auftritt' : 'begegnung';
        $ort = $this->db->eine(
            "SELECT p.tatami_id, k.turnier_id FROM $tabelle x JOIN phase p ON p.id = x.phase_id JOIN klasse k ON k.id = p.klasse_id WHERE x.id = ?",
            [$id],
        );
        // Tischbesatzung nur eigene Tatami; die Leitung (ohne Tablet-Anmeldung) jede Tatami ihrer Turniere
        $leitungDarf = $ort !== null && Session::tisch() === null && Session::leitet((int) $ort['turnier_id']);
        if ((int) ($ort['tatami_id'] ?? 0) !== $tatamiId && !$leitungDarf) {
            throw new FachFehler('Diese Begegnung gehört nicht zu dieser Tatami.');
        }
    }

    /** @param array<string, mixed> $eingabe */
    /**
     * Korrekturrecht in der Tisch-Ansicht. Ist eine Leitungs-PIN vergeben,
     * ist sie immer nötig – auch wenn die Ansicht von der Leitung geöffnet
     * wurde (ein Tablet bleibt am Tisch liegen). Ohne Leitungs-PIN darf nur
     * die angemeldete Leitung korrigieren, nicht die Tischbesatzung.
     *
     * @param array<string, mixed> $eingabe
     */
    private function darfKorrigieren(int $turnierId, array $eingabe, Request $request): bool
    {
        $hash = $this->db->wert('SELECT leitung_pin_hash FROM turnier WHERE id = ?', [$turnierId]);
        if (!$hash) {
            return Session::tisch() === null && Session::leitet($turnierId);
        }
        $pin = (string) ($eingabe['leitung_pin'] ?? '');
        if ($pin === '') {
            return false;
        }
        $schutz = new AnmeldeSchutz($this->db);
        $ziel = 'leitung-pin:' . $turnierId;
        $ip = $this->app->clientIp($request);
        $schutz->pruefen($ziel, $ip);
        if (!password_verify($pin, (string) $hash)) {
            $schutz->fehlschlag($ziel, $ip, $turnierId);
            throw new FachFehler('Leitungs-PIN ist falsch.');
        }
        $schutz->erfolg($ziel, $ip);
        return true;
    }

    /** @return list<array<string, mixed>> */
    private function letzteErgebnisse(int $tatamiId): array
    {
        $ansicht = new KlassenAnsicht($this->db);
        $liste = [];
        foreach ($this->db->alle(
            "SELECT b.*, k.code, p.klasse_id FROM begegnung b JOIN phase p ON p.id = b.phase_id JOIN klasse k ON k.id = p.klasse_id
              WHERE p.tatami_id = ? AND b.status = 'beendet' ORDER BY b.beendet_am DESC, b.id DESC LIMIT 5",
            [$tatamiId],
        ) as $b) {
            $nr = KlassenAnsicht::kampfNummern($this->db, (int) $b['klasse_id'])[(int) $b['id']] ?? null;
            $liste[] = [
                'typ' => 'begegnung', 'id' => (int) $b['id'], 'nr' => $nr, 'klasse' => $b['code'], 'version' => (int) $b['version'],
                'aka' => $ansicht->person((int) $b['aka_start_id']), 'shiro' => $ansicht->person((int) $b['shiro_start_id']),
                'sieger' => (int) $b['sieger_start_id'] === (int) $b['aka_start_id'] ? 'aka' : 'shiro',
                'entscheidung' => $b['entscheidung'], 'entscheidung_text' => Wertung::entscheidungText($b['entscheidung']),
                'beendet_am' => $b['beendet_am'],
            ];
        }
        // Kata-Vorträge mit Punkten: ebenfalls am Tablet korrigierbar
        foreach ($this->db->alle(
            "SELECT a.*, k.code FROM auftritt a JOIN phase p ON p.id = a.phase_id JOIN klasse k ON k.id = p.klasse_id
              WHERE p.tatami_id = ? AND a.status = 'beendet' ORDER BY a.beendet_am DESC, a.id DESC LIMIT 5",
            [$tatamiId],
        ) as $a) {
            $kiken = !empty((json_decode((string) $a['wertung'], true) ?: [])['kiken']);
            $liste[] = [
                'typ' => 'auftritt', 'id' => (int) $a['id'], 'nr' => (int) $a['reihenfolge'], 'klasse' => $a['code'], 'version' => (int) $a['version'],
                'starter' => $ansicht->person((int) $a['start_id']),
                'ergebnis' => $kiken ? 'Kiken' : number_format((float) $a['gesamt'], 1, ',', ''),
                'beendet_am' => $a['beendet_am'],
            ];
        }
        usort($liste, static fn(array $x, array $y) => [(string) $y['beendet_am'], $y['id']] <=> [(string) $x['beendet_am'], $x['id']]);
        return array_slice($liste, 0, 5);
    }
}
