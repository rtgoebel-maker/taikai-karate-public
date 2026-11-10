<?php

declare(strict_types=1);

namespace Wettkampf\Meldung;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Durchfuehrung\TatamiAnsicht;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Drossel;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Kern\Http\NichtGefunden;

/**
 * Coaching-Portal: was ein Dojo (Coach-Konto) je Turnier sieht – eigene Starter live, Ergebnisse,
 * Korrekturanträge, Startgeld. Die Turnierleitung schaltet das Portal und jeden Bereich je Turnier.
 *
 * Zugriff (OWASP A01/API1): Jede Abfrage ist auf das Dojo des Kontos beschränkt; fremde Turniere,
 * Teilnehmer und Anträge gelten als „nicht gefunden“.
 */
final class CoachService
{
    /** Bereiche des Portals je Turnier (einzeln schaltbar) */
    public const BEREICHE = [
        'starter' => 'Meine Starter live',
        'ergebnisse' => 'Ergebnisse des Dojos',
        'antraege' => 'Korrekturanträge',
        'startgeld' => 'Startgeld-Übersicht',
    ];

    /** Arten von Korrekturanträgen */
    public const ANTRAG_ARTEN = [
        'name' => 'Name',
        'geburtsdatum' => 'Geburtsdatum',
        'graduierung' => 'Graduierung (Kyu/Dan)',
        'klasse' => 'Klasse / Disziplin',
        'sonstiges' => 'Sonstiges',
    ];

    public const ANTRAG_STATUS = ['offen' => 'offen', 'angenommen' => 'angenommen', 'abgelehnt' => 'abgelehnt'];

    /** Höchstens so viele Korrekturanträge je Dojo und Stunde. */
    public const MAX_ANTRAEGE_JE_STUNDE = 10;

    public function __construct(private readonly Database $db) {}

    /**
     * Einstellung je Turnier: Portal an/aus (Standard aus) und welche Bereiche sichtbar sind (Standard alle).
     *
     * @param array<string, mixed> $turnier
     * @return array{aktiv: bool, bereiche: array<string, bool>}
     */
    public static function einstellungen(array $turnier): array
    {
        $e = (json_decode((string) ($turnier['einstellungen'] ?? ''), true) ?: [])['coach'] ?? [];
        $bereiche = [];
        foreach (array_keys(self::BEREICHE) as $b) {
            $bereiche[$b] = ($e['bereiche'][$b] ?? true) !== false;
        }
        return ['aktiv' => !empty($e['aktiv']), 'bereiche' => $bereiche];
    }

    /** @param array<string, mixed> $daten aktiv, bereiche[] (Formular der Turnierleitung) */
    public function einstellungenSpeichern(int $turnierId, array $daten): void
    {
        $e = json_decode((string) $this->db->wert('SELECT einstellungen FROM turnier WHERE id = ?', [$turnierId]), true) ?: [];
        $gewaehlt = array_map('strval', (array) ($daten['bereiche'] ?? []));
        $e['coach'] = [
            'aktiv' => ($daten['aktiv'] ?? '') === '1',
            'bereiche' => array_combine(array_keys(self::BEREICHE), array_map(static fn(string $b) => in_array($b, $gewaehlt, true), array_keys(self::BEREICHE))),
        ];
        $this->db->update('turnier', ['einstellungen' => json_encode($e, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)], ['id' => $turnierId]);
    }

    /**
     * Turniere mit eingeschaltetem Portal, an denen das Dojo teilnimmt (Einzelstarter oder Team).
     *
     * @return list<array<string, mixed>> Turnier + 'portal' (Einstellungen) + 'starter' (Anzahl)
     */
    public function turniere(int $vereinId): array
    {
        $liste = [];
        foreach ($this->db->alle(
            'SELECT t.*,
                    (SELECT COUNT(*) FROM teilnahme ta JOIN athlet a ON a.id = ta.athlet_id WHERE ta.turnier_id = t.id AND a.verein_id = ?) AS starter,
                    (SELECT COUNT(*) FROM team tm WHERE tm.turnier_id = t.id AND tm.verein_id = ?) AS teams
               FROM turnier t ORDER BY t.datum DESC, t.id DESC',
            [$vereinId, $vereinId],
        ) as $t) {
            $portal = self::einstellungen($t);
            if ($portal['aktiv'] && ((int) $t['starter'] > 0 || (int) $t['teams'] > 0)) {
                $liste[] = $t + ['portal' => $portal];
            }
        }
        return $liste;
    }

