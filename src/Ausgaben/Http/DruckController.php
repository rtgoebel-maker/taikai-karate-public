<?php

declare(strict_types=1);

namespace Wettkampf\Ausgaben\Http;

use Wettkampf\Durchfuehrung\KlassenAnsicht;
use Wettkampf\Turnier\LogoService;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Controller;

/**
 * Druckausgaben (A4, Print-CSS): Bäume, Kampflisten, Ergebnis- und Siegerlisten.
 */
final class DruckController extends Controller
{
    public function center(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $tatamis = $this->db->alle(
            'SELECT t.*, (SELECT COUNT(*) FROM phase p WHERE p.tatami_id = t.id) AS phasen FROM tatami t WHERE t.turnier_id = ? ORDER BY t.nummer',
            [$id],
        );
        $klassen = $this->db->alle(
            "SELECT id, code, bezeichnung, status FROM klasse WHERE turnier_id = ? AND status NOT IN ('offen', 'zusammengelegt') ORDER BY sortierung, code",
            [$id],
        );
        $urkunde = $this->urkundenEinstellungen($turnier);
        $logos = (new LogoService($this->db))->vorhandene($id);
        return $this->seite('druck/center', compact('turnier', 'tatamis', 'klassen', 'urkunde', 'logos') + ['aktiv' => 'druck']);
    }

    /** Turnierbaum / Pool-Listen einer Klasse. */
    public function klasse(Request $request, int $id, int $klasseid): Response
    {
        $turnier = $this->turnier($id);
        $ansicht = (new KlassenAnsicht($this->db))->laden($klasseid);
        if ((int) $ansicht['klasse']['turnier_id'] !== $id) {
            throw new NichtGefunden('Klasse nicht gefunden.');
        }
        return $this->druck('druck/baeume', 'Turnierbaum ' . $ansicht['klasse']['code'], $turnier, ['klassen' => [$ansicht]]);
    }

    /** Bäume aller (oder ausgewählter) ausgeloster Klassen. */
    public function baeume(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $ids = array_map('intval', (array) $request->get('klassen', []));
        if ($ids === []) {
            $ids = array_map('intval', $this->db->spalte(
                "SELECT id FROM klasse WHERE turnier_id = ? AND status NOT IN ('offen', 'zusammengelegt') ORDER BY sortierung, code",
                [$id],
            ));
        }
        $ansicht = new KlassenAnsicht($this->db);
        $klassen = [];
        foreach ($ids as $kid) {
            $k = $ansicht->laden($kid);
            if ((int) $k['klasse']['turnier_id'] === $id) {
                $klassen[] = $k;
            }
        }
        return $this->druck('druck/baeume', 'Turnierbäume', $turnier, ['klassen' => $klassen]);
    }

    /**
     * Notfall-Satz: je ausgeloster Klasse Turnierbaum/Pool-Liste und Kampfliste aller
     * Kämpfe, mit Feld für die Tatami (von Hand) – Backup bei Technikausfall.
     */
    public function notfall(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $saetze = [];
        foreach ($this->ausgewaehlteKlassen($request, $id) as $k) {
            $bloecke = [];
            foreach ($k['phasen'] as $phase) {
                $phase['begegnungen'] = array_values(array_filter($phase['begegnungen'], static fn($b) => $b['status'] !== 'freilos'));
                $bloecke[] = ['klasse' => $k['klasse'], 'phase' => $phase];
            }
            $saetze[] = ['k' => $k, 'bloecke' => $bloecke];
        }
        return $this->druck('druck/notfall', 'Notfall-Satz (' . count($saetze) . ' Klassen)', $turnier, ['saetze' => $saetze]);
    }

    /** Aushang: Bäume und Pool-Listen groß, je Klasse ein Blatt – ersetzt die Statusseite. */
    public function aushang(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $format = $request->get('format') === 'a3' ? 'a3' : 'a4';
        return $this->druck('druck/baeume', 'Aushang', $turnier, ['klassen' => $this->ausgewaehlteKlassen($request, $id), 'aushang' => $format]);
    }

