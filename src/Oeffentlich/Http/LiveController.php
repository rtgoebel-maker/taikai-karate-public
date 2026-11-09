<?php

declare(strict_types=1);

namespace Wettkampf\Oeffentlich\Http;

use Wettkampf\Durchfuehrung\KlassenAnsicht;
use Wettkampf\Turnier\LogoService;
use Wettkampf\Oeffentlich\StarterlistenAnsicht;
use Wettkampf\Turnier\SystemEinstellungen;
use Wettkampf\Durchfuehrung\TatamiAnsicht;
use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Controller;

/**
 * Öffentliche Statusseite für Besucher: nur lesend, ohne Login,
 * nur Name und Verein (keine Geburtsdaten).
 */
final class LiveController extends Controller
{
    public function tatamis(Request $request, int $id): Response
    {
        $turnier = $this->oeffentlich($id);
        if (!TurnierPhase::gestartet($turnier)) {
            // vor dem Start nur der Hinweis „Live-Anzeige startet, sobald …“ (Layout)
            return $this->live('live/tatamis', $turnier, 'tatamis', ['tatamis' => [], 'zahlen' => null]);
        }
        $tatamis = (new TatamiAnsicht($this->db))->alle($id, 8);
        // Kämpfe und Vorträge zusammen (wie auf den Tatami-Karten)
        $zahlen = ['beendet' => 0, 'gesamt' => 0];
        foreach ([
            "SELECT SUM(CASE WHEN b.status = 'beendet' THEN 1 ELSE 0 END) AS beendet, COUNT(*) AS gesamt
               FROM begegnung b JOIN phase p ON p.id = b.phase_id JOIN klasse k ON k.id = p.klasse_id
              WHERE k.turnier_id = ? AND b.status <> 'freilos'",
            "SELECT SUM(CASE WHEN a.status = 'beendet' THEN 1 ELSE 0 END) AS beendet, COUNT(*) AS gesamt
               FROM auftritt a JOIN phase p ON p.id = a.phase_id JOIN klasse k ON k.id = p.klasse_id
              WHERE k.turnier_id = ?",
        ] as $sql) {
            $z = $this->db->eine($sql, [$id]);
            $zahlen['beendet'] += (int) $z['beendet'];
            $zahlen['gesamt'] += (int) $z['gesamt'];
        }
        return $this->live('live/tatamis', $turnier, 'tatamis', ['tatamis' => $tatamis, 'zahlen' => $zahlen]);
    }

    /** Vollbild-Anzeige einer Tatami (Bildschirm an der Matte): jetzt, bereit machen, Aufruf. */
    public function tatami(Request $request, int $id, string $nummer): Response
    {
        $turnier = $this->oeffentlich($id);
        $tatamiId = $this->db->wert('SELECT id FROM tatami WHERE turnier_id = ? AND nummer = ?', [$id, (int) $nummer]) ?? throw new NichtGefunden('Tatami nicht gefunden.');
        if (!TurnierPhase::gestartet($turnier)) {
            return $this->live('live/tatamis', $turnier, 'tatamis', ['tatamis' => [], 'zahlen' => null]);
        }
        $t = (new TatamiAnsicht($this->db))->fuerTatami((int) $tatamiId, 8);
        return $this->live('live/tatami', $turnier, 'tatamis', ['t' => $t]);
    }

    /**
     * Einblendung für den Livestream einer Tatami (OBS: Browserquelle, 1920×1080). Eigenständige Seite mit
     * transparentem Hintergrund; ?pos=oben, ?hintergrund=gruen (Chroma-Key), ?ergebnis=0 (ohne Sieger-Einblendung).
     */
    public function einblendung(Request $request, int $id, string $nummer): Response
    {
        $turnier = $this->oeffentlich($id);
        $tatami = $this->db->eine('SELECT nummer, bezeichnung FROM tatami WHERE turnier_id = ? AND nummer = ?', [$id, (int) $nummer]) ?? throw new NichtGefunden('Tatami nicht gefunden.');
        return Response::html($this->app->view->render('live/einblendung', [
            'turnier' => $turnier,
            'tatami' => $tatami,
            'oben' => $request->get('pos') === 'oben',
            'gruen' => $request->get('hintergrund') === 'gruen',
            'ergebnis' => $request->get('ergebnis') !== '0',
        ]));
    }