    /**
     * Turnier für das Dojo – nur mit eingeschaltetem Portal (und Bereich) und eigener Beteiligung.
     *
     * @return array<string, mixed> Turnier + 'portal'
     */
    public function turnier(int $turnierId, int $vereinId, ?string $bereich = null): array
    {
        foreach ($this->turniere($vereinId) as $t) {
            if ((int) $t['id'] === $turnierId && ($bereich === null || $t['portal']['bereiche'][$bereich])) {
                return $t;
            }
        }
        throw new NichtGefunden('Turnier nicht gefunden oder im Coaching-Portal nicht freigegeben.');
    }

    /**
     * Starts des Dojos (Einzel über die Athleten, Teams über das Team).
     *
     * @return list<array{start_id: int, klasse_id: int, code: string, klasse: string, klasse_status: string, name: string, team: bool, teilnahme_id: int|null}>
     */
    public function starts(int $turnierId, int $vereinId): array
    {
        $zeilen = $this->db->alle(
            "SELECT s.id AS start_id, s.teilnahme_id, s.team_id, k.id AS klasse_id, k.code, k.bezeichnung AS klasse, k.status AS klasse_status,
                    a.vorname, a.nachname, tm.name AS team_name
               FROM start s JOIN klasse k ON k.id = s.klasse_id
               LEFT JOIN teilnahme ta ON ta.id = s.teilnahme_id LEFT JOIN athlet a ON a.id = ta.athlet_id
               LEFT JOIN team tm ON tm.id = s.team_id
              WHERE k.turnier_id = ? AND s.status <> 'zurueckgezogen' AND k.status <> 'zusammengelegt'
                AND (a.verein_id = ? OR tm.verein_id = ?)
              ORDER BY k.sortierung, k.code, a.nachname, a.vorname, tm.name",
            [$turnierId, $vereinId, $vereinId],
        );
        return array_map(static fn(array $z) => [
            'start_id' => (int) $z['start_id'],
            'klasse_id' => (int) $z['klasse_id'],
            'code' => (string) $z['code'],
            'klasse' => (string) $z['klasse'],
            'klasse_status' => (string) $z['klasse_status'],
            'name' => $z['team_id'] !== null ? (string) $z['team_name'] : trim($z['vorname'] . ' ' . $z['nachname']),
            'team' => $z['team_id'] !== null,
            'teilnahme_id' => $z['teilnahme_id'] === null ? null : (int) $z['teilnahme_id'],
        ], $zeilen);
    }