    /** @return list<array<string, mixed>> Lesemodelle der gewählten (sonst aller ausgelosten) Klassen */
    private function ausgewaehlteKlassen(Request $request, int $id): array
    {
        $ids = array_map('intval', (array) $request->get('klassen', []));
        if ($ids === []) {
            $ids = array_map('intval', $this->db->spalte(
                "SELECT id FROM klasse WHERE turnier_id = ? AND status NOT IN ('offen', 'zusammengelegt') ORDER BY sortierung, code",
                [$id],
            ));
        }
        $ansicht = new KlassenAnsicht($this->db);
        $klassen = [];
        foreach ($ids as $kid) {
            $k = $ansicht->laden($kid);
            if ((int) $k['klasse']['turnier_id'] === $id && $k['klasse']['status'] !== 'offen') {
                $klassen[] = $k;
            }
        }
        return $klassen;
    }

    /** Kampfliste für die Tischbesatzung einer Tatami. */
    public function kampfliste(Request $request, int $id, int $tatamiid): Response
    {
        $turnier = $this->turnier($id);
        $tatami = $this->datensatz('tatami', $tatamiid, $id);
        $nurOffen = $request->get('offen') === '1';
        $ansicht = new KlassenAnsicht($this->db);
        $cache = [];
        $bloecke = [];
        foreach ($this->db->alle('SELECT * FROM phase WHERE tatami_id = ? ORDER BY reihenfolge, id', [$tatamiid]) as $p) {
            $kid = (int) $p['klasse_id'];
            $cache[$kid] ??= $ansicht->laden($kid);
            foreach ($cache[$kid]['phasen'] as $phase) {
                if ((int) $phase['id'] !== (int) $p['id']) {
                    continue;
                }
                $phase['begegnungen'] = array_values(array_filter(
                    $phase['begegnungen'],
                    static fn($b) => $b['status'] !== 'freilos' && (!$nurOffen || $b['status'] !== 'beendet'),
                ));
                $phase['auftritte'] = array_values(array_filter(
                    $phase['auftritte'],
                    static fn($a) => !$nurOffen || $a['status'] !== 'beendet',
                ));
                if ($phase['begegnungen'] || $phase['auftritte'] || $phase['status'] === 'wartet') {
                    $bloecke[] = ['klasse' => $cache[$kid]['klasse'], 'phase' => $phase];
                }
            }
        }
        return $this->druck('druck/kampfliste', 'Kampfliste ' . $tatami['bezeichnung'], $turnier, ['tatami' => $tatami, 'bloecke' => $bloecke]);
    }

    public function ergebnisse(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $ansicht = new KlassenAnsicht($this->db);
        $klassen = array_map(
            static fn($kid) => $ansicht->laden((int) $kid),
            $this->db->spalte("SELECT id FROM klasse WHERE turnier_id = ? AND status IN ('laeuft', 'beendet', 'bestaetigt') ORDER BY sortierung, code", [$id]),
        );
        return $this->druck('druck/ergebnisse', 'Ergebnisliste', $turnier, ['klassen' => $klassen]);
    }

    public function siegerliste(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $alle = $request->get('alle') === '1';
        $ansicht = new KlassenAnsicht($this->db);
        $klassen = [];
        foreach ($this->db->alle(
            "SELECT * FROM klasse WHERE turnier_id = ? AND status IN ('beendet', 'bestaetigt') ORDER BY sortierung, code",
            [$id],
        ) as $k) {
            if (!$alle && $k['status'] !== 'bestaetigt') {
                continue;
            }
            $k['platzierungen'] = array_map(
                static fn($p) => $p + $ansicht->person((int) $p['start_id']),
                $this->db->alle('SELECT * FROM platzierung WHERE klasse_id = ? ORDER BY platz', [$k['id']]),
            );
            $klassen[] = $k;
        }
        return $this->druck('druck/siegerliste', 'Siegerliste', $turnier, ['klassen' => $klassen, 'alle' => $alle]);
    }

