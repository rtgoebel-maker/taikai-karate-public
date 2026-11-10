<?php

declare(strict_types=1);

namespace Wettkampf\Turnier\Http;

use Wettkampf\Durchfuehrung\AusrufService;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Turnier\StammdatenService;
use Wettkampf\Turnier\TurnierPhaseService;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Controller;

final class TurnierController extends Controller
{
    public function neu(Request $request): Response
    {
        return $this->seite('turnier/formular', [
            'turnier' => null,
            'werte' => ['name' => '', 'datum' => date('Y-m-d'), 'ort' => '', 'halle' => '', 'anzahl_tatamis' => 2,
                'min_graduierung' => '', 'meldeschluss' => '', 'oeffentlich' => 1],
        ]);
    }

    public function anlegen(Request $request): Response
    {
        $id = (new StammdatenService($this->db))->turnierAnlegen($request->post);
        if ($request->post('assistent_starten') !== null) {
            $this->erfolg('Turnier angelegt. Der Assistent führt jetzt durch die wichtigsten Einstellungen – jeder Schritt lässt sich überspringen und später ändern.');
            return $this->weiter("turnier/$id/assistent/klassen");
        }
        $this->erfolg('Turnier angelegt. Als Nächstes: Klassen aus einer Vorlage laden und Teilnehmer erfassen.');
        return $this->weiter("turnier/$id");
    }