    /** Aushang „Starterlisten & Wettkampfbäume“ (alle Gruppen, filterbar, druckbar). */
    public function starterlisten(Request $request, int $id): Response
    {
        $this->bereichPruefen('starterlisten');
        $turnier = $this->oeffentlich($id);
        $daten = (new StarterlistenAnsicht($this->db))->laden($id);
        $tatamis = $this->db->alle('SELECT nummer, bezeichnung FROM tatami WHERE turnier_id = ? ORDER BY nummer', [$id]);
        return $this->live('live/starterlisten', $turnier, 'starterlisten', $daten + ['tatamis' => $tatamis, 'offline' => false]);
    }

    /**
     * Offlinekopie: dieselbe Seite als eigenständige HTML-Datei (CSS eingebettet,
     * ohne Live-Abfrage) – z. B. für Handys ohne Netz oder als Aushang-Datei.
     */
    public function starterlistenOffline(Request $request, int $id): Response
    {
        $this->bereichPruefen('starterlisten');
        $turnier = $this->oeffentlich($id);
        $daten = (new StarterlistenAnsicht($this->db))->laden($id);
        $tatamis = $this->db->alle('SELECT nummer, bezeichnung FROM tatami WHERE turnier_id = ? ORDER BY nummer', [$id]);
        $html = $this->app->view->render('live/starterlisten', $daten + [
            'turnier' => $turnier, 'aktiv' => 'starterlisten', 'tatamis' => $tatamis, 'offline' => true, 'logos' => $this->statusLogos($id, true),
            'css' => (string) file_get_contents($this->app->wurzel . '/public/assets/app.css')
                . (string) file_get_contents($this->app->wurzel . '/public/assets/baum.css'),
            'js' => (string) file_get_contents($this->app->wurzel . '/public/assets/starterlisten.js'),
        ]);
        $name = 'Starterlisten_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $turnier['name']) . '_' . date('Y-m-d_Hi') . '.html';
        return Response::download($html, $name, 'text/html; charset=utf-8');
    }

    /** Frühere Adressen leiten auf den Aushang (bzw. den Gruppenanker) weiter. */
    public function klassen(Request $request, int $id): Response
    {
        $this->bereichPruefen('starterlisten');
        $this->oeffentlich($id);
        return $this->weiter("live/$id/starterlisten");
    }

    public function klasse(Request $request, int $id, int $klasseid): Response
    {
        $this->bereichPruefen('starterlisten');
        $this->oeffentlich($id);
        $uuid = $this->db->wert('SELECT uuid FROM klasse WHERE id = ? AND turnier_id = ?', [$klasseid, $id])
            ?? throw new NichtGefunden('Klasse nicht gefunden.');
        return Response::weiterleiten(\Wettkampf\Kern\Http\Url::zu("live/$id/starterlisten") . '#' . StarterlistenAnsicht::anker((string) $uuid));
    }

    public function ergebnisse(Request $request, int $id): Response
    {
        $this->bereichPruefen('ergebnisse');
        $turnier = $this->oeffentlich($id);
        $ansicht = new KlassenAnsicht($this->db);
        $klassen = [];
        foreach ($this->db->alle("SELECT * FROM klasse WHERE turnier_id = ? AND status = 'bestaetigt' ORDER BY sortierung, code", [$id]) as $k) {
            $k['platzierungen'] = array_map(
                static fn($p) => $p + $ansicht->person((int) $p['start_id']),
                $this->db->alle('SELECT * FROM platzierung WHERE klasse_id = ? ORDER BY platz', [$k['id']]),
            );
            $klassen[] = $k;
        }
        return $this->live('live/ergebnisse', $turnier, 'ergebnisse', ['klassen' => $klassen]);
    }