    /**
     * Urkunden für bestätigte Platzierungen – eine Seite je Platz, bei Teams je
     * Mitglied. ?klasse=ID für eine Klasse (sonst alle bestätigten), ?vordruck=1
     * nur Text für vorgedrucktes Urkundenpapier.
     */
    public function urkunden(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $klasseId = $request->zahl('klasse');
        $ansicht = new KlassenAnsicht($this->db);
        $urkunden = [];
        foreach ($this->db->alle(
            "SELECT * FROM klasse WHERE turnier_id = ? AND status = 'bestaetigt'" . ($klasseId ? ' AND id = ?' : '') . ' ORDER BY sortierung, code',
            $klasseId ? [$id, $klasseId] : [$id],
        ) as $k) {
            foreach ($this->db->alle('SELECT p.*, s.team_id FROM platzierung p JOIN start s ON s.id = p.start_id WHERE p.klasse_id = ? ORDER BY p.platz', [$k['id']]) as $p) {
                $person = $ansicht->person((int) $p['start_id']);
                if ($p['team_id'] === null) {
                    $urkunden[] = ['klasse' => $k, 'platz' => (int) $p['platz'], 'name' => $person['name'], 'verein' => $person['verein_voll'], 'team' => null];
                    continue;
                }
                $mitglieder = $this->db->alle(
                    "SELECT a.vorname, a.nachname FROM team_mitglied m JOIN teilnahme t ON t.id = m.teilnahme_id JOIN athlet a ON a.id = t.athlet_id
                      WHERE m.team_id = ? ORDER BY m.rolle DESC, a.nachname",
                    [$p['team_id']],
                ) ?: [['vorname' => '', 'nachname' => $person['name']]];
                foreach ($mitglieder as $m) {
                    $urkunden[] = ['klasse' => $k, 'platz' => (int) $p['platz'], 'name' => trim($m['vorname'] . ' ' . $m['nachname']), 'verein' => $person['verein_voll'], 'team' => $person['name']];
                }
            }
        }
        if ($klasseId && $urkunden === []) {
            $klasse = $this->db->eine('SELECT code, status FROM klasse WHERE id = ? AND turnier_id = ?', [$klasseId, $id]) ?? throw new NichtGefunden('Klasse nicht gefunden.');
            throw new \Wettkampf\Domain\FachFehler("Für Klasse {$klasse['code']} gibt es noch keine Urkunden – bitte zuerst die Platzierungen bestätigen.");
        }
        $einstellungen = $this->urkundenEinstellungen($turnier);
        $logos = [];
        foreach ((new LogoService($this->db))->vorhandene($id) as $art => $l) {
            $logos[$art] = url("turnier/$id/logo/$art", ['v' => $l['version']]);
        }
        $inhalt = $this->app->view->render('druck/urkunden', [
            'logos' => $logos,
            'turnier' => $turnier,
            'urkunden' => $urkunden,
            'vordruck' => $request->get('vordruck') === '1',
            'e' => $einstellungen,
        ]);
        return Response::html($this->app->view->render('druck/layout', [
            'inhalt' => $inhalt,
            'titel' => 'Urkunden (' . count($urkunden) . ')',
            'turnier' => $turnier,
            'zusatz' => $request->get('vordruck') === '1'
                ? '<a href="' . e(url("turnier/$id/druck/urkunden", array_filter(['klasse' => $klasseId]))) . '">mit Gestaltung</a>'
                : '<a href="' . e(url("turnier/$id/druck/urkunden", array_filter(['klasse' => $klasseId, 'vordruck' => 1]))) . '">nur Text (Vordruck)</a>',
        ]));
    }