    public function uebersicht(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $zahlen = [
            'teilnehmer' => (int) $this->db->wert("SELECT COUNT(*) FROM teilnahme WHERE turnier_id = ? AND status NOT IN ('abgemeldet', 'nicht_erschienen')", [$id]),
            'vereine' => (int) $this->db->wert(
                "SELECT COUNT(DISTINCT a.verein_id) FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id WHERE t.turnier_id = ? AND t.status NOT IN ('abgemeldet', 'nicht_erschienen')",
                [$id],
            ),
            'teams' => (int) $this->db->wert('SELECT COUNT(*) FROM team WHERE turnier_id = ?', [$id]),
            'klassen' => (int) $this->db->wert("SELECT COUNT(*) FROM klasse WHERE turnier_id = ? AND status <> 'zusammengelegt'", [$id]),
            'starts' => (int) $this->db->wert(
                "SELECT COUNT(*) FROM start s JOIN klasse k ON k.id = s.klasse_id WHERE k.turnier_id = ? AND s.status <> 'zurueckgezogen'",
                [$id],
            ),
            // Kämpfe und Kata-Vorträge zusammen (wie auf der Statusseite)
            'begegnungen' => (int) $this->db->wert(
                "SELECT (SELECT COUNT(*) FROM begegnung b JOIN phase p ON p.id = b.phase_id JOIN klasse k ON k.id = p.klasse_id
                          WHERE k.turnier_id = ? AND b.status <> 'freilos')
                      + (SELECT COUNT(*) FROM auftritt a JOIN phase p ON p.id = a.phase_id JOIN klasse k ON k.id = p.klasse_id
                          WHERE k.turnier_id = ?)",
                [$id, $id],
            ),
            'beendet' => (int) $this->db->wert(
                "SELECT (SELECT COUNT(*) FROM begegnung b JOIN phase p ON p.id = b.phase_id JOIN klasse k ON k.id = p.klasse_id
                          WHERE k.turnier_id = ? AND b.status = 'beendet')
                      + (SELECT COUNT(*) FROM auftritt a JOIN phase p ON p.id = a.phase_id JOIN klasse k ON k.id = p.klasse_id
                          WHERE k.turnier_id = ? AND a.status = 'beendet')",
                [$id, $id],
            ),
        ];
        $hinweise = (new \Wettkampf\Durchfuehrung\KlassenService($this->db))->hinweise($id);
        $phase = TurnierPhase::von($turnier);
        $phasen = new TurnierPhaseService($this->db);
        $offenePunkte = [
            TurnierPhase::LAEUFT => $phasen->pruefen($id, TurnierPhase::LAEUFT),
            'laeuft_rest' => $phasen->nichtBehebbar($id, TurnierPhase::LAEUFT),
            TurnierPhase::ABGESCHLOSSEN => $phasen->pruefen($id, TurnierPhase::ABGESCHLOSSEN),
        ];
        $nichtErschienen = (new AusrufService($this->db))->nichtErschienen($id);
        // Meldephase nur als erledigt zeigen, wenn es sie gab (Phasenwechsel im Protokoll oder Online-Anmeldungen)
        $meldephaseGewesen = $this->db->wert(
            "SELECT 1 FROM audit_log WHERE turnier_id = ? AND aktion = 'phase' AND daten LIKE ? LIMIT 1",
            [$id, '%"neu":"' . TurnierPhase::MELDEPHASE . '"%'],
        ) !== null || $this->db->wert('SELECT 1 FROM anmeldung WHERE turnier_id = ? LIMIT 1', [$id]) !== null;
        return $this->seite('turnier/uebersicht', compact('turnier', 'zahlen', 'hinweise', 'phase', 'offenePunkte', 'nichtErschienen', 'meldephaseGewesen') + [
            'aktiv' => 'uebersicht',
            // nur solange das Turnier noch nicht abgeschlossen ist – danach braucht es keinen Hallen-Laptop mehr
            'zweiterFaktor' => $phase === TurnierPhase::ABGESCHLOSSEN ? null : $this->app->zweiterFaktorHinweis($id),
        ]);
    }

    /** Entscheidung zu einer Rückmeldung „nicht erschienen“ aus dem Ausruf. */
    public function nichtErschienen(Request $request, int $id): Response
    {
        $this->turnier($id);
        (new TurnierPhaseService($this->db))->ergebnisseErlaubt($id);
        $text = (new AusrufService($this->db))->entscheiden($id, $request->text('typ'), (int) $request->zahl('eintrag'), $request->text('seite'), $request->text('aktion'));
        $this->erfolg($text);
        return $this->weiter("turnier/$id");
    }

    /** Leitung quittiert die Meldung „alle Kata gelaufen“. */
    public function kataBeendet(Request $request, int $id): Response
    {
        $this->turnier($id);
        (new TurnierPhaseService($this->db))->kataBeendetQuittieren($id);
        return $this->weiter("turnier/$id");
    }

    /** Wechselt die Turnierphase (Vorbereitung, Meldephase, läuft, abgeschlossen). */
    public function phase(Request $request, int $id): Response
    {
        $this->turnier($id);
        $ziel = $request->text('phase');
        $phasen = new TurnierPhaseService($this->db);
        $vorbereitet = $ziel === TurnierPhase::LAEUFT && $request->post('vorbereiten') !== null
            ? $phasen->startVorbereiten($id) : null;
        $phasen->setzen($id, $ziel, $request->post('trotzdem') !== null);
        $this->erfolg('Turnierphase: ' . TurnierPhase::text($ziel) . '.'
            . ($vorbereitet ? " {$vorbereitet['ausgelost']} Klassen ausgelost, {$vorbereitet['verteilt']} Gruppen auf die Tatamis verteilt." : ''));
        return $this->weiterNach($request, "turnier/$id");
    }

    public function einstellungen(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $tatamis = $this->db->alle('SELECT * FROM tatami WHERE turnier_id = ? ORDER BY nummer', [$id]);
        return $this->seite('turnier/formular', ['turnier' => $turnier, 'werte' => $turnier, 'tatamis' => $tatamis, 'aktiv' => 'einstellungen']);
    }

    public function speichern(Request $request, int $id): Response
    {
        $this->turnier($id);
        (new StammdatenService($this->db))->turnierAktualisieren($id, $request->post);
        $this->erfolg('Einstellungen gespeichert.');
        return $this->weiterNach($request, "turnier/$id/einstellungen");
    }

    /** PINs für Tatami-Tablets und für Korrekturen durch die Leitung setzen. */
    public function zugaengeSpeichern(Request $request, int $id): Response
    {
        $this->turnier($id);
        $leitungPin = $request->text('leitung_pin');
        if ($leitungPin !== '') {
            $this->pinPruefen($leitungPin);
            $this->db->update('turnier', ['leitung_pin_hash' => password_hash($leitungPin, PASSWORD_DEFAULT)], ['id' => $id]);
        }
        foreach ((array) $request->post('tatami', []) as $tatamiId => $felder) {
            $tatami = $this->datensatz('tatami', (int) $tatamiId, $id);
            $stream = mb_substr(trim((string) ($felder['stream_url'] ?? '')), 0, 500);
            // Link auf der öffentlichen Statusseite: nur http(s), kein javascript:/data: (OWASP A05)
            if ($stream !== '' && preg_match('#^https?://[^\s"<>]+$#i', $stream) !== 1) {
                throw new FachFehler('Die Stream-Adresse muss mit https:// beginnen, z. B. https://youtube.com/live/…');
            }
            $aenderung = [
                'bezeichnung' => trim((string) ($felder['bezeichnung'] ?? '')) ?: $tatami['bezeichnung'],
                'stream_url' => $stream ?: null,
            ];
            $pin = trim((string) ($felder['pin'] ?? ''));
            if ($pin !== '') {
                $this->pinPruefen($pin);
                $aenderung['pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
            }
            $this->db->update('tatami', $aenderung, ['id' => $tatami['id']]);
        }
        $einstellungen = json_decode((string) $this->db->wert('SELECT einstellungen FROM turnier WHERE id = ?', [$id]), true) ?: [];
        $einstellungen['aka_seite'] = $request->text('aka_seite') === 'links' ? 'links' : 'rechts';
        $einstellungen['kampfuhr'] = [
            'aktiv' => $request->post('kampfuhr_aktiv') === '1',
            'minuten' => (float) str_replace(',', '.', $request->text('kampfuhr_minuten', '2')),
            'minuten_finale' => (float) str_replace(',', '.', $request->text('kampfuhr_minuten_finale', '2')),
            'ton' => $request->post('kampfuhr_ton') === '1',
        ];
        // auf erlaubte Werte begrenzen (1–5 Minuten in halben Minuten)
        $einstellungen['kampfuhr'] = AusrufService::kampfuhr(['einstellungen' => json_encode($einstellungen)]);
        $this->db->update('turnier', ['einstellungen' => json_encode($einstellungen, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)], ['id' => $id]);
        (new Protokoll($this->db))->eintragen($id, 'turnier', $id, 'zugaenge_geaendert');
        $this->erfolg('Zugänge gespeichert.');
        return $this->weiterNach($request, "turnier/$id/einstellungen");
    }

    public function loeschen(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        if ($request->text('bestaetigung') !== $turnier['name']) {
            throw new FachFehler('Zum Löschen bitte den Turniernamen exakt eingeben.');
        }
        $geloescht = (new StammdatenService($this->db))->turnierLoeschen($id);
        (new Protokoll($this->db))->eintragen(null, 'turnier', $id, 'geloescht', ['name' => $turnier['name']] + $geloescht);
        $this->erfolg("Turnier „{$turnier['name']}“ gelöscht"
            . ($geloescht['athleten'] || $geloescht['vereine'] ? " – mit {$geloescht['athleten']} Athleten und {$geloescht['vereine']} Vereinen, die zu keinem anderen Turnier gehören." : '.'));
        return $this->weiter('/');
    }

    private function pinPruefen(string $pin): void
    {
        if (!preg_match('/^\d{4,8}$/', $pin)) {
            throw new FachFehler('Eine PIN besteht aus 4 bis 8 Ziffern.');
        }
    }
}