    /** Starter oder Verein suchen: Klassen und nächste Kämpfe. */
    public function suche(Request $request, int $id): Response
    {
        $this->bereichPruefen('starterlisten');
        $turnier = $this->oeffentlich($id);
        $q = $request->text('q');
        $treffer = [];
        if (mb_strlen($q) >= 2) {
            $muster = '%' . mb_strtolower($q) . '%';
            foreach ($this->db->alle(
                "SELECT s.id AS start_id, k.id AS klasse_id, k.code, k.bezeichnung, k.status,
                        tm.name AS team, a.vorname, a.nachname, COALESCE(v.name, vt.name) AS verein
                   FROM start s JOIN klasse k ON k.id = s.klasse_id
                   LEFT JOIN teilnahme t ON t.id = s.teilnahme_id LEFT JOIN athlet a ON a.id = t.athlet_id
                   LEFT JOIN verein v ON v.id = a.verein_id LEFT JOIN team tm ON tm.id = s.team_id LEFT JOIN verein vt ON vt.id = tm.verein_id
                  WHERE k.turnier_id = :t AND k.status NOT IN ('offen', 'zusammengelegt') AND s.status <> 'zurueckgezogen'
                    AND (LOWER(a.vorname) LIKE :q OR LOWER(a.nachname) LIKE :q OR LOWER(v.name) LIKE :q OR LOWER(tm.name) LIKE :q OR LOWER(vt.name) LIKE :q)
                  ORDER BY a.nachname, tm.name, k.sortierung
                  LIMIT 100",
                ['t' => $id, 'q' => $muster],
            ) as $z) {
                $z['name'] = $z['team'] ?? trim("{$z['vorname']} {$z['nachname']}");
                $z['naechster'] = $this->naechsterKampf((int) $z['start_id']);
                $treffer[] = $z;
            }
        }
        return $this->live('live/suche', $turnier, 'suche', ['q' => $q, 'treffer' => $treffer]);
    }

    /** @return array<string, mixed>|null */
    private function naechsterKampf(int $startId): ?array
    {
        return $this->db->eine(
            "SELECT b.id, b.status, ta.bezeichnung AS tatami, p.bezeichnung AS phase
               FROM begegnung b JOIN phase p ON p.id = b.phase_id LEFT JOIN tatami ta ON ta.id = p.tatami_id
              WHERE (b.aka_start_id = ? OR b.shiro_start_id = ?) AND b.status IN ('bereit', 'laeuft')
              ORDER BY b.runde, b.position",
            [$startId, $startId],
        );
    }

    /** Ausgeblendete Bereiche der Statusseite (Systemeinstellungen) sind nicht erreichbar. */
    private function bereichPruefen(string $bereich): void
    {
        if (!((new SystemEinstellungen($this->db))->statusBereiche()[$bereich] ?? true)) {
            throw new NichtGefunden('Dieser Bereich der Statusseite ist ausgeblendet.');
        }
    }

    /** @return array<string, mixed> */
    private function oeffentlich(int $id): array
    {
        $t = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$id]);
        if ($t === null || (!(int) $t['oeffentlich'] && !Session::leitet($id))) {
            throw new NichtGefunden('Diese Statusseite ist nicht öffentlich.');
        }
        return $t;
    }

    /** @param array<string, mixed> $turnier @param array<string, mixed> $daten */
    private function live(string $template, array $turnier, string $aktiv, array $daten): Response
    {
        return Response::html($this->app->view->render($template, $daten + ['turnier' => $turnier, 'aktiv' => $aktiv, 'logos' => $this->statusLogos((int) $turnier['id'], false)]));
    }

    /**
     * Vereins-/Dojo-Logo des Ausrichters für die Kopfzeile – nur die in den Systemeinstellungen
     * freigegebenen Arten (Unterschriften und übrige Bilder sind hier nie erreichbar).
     */
    public function logo(Request $request, int $id, string $art): Response
    {
        $this->oeffentlich($id);
        if (!in_array($art, (new SystemEinstellungen($this->db))->statusLogos(), true)) {
            throw new NichtGefunden('Logo nicht gefunden.');
        }
        $bild = (new LogoService($this->db))->bild($id, $art) ?? throw new NichtGefunden('Logo nicht gefunden.');
        return new Response($bild['inhalt'], 200, ['Content-Type' => $bild['mime'], 'Cache-Control' => 'public, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * @return list<array{art: string, src: string}> eingeschaltete und vorhandene Logos;
     *         eingebettet als Data-URL (Offlinekopie) oder als Adresse
     */
    private function statusLogos(int $id, bool $eingebettet): array
    {
        $logos = new LogoService($this->db);
        $vorhanden = $logos->vorhandene($id);
        $liste = [];
        foreach ((new SystemEinstellungen($this->db))->statusLogos() as $art) {
            if (!isset($vorhanden[$art])) {
                continue;
            }
            if ($eingebettet) {
                $bild = $logos->bild($id, $art);
                $src = 'data:' . $bild['mime'] . ';base64,' . base64_encode($bild['inhalt']);
            } else {
                $src = url("live/$id/logo/$art", ['v' => $vorhanden[$art]['version']]);
            }
            $liste[] = ['art' => $art, 'src' => $src];
        }
        return $liste;
    }
}