    /** Logo als Bild ausliefern (für Urkunden und Vorschau im Druckcenter). */
    public function logo(Request $request, int $id, string $art): Response
    {
        $this->turnier($id);
        $bild = (new LogoService($this->db))->bild($id, $art) ?? throw new NichtGefunden('Logo nicht gefunden.');
        return new Response($bild['inhalt'], 200, [
            'Content-Type' => $bild['mime'],
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function urkundenSpeichern(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $alle = json_decode((string) $turnier['einstellungen'], true) ?: [];
        $alle['urkunde'] = [
            'untertitel' => mb_substr($request->text('untertitel'), 0, 200),
            'unterschrift_links' => mb_substr($request->text('unterschrift_links'), 0, 100),
            'unterschrift_rechts' => mb_substr($request->text('unterschrift_rechts'), 0, 100),
        ];
        $this->db->update('turnier', ['einstellungen' => json_encode($alle, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)], ['id' => $id]);
        // Logos: hochladen (ersetzt) bzw. entfernen
        $logos = new LogoService($this->db);
        $geaendert = [];
        foreach (LogoService::ARTEN + LogoService::UNTERSCHRIFTEN + LogoService::HINTERGRUND as $art => $name) {
            $datei = $request->files["logo_$art"] ?? null;
            if (is_array($datei) && ($datei['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $logos->speichern($id, $art, (string) $datei['tmp_name']);
                $geaendert[] = $name;
            } elseif (is_array($datei) && in_array($datei['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new \Wettkampf\Domain\FachFehler("$name: Datei ist zu groß (max. " . intdiv(LogoService::maxBytes($art), 1024 * 1024) . ' MB bzw. die Grenze des Servers).');
            } elseif (in_array($art, (array) $request->post('logo_entfernen', []), true)) {
                $logos->entfernen($id, $art);
                $geaendert[] = "$name entfernt";
            }
        }
        $this->erfolg('Urkunden gespeichert.' . ($geaendert ? ' ' . implode(', ', $geaendert) . '.' : ''));
        return $this->weiter("turnier/$id/druck");
    }

    /**
     * @param array<string, mixed> $turnier
     * @return array{untertitel: string, unterschrift_links: string, unterschrift_rechts: string}
     */
    private function urkundenEinstellungen(array $turnier): array
    {
        $u = (json_decode((string) ($turnier['einstellungen'] ?? ''), true) ?: [])['urkunde'] ?? [];
        return [
            'untertitel' => (string) ($u['untertitel'] ?? ''),
            'unterschrift_links' => (string) (($u['unterschrift_links'] ?? '') ?: 'Wettkampfleitung'),
            'unterschrift_rechts' => (string) (($u['unterschrift_rechts'] ?? '') ?: 'Veranstalter'),
        ];
    }

    /** Starter je Verein mit ihren Klassen (für Coaches / Einlass). */
    public function vereine(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $vereine = [];
        foreach ($this->db->alle(
            "SELECT t.startnummer, a.vorname, a.nachname, a.graduierung, COALESCE(v.name, 'ohne Verein') AS verein,
                    (SELECT GROUP_CONCAT(k.code) FROM start s JOIN klasse k ON k.id = s.klasse_id
                      WHERE s.teilnahme_id = t.id AND s.status <> 'zurueckgezogen') AS klassen
               FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id LEFT JOIN verein v ON v.id = a.verein_id
              WHERE t.turnier_id = ? AND t.status NOT IN ('abgemeldet', 'nicht_erschienen')
              ORDER BY verein, a.nachname, a.vorname",
            [$id],
        ) as $z) {
            $vereine[$z['verein']][] = $z;
        }
        return $this->druck('druck/vereine', 'Starter nach Verein', $turnier, ['vereine' => $vereine]);
    }

    /** @param array<string, mixed> $turnier @param array<string, mixed> $daten */
    private function druck(string $template, string $titel, array $turnier, array $daten): Response
    {
        $inhalt = $this->app->view->render($template, $daten + ['turnier' => $turnier]);
        return Response::html($this->app->view->render('druck/layout', ['inhalt' => $inhalt, 'titel' => $titel, 'turnier' => $turnier]));
    }
}