    /**
     * „Meine Starter live“: je Start der nächste offene Kampf bzw. Vortrag mit Tatami, Gegner,
     * Stand im Ausruf und wie viele Kämpfe/Vorträge auf der Tatami noch davor liegen.
     *
     * @return list<array<string, mixed>>
     */
    public function starterLive(int $turnierId, int $vereinId): array
    {
        $turnier = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$turnierId]) ?? [];
        $gestartet = TurnierPhase::gestartet($turnier);
        $ansicht = new TatamiAnsicht($this->db);
        $tatamis = [];
        $liste = [];
        foreach ($this->starts($turnierId, $vereinId) as $s) {
            $naechster = $this->naechsterEintrag($s['start_id']);
            $platz = $this->db->wert('SELECT platz FROM platzierung WHERE start_id = ?', [$s['start_id']]);
            $info = ['stand' => 'wartet', 'tatami' => null, 'vorher' => null, 'gegner' => null, 'runde' => null, 'seite' => null, 'ausruf' => null, 'platz' => $platz === null ? null : (int) $platz];
            if ($naechster === null) {
                $info['stand'] = $platz !== null ? 'fertig' : (in_array($s['klasse_status'], ['beendet', 'bestaetigt'], true) ? 'ausgeschieden' : ($s['klasse_status'] === 'offen' ? 'nicht_ausgelost' : 'ausgeschieden'));
            } else {
                $info['runde'] = $naechster['runde'];
                $info['tatami'] = $naechster['tatami'];
                if ($naechster['tatami_id'] !== null && $gestartet) {
                    $tatamis[$naechster['tatami_id']] ??= $ansicht->fuerTatami($naechster['tatami_id'], 500);
                    $info = $this->stellung($tatamis[$naechster['tatami_id']], $naechster, $s['start_id']) + $info;
                } elseif ($naechster['tatami_id'] === null) {
                    $info['stand'] = 'warteschlange';
                }
                $info['gegner'] = $naechster['gegner'];
                $info['seite'] = $naechster['seite'];
            }
            $liste[] = $s + $info;
        }
        return $liste;
    }

    /**
     * Nächster offener Kampf/Vortrag eines Starts (auch ein wartender Kampf, dessen Gegner noch fehlt).
     *
     * @return array{typ: string, id: int, tatami_id: int|null, tatami: string|null, runde: string, gegner: string|null, seite: string|null, status: string}|null
     */
    private function naechsterEintrag(int $startId): ?array
    {
        $b = $this->db->eine(
            "SELECT b.id, b.status, b.aka_start_id, b.shiro_start_id, p.tatami_id, p.bezeichnung AS phase, t.bezeichnung AS tatami
               FROM begegnung b JOIN phase p ON p.id = b.phase_id LEFT JOIN tatami t ON t.id = p.tatami_id
              WHERE (b.aka_start_id = ? OR b.shiro_start_id = ?) AND b.status IN ('bereit', 'laeuft', 'wartet')
              ORDER BY CASE b.status WHEN 'laeuft' THEN 0 WHEN 'bereit' THEN 1 ELSE 2 END, p.id, b.runde, b.position LIMIT 1",
            [$startId, $startId],
        );
        if ($b !== null) {
            $seite = (int) $b['aka_start_id'] === $startId ? 'aka' : 'shiro';
            $gegnerId = $seite === 'aka' ? $b['shiro_start_id'] : $b['aka_start_id'];
            return [
                'typ' => 'begegnung', 'id' => (int) $b['id'], 'tatami_id' => $b['tatami_id'] === null ? null : (int) $b['tatami_id'],
                'tatami' => $b['tatami'], 'runde' => (string) $b['phase'], 'status' => (string) $b['status'],
                'gegner' => $gegnerId === null ? null : $this->startName((int) $gegnerId), 'seite' => $seite,
            ];
        }
        $a = $this->db->eine(
            "SELECT a.id, a.reihenfolge, p.tatami_id, p.bezeichnung AS phase, t.bezeichnung AS tatami
               FROM auftritt a JOIN phase p ON p.id = a.phase_id LEFT JOIN tatami t ON t.id = p.tatami_id
              WHERE a.start_id = ? AND a.status = 'offen' AND p.status <> 'wartet' ORDER BY p.id LIMIT 1",
            [$startId],
        );
        return $a === null ? null : [
            'typ' => 'auftritt', 'id' => (int) $a['id'], 'tatami_id' => $a['tatami_id'] === null ? null : (int) $a['tatami_id'],
            'tatami' => $a['tatami'], 'runde' => $a['phase'] . ' · Vortrag ' . $a['reihenfolge'], 'status' => 'offen', 'gegner' => null, 'seite' => 'starter',
        ];
    }

    /**
     * Stellung auf der Tatami: läuft jetzt, Zahl der Kämpfe/Vorträge davor, Stand im Ausruf.
     *
     * @param array<string, mixed> $zustand TatamiAnsicht::fuerTatami
     * @param array<string, mixed> $eintrag
     * @return array{stand: string, vorher: int|null, ausruf: string|null}
     */
    private function stellung(array $zustand, array $eintrag, int $startId): array
    {
        $reihe = array_values(array_filter([$zustand['laufend'], ...$zustand['naechste']]));
        foreach ($reihe as $i => $e) {
            if ($e['typ'] !== $eintrag['typ'] || (int) $e['id'] !== $eintrag['id']) {
                continue;
            }
            $seite = $eintrag['seite'];
            $haken = $e['aufruf'][$seite] ?? null;
            $ausruf = $haken === 'da' ? 'da' : ($haken === 'fehlt' ? 'fehlt' : (in_array($startId, array_column($zustand['aufruf_liste'], 'start_id'), true) ? 'gerufen' : null));
            if ($e['status'] === 'laeuft') {
                return ['stand' => 'laeuft', 'vorher' => 0, 'ausruf' => $ausruf];
            }
            return ['stand' => 'tatami', 'vorher' => $i, 'ausruf' => $ausruf];
        }
        // noch nicht in der Liste der Tatami (z. B. Folgerunde wartet auf andere Kämpfe)
        return ['stand' => $eintrag['status'] === 'wartet' ? 'folgerunde' : 'tatami', 'vorher' => null, 'ausruf' => null];
    }

    private function startName(int $startId): string
    {
        $z = $this->db->eine(
            'SELECT a.vorname, a.nachname, v.name AS verein, tm.name AS team_name, tv.name AS team_verein
               FROM start s LEFT JOIN teilnahme ta ON ta.id = s.teilnahme_id LEFT JOIN athlet a ON a.id = ta.athlet_id
               LEFT JOIN verein v ON v.id = a.verein_id LEFT JOIN team tm ON tm.id = s.team_id LEFT JOIN verein tv ON tv.id = tm.verein_id
              WHERE s.id = ?',
            [$startId],
        ) ?? [];
        $name = $z['team_name'] ?? trim(($z['vorname'] ?? '') . ' ' . ($z['nachname'] ?? ''));
        $verein = $z['team_name'] !== null ? ($z['team_verein'] ?? '') : ($z['verein'] ?? '');
        return $name . ($verein ? " ($verein)" : '');
    }

    /**
     * Ergebnisse des Dojos: Platzierungen (bestätigt bzw. vorläufig), Medaillenspiegel und
     * die entschiedenen Kämpfe/Vorträge der eigenen Starter.
     *
     * @return array{platzierungen: list<array<string, mixed>>, medaillen: array<int, int>, kaempfe: list<array<string, mixed>>}
     */
    public function ergebnisse(int $turnierId, int $vereinId): array
    {
        $starts = $this->starts($turnierId, $vereinId);
        $nachId = array_column($starts, null, 'start_id');
        $platzierungen = [];
        $medaillen = [1 => 0, 2 => 0, 3 => 0];
        $kaempfe = [];
        if ($nachId === []) {
            return ['platzierungen' => [], 'medaillen' => $medaillen, 'kaempfe' => []];
        }
        $in = implode(',', array_map('intval', array_keys($nachId)));
        foreach ($this->db->alle("SELECT start_id, platz, bestaetigt_am FROM platzierung WHERE start_id IN ($in) ORDER BY platz") as $p) {
            $s = $nachId[(int) $p['start_id']];
            $platzierungen[] = $s + ['platz' => (int) $p['platz'], 'bestaetigt' => $p['bestaetigt_am'] !== null];
            if ((int) $p['platz'] <= 3) {
                $medaillen[(int) $p['platz']]++;
            }
        }
        usort($platzierungen, static fn($a, $b) => [$a['platz'], $a['code']] <=> [$b['platz'], $b['code']]);
        foreach ($this->db->alle(
            "SELECT b.id, b.aka_start_id, b.shiro_start_id, b.sieger_start_id, b.entscheidung, b.wertung, b.beendet_am, p.bezeichnung AS phase
               FROM begegnung b JOIN phase p ON p.id = b.phase_id
              WHERE b.status = 'beendet' AND (b.aka_start_id IN ($in) OR b.shiro_start_id IN ($in)) ORDER BY b.beendet_am, b.id",
        ) as $b) {
            foreach (['aka', 'shiro'] as $seite) {
                $id = (int) $b["{$seite}_start_id"];
                if (!isset($nachId[$id])) {
                    continue;
                }
                $gegner = (int) $b[$seite === 'aka' ? 'shiro_start_id' : 'aka_start_id'];
                $kaempfe[] = $nachId[$id] + [
                    'runde' => $b['phase'], 'seite' => $seite, 'gegner' => $gegner ? $this->startName($gegner) : '–',
                    'sieg' => (int) $b['sieger_start_id'] === $id, 'ergebnis' => self::ergebnisText((string) $b['entscheidung'], json_decode((string) $b['wertung'], true) ?: [], $seite),
                    'am' => $b['beendet_am'],
                ];
            }
        }
        foreach ($this->db->alle(
            "SELECT a.start_id, a.gesamt, a.kata, a.beendet_am, p.bezeichnung AS phase FROM auftritt a JOIN phase p ON p.id = a.phase_id
              WHERE a.status = 'beendet' AND a.start_id IN ($in) ORDER BY a.beendet_am, a.id",
        ) as $a) {
            $kaempfe[] = $nachId[(int) $a['start_id']] + [
                'runde' => $a['phase'], 'seite' => 'starter', 'gegner' => null, 'sieg' => null,
                'ergebnis' => $a['kata'] === 'Kiken' ? 'Kiken' : str_replace('.', ',', sprintf('%.1f', (float) $a['gesamt'])) . ' Punkte' . ($a['kata'] ? ' · ' . $a['kata'] : ''),
                'am' => $a['beendet_am'],
            ];
        }
        // nach Klasse, dann zeitlich (Vorrunde vor Finale)
        usort($kaempfe, static fn($x, $y) => strnatcmp((string) $x['code'], (string) $y['code']) ?: strcmp((string) $x['am'], (string) $y['am']));
        return ['platzierungen' => $platzierungen, 'medaillen' => $medaillen, 'kaempfe' => $kaempfe];
    }

    /**
     * Kurzer Ergebnistext aus Sicht einer Seite.
     *
     * @param array<string, mixed> $w
     */
    private static function ergebnisText(string $entscheidung, array $w, string $seite): string
    {
        $gegner = $seite === 'aka' ? 'shiro' : 'aka';
        return match (true) {
            in_array($entscheidung, ['kiken', 'hansoku', 'shikkaku'], true) => ucfirst($entscheidung) . (($w['gegen'] ?? '') === $seite ? ' gegen uns' : ' des Gegners'),
            isset($w['summe']) => sprintf('%d : %d Einzelsiege', $w['summe'][$seite]['siege'] ?? 0, $w['summe'][$gegner]['siege'] ?? 0),
            isset($w[$seite]['ippon']) => self::kumiteText($w[$seite]) . ' : ' . self::kumiteText($w[$gegner] ?? [])
                . (!empty($w['saishiai']) ? ' (Saishiai)' : '')
                . (isset($w['flaggen']) ? sprintf(' · Hantei %d : %d', $w['flaggen'][$seite] ?? 0, $w['flaggen'][$gegner] ?? 0) : ''),
            isset($w[$seite]['werte']) => str_replace('.', ',', sprintf('%.1f : %.1f Punkte', $w[$seite]['gesamt'] ?? 0, $w[$gegner]['gesamt'] ?? 0)),
            isset($w['flaggen']) => sprintf('%d : %d Flaggen', $w['flaggen'][$seite] ?? 0, $w['flaggen'][$gegner] ?? 0),
            default => $entscheidung,
        };
    }

    /**
     * Wertung einer Kumite-Seite in Worten: „1 Ippon, 1 Wazaari“ bzw. „0“.
     *
     * @param array<string, mixed> $s
     */
    private static function kumiteText(array $s): string
    {
        $teile = [];
        if ((int) ($s['ippon'] ?? 0) > 0) {
            $teile[] = (int) $s['ippon'] . ' Ippon';
        }
        if ((int) ($s['wazaari'] ?? 0) > 0) {
            $teile[] = (int) $s['wazaari'] . ' Wazaari';
        }
        return $teile === [] ? '0' : implode(', ', $teile);
    }

    // --- Korrekturanträge ------------------------------------------------------------

    /**
     * Teilnehmer des Dojos (für Anträge): nur Einzelstarter des eigenen Dojos.
     *
     * @return list<array{id: int, name: string}>
     */
    public function teilnehmer(int $turnierId, int $vereinId): array
    {
        return array_map(static fn(array $z) => ['id' => (int) $z['id'], 'name' => trim($z['nachname'] . ', ' . $z['vorname'])], $this->db->alle(
            'SELECT ta.id, a.vorname, a.nachname FROM teilnahme ta JOIN athlet a ON a.id = ta.athlet_id
              WHERE ta.turnier_id = ? AND a.verein_id = ? ORDER BY a.nachname, a.vorname',
            [$turnierId, $vereinId],
        ));
    }

    /** @param array<string, mixed> $daten teilnahme_id, art, neu, begruendung */
    public function antragStellen(int $turnierId, int $vereinId, array $daten, string $von): int
    {
        $art = (string) ($daten['art'] ?? '');
        $neu = trim((string) ($daten['neu'] ?? ''));
        $begruendung = trim((string) ($daten['begruendung'] ?? ''));
        $teilnahme = (int) ($daten['teilnahme_id'] ?? 0);
        if (!isset(self::ANTRAG_ARTEN[$art])) {
            throw new FachFehler('Bitte die Art der Korrektur wählen.');
        }
        if ($neu === '' || mb_strlen($neu) > 200) {
            throw new FachFehler('Bitte die gewünschte Korrektur angeben (höchstens 200 Zeichen).');
        }
        if ($begruendung === '' || mb_strlen($begruendung) > 500) {
            throw new FachFehler('Bitte eine Begründung angeben (höchstens 500 Zeichen).');
        }
        // nur eigene Teilnehmer dieses Turniers (BOLA)
        if ($teilnahme !== 0 && !in_array($teilnahme, array_column($this->teilnehmer($turnierId, $vereinId), 'id'), true)) {
            throw new NichtGefunden('Teilnehmer nicht gefunden.');
        }
        if ($teilnahme === 0 && $art !== 'sonstiges') {
            throw new FachFehler('Bitte den Teilnehmer wählen.');
        }
        // höchstens MAX_ANTRAEGE_JE_STUNDE je Dojo (OWASP A06); gezählt wird nur, was gespeichert wurde
        $drossel = new Drossel($this->db);
        $absender = 'korrekturantrag:verein:' . $vereinId;
        $drossel->pruefen($absender, self::MAX_ANTRAEGE_JE_STUNDE, 3600);
        $id = $this->db->insert('korrekturantrag', [
            'turnier_id' => $turnierId, 'verein_id' => $vereinId, 'teilnahme_id' => $teilnahme ?: null,
            'art' => $art, 'neu' => $neu, 'begruendung' => $begruendung, 'status' => 'offen',
            'erstellt_am' => date('Y-m-d H:i:s'), 'erstellt_von' => mb_substr($von, 0, 100),
        ]);
        $drossel->zaehlen($absender, 3600);
        (new Protokoll($this->db))->eintragen($turnierId, 'korrekturantrag', $id, 'gestellt', [
            'verein_id' => $vereinId, 'teilnahme_id' => $teilnahme ?: null, 'art' => $art,
        ]);
        return $id;
    }

    /**
     * Anträge eines Turniers – für die Leitung alle, für ein Dojo nur die eigenen.
     *
     * @return list<array<string, mixed>>
     */
    public function antraege(int $turnierId, ?int $vereinId = null): array
    {
        return $this->db->alle(
            'SELECT k.*, v.name AS verein, a.vorname, a.nachname, ta.id AS teilnahme
               FROM korrekturantrag k JOIN verein v ON v.id = k.verein_id
               LEFT JOIN teilnahme ta ON ta.id = k.teilnahme_id LEFT JOIN athlet a ON a.id = ta.athlet_id
              WHERE k.turnier_id = ?' . ($vereinId === null ? '' : ' AND k.verein_id = ?') . "
              ORDER BY CASE k.status WHEN 'offen' THEN 0 ELSE 1 END, k.erstellt_am DESC, k.id DESC",
            $vereinId === null ? [$turnierId] : [$turnierId, $vereinId],
        );
    }

    public function offeneAntraege(int $turnierId): int
    {
        return (int) $this->db->wert("SELECT COUNT(*) FROM korrekturantrag WHERE turnier_id = ? AND status = 'offen'", [$turnierId]);
    }

    /** Leitung: Antrag annehmen bzw. ablehnen, mit Antwort an das Dojo. */
    public function antragBearbeiten(int $turnierId, int $antragId, string $status, string $antwort, string $von): void
    {
        if ($this->db->wert('SELECT id FROM korrekturantrag WHERE id = ? AND turnier_id = ?', [$antragId, $turnierId]) === null) {
            throw new NichtGefunden('Antrag nicht gefunden.');
        }
        if (!in_array($status, ['angenommen', 'abgelehnt', 'offen'], true)) {
            throw new FachFehler('Ungültiger Status.');
        }
        $this->db->update('korrekturantrag', [
            'status' => $status,
            'antwort' => mb_substr(trim($antwort), 0, 500) ?: null,
            'bearbeitet_am' => $status === 'offen' ? null : date('Y-m-d H:i:s'),
            'bearbeitet_von' => $status === 'offen' ? null : mb_substr($von, 0, 100),
        ], ['id' => $antragId]);
        (new Protokoll($this->db))->eintragen($turnierId, 'korrekturantrag', $antragId, 'bearbeitet', ['status' => $status]);
    }

    // --- Startgeld ---------------------------------------------------------------------

    /**
     * Startgeld des Dojos aus den gemeldeten Teilnehmern (je Kata- bzw. Kumite-Einzelstart) und Teams.
     *
     * @return array{posten: list<array{text: string, anzahl: int, einzel: int, summe: int}>, summe: int, teilnehmer: list<array<string, mixed>>, teams: list<array<string, mixed>>}
     */
    public function startgeld(int $turnierId, int $vereinId): array
    {
        $turnier = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$turnierId]) ?? [];
        /** @var array<string, array{name: string, kata: bool, kumite: string}> $teilnehmer */
        $teilnehmer = [];
        foreach ($this->db->alle(
            "SELECT a.vorname, a.nachname, ta.disziplinen FROM teilnahme ta JOIN athlet a ON a.id = ta.athlet_id
              WHERE ta.turnier_id = ? AND a.verein_id = ? AND ta.status NOT IN ('abgemeldet', 'nicht_erschienen') ORDER BY a.nachname, a.vorname",
            [$turnierId, $vereinId],
        ) as $z) {
            $d = array_filter(array_map('trim', explode(',', (string) $z['disziplinen'])));
            $kumite = array_values(array_filter($d, static fn($x) => str_starts_with($x, 'kumite')));
            $teilnehmer['t' . count($teilnehmer)] = ['name' => trim($z['nachname'] . ', ' . $z['vorname']), 'kata' => in_array('kata', $d, true), 'kumite' => $kumite[0] ?? ''];
        }
        $teams = $this->db->alle("SELECT name, disziplin FROM team WHERE turnier_id = ? AND verein_id = ? ORDER BY disziplin, name", [$turnierId, $vereinId]);
        $anmeldung = new AnmeldungService($this->db);
        $preis = $anmeldung->preis($anmeldung->einstellungen($turnier), ['teilnehmer' => $teilnehmer, 'teams' => $teams]);
        return $preis + ['teilnehmer' => array_values($teilnehmer), 'teams' => $teams];
    }
}
