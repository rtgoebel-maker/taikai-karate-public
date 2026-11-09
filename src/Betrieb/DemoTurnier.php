<?php

declare(strict_types=1);

namespace Wettkampf\Betrieb;

use DateTimeImmutable;
use Wettkampf\Domain\Auslosung\Zufall;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Domain\Graduierung;
use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Turnier\TurnierPhaseService;
use Wettkampf\Turnier\StammdatenService;
use Wettkampf\Meldung\AnmeldungService;
use Wettkampf\Meldung\TeamService;
use Wettkampf\Meldung\ZuordnungService;
use Wettkampf\Durchfuehrung\KlassenService;
use Wettkampf\Durchfuehrung\AuslosungService;
use Wettkampf\Durchfuehrung\ErgebnisService;
use Wettkampf\Durchfuehrung\TatamiAnsicht;

/**
 * Demo-Turnier zum Ausprobieren: ausgelost und verteilt – noch nicht gestartet, laufend
 * (ein Teil der Ergebnisse eingetragen) oder abgeschlossen (alles gespielt und bestätigt).
 * Klassen mit gezielt gewählten Teilnehmerzahlen: Kata (Punkte) mit einem Block bis 8 Startern
 * und Pools zu höchstens 8 (bis Pool E), Kumite-Pools gleich groß und möglichst gerade.
 * Optional Kata- und Kumite-Teams (ohne Pools: Kata bis 8 Teams ein Block, Kumite K.-o.).
 * Erfundene Namen und Vereine; PINs Tatami 1111–4444, Leitung 9999.
 */
final class DemoTurnier
{
    public const PINS = 'Tatami 1111–4444, Leitung 9999';

    /** Wählbare Phasen des Demo-Turniers */
    public const PHASEN = [
        TurnierPhase::VORBEREITUNG => 'in Vorbereitung (ausgelost, noch nicht gestartet)',
        TurnierPhase::LAEUFT => 'läuft (etwa 40 % der Ergebnisse eingetragen)',
        TurnierPhase::ABGESCHLOSSEN => 'abgeschlossen (alle Ergebnisse eingetragen und bestätigt)',
    ];

    /** Fortschritt eines laufenden Demo-Turniers (Anteil eingetragener Kämpfe/Vorträge) */
    private const ANTEIL_LAUFEND = 0.4;

    private const KATAS = ['Heian Shodan', 'Heian Nidan', 'Heian Sandan', 'Heian Yondan', 'Heian Godan', 'Tekki Shodan', 'Bassai Dai', 'Kanku Dai', 'Jion', 'Enpi'];

    private const VORNAMEN = [
        'w' => ['Anna', 'Lena', 'Mia', 'Emma', 'Lea', 'Sophie', 'Hannah', 'Marie', 'Clara', 'Ida', 'Frieda', 'Jana', 'Nele', 'Paula', 'Romy', 'Lina', 'Greta', 'Mila'],
        'm' => ['Ben', 'Paul', 'Leon', 'Finn', 'Noah', 'Elias', 'Jonas', 'Luis', 'Felix', 'Max', 'Emil', 'Anton', 'Theo', 'Jakob', 'Moritz', 'Karl', 'Oskar', 'Lukas'],
    ];
    private const NACHNAMEN = ['Berger', 'Huber', 'Wagner', 'Bauer', 'Lehner', 'Gruber', 'Brandl', 'Moser', 'Hofer', 'Kerner', 'Lindner', 'Seidl', 'Winkler', 'Raab', 'Stadler', 'Ostler', 'Kain', 'Pichler', 'Fuchs', 'Maier'];
    private const VEREINE = ['Karate Dojo Bergheim', 'TSV Seefeld Karate', 'Budokan Isartal', 'SV Waldkirch', 'Shotokan Club Moosach', 'JKA Dojo Lindenau', 'Karate-Club Ebersberg', 'Kampfsport Rosenau'];

    public function __construct(private readonly Database $db) {}

    /**
     * @param string $datum JJJJ-MM-TT
     * @param int|null $logosVon Logos/Urkunde aus diesem Turnier übernehmen
     * @param string $phase Vorbereitung, läuft oder abgeschlossen (siehe PHASEN)
     * @return array{turnier: int, name: string, teilnehmer: int, teams: int, ausgelost: int, ergebnisse: int,
     *               klassen: list<array{code: string, bezeichnung: string, starter: int, pools: list<string>}>}
     */
    public function anlegen(string $datum, ?string $name = null, bool $mitTeams = false, ?int $logosVon = null, string $phase = TurnierPhase::VORBEREITUNG): array
    {
        if (!isset(self::PHASEN[$phase])) {
            throw new FachFehler('Unbekannte Turnierphase.');
        }
        $tag = DateTimeImmutable::createFromFormat('!Y-m-d', $datum);
        if ($tag === false || $tag->format('Y-m-d') !== $datum) {
            throw new FachFehler('Bitte ein gültiges Datum angeben.');
        }
        $name = trim((string) $name);
        if (mb_strlen($name) > 200) {
            throw new FachFehler('Der Name ist zu lang.');
        }
        if ($logosVon !== null && $this->db->wert('SELECT id FROM turnier WHERE id = ?', [$logosVon]) === null) {
            throw new FachFehler('Turnier für die Logos nicht gefunden.');
        }
        return $this->db->transaktion(function () use ($tag, $name, $mitTeams, $logosVon, $phase) {
            $demo = $this->erzeugen($tag, $name !== '' ? $name : null, $mitTeams, $logosVon);
            $demo['ergebnisse'] = $phase === TurnierPhase::VORBEREITUNG ? 0 : $this->spielen($demo['turnier'], $phase);
            $demo['klassen'] = $this->uebersicht($demo['turnier']);
            return $demo;
        });
    }

    /**
     * Startet das Turnier und trägt Ergebnisse ein – Tatami für Tatami wie an den Tischen, die
     * dynamische Verteilung zieht dabei die nächsten Gruppen nach. Abgeschlossen: alles spielen,
     * Gleichstände per Stichentscheid lösen, Gruppen abschließen und bestätigen.
     */
    private function spielen(int $turnier, string $phase): int
    {
        $phasen = new TurnierPhaseService($this->db);
        $phasen->setzen($turnier, TurnierPhase::LAEUFT, true);
        $ergebnis = new ErgebnisService($this->db);
        $ansicht = new TatamiAnsicht($this->db);
        $z = new Zufall('demolauf' . $turnier);
        $ziel = $phase === TurnierPhase::ABGESCHLOSSEN ? 1.0 : self::ANTEIL_LAUFEND;
        $tatamis = array_map('intval', $this->db->spalte('SELECT id FROM tatami WHERE turnier_id = ? ORDER BY nummer', [$turnier]));
        $eingetragen = 0;
        do {
            $fortschritt = false;
            foreach ($tatamis as $tatami) {
                if ($this->fortschritt($turnier) >= $ziel) {
                    break 2;
                }
                $zustand = $ansicht->fuerTatami($tatami, 1);
                $e = $zustand['laufend'] ?? ($zustand['naechste'][0] ?? null);
                if ($e === null) {
                    continue;
                }
                try {
                    if ($e['typ'] === 'auftritt') {
                        $ergebnis->auftrittEintragen($turnier, (int) $e['id'], $this->eingabe($e, $z), false);
                    } else {
                        $ergebnis->begegnungEintragen($turnier, (int) $e['id'], $this->eingabe($e, $z), false);
                    }
                    $eingetragen++;
                    $fortschritt = true;
                } catch (FachFehler) {
                    // z. B. Gleichstand, der erst per Stichentscheid weitergeht – unten gelöst
                }
            }
            if (!$fortschritt) {
                $fortschritt = $this->gleichstaendeLoesen($turnier, $ergebnis);
            }
        } while ($fortschritt);
        // Gleichstand im zuletzt gespielten Finale (Stechen)
        $this->gleichstaendeLoesen($turnier, $ergebnis);

        // fertige Gruppen hat die Tischbesatzung abgeschlossen, die Leitung bestätigt
        $this->db->query(
            "UPDATE klasse SET tisch_abschluss_am = ?, tisch_abschluss_von = 'Demo' WHERE turnier_id = ? AND status = 'beendet' AND tisch_abschluss_am IS NULL",
            [date('Y-m-d H:i:s'), $turnier],
        );
        foreach ($this->db->spalte("SELECT id FROM klasse WHERE turnier_id = ? AND status = 'beendet' ORDER BY sortierung", [$turnier]) as $k) {
            $ergebnis->bestaetigen($turnier, (int) $k);
        }
        if ($phase === TurnierPhase::ABGESCHLOSSEN) {
            $phasen->setzen($turnier, TurnierPhase::ABGESCHLOSSEN, true);
        }
        return $eingetragen;
    }

    /** Anteil eingetragener Kämpfe und Vorträge (wartende Finalrunden zählen erst, wenn erzeugt). */
    private function fortschritt(int $turnier): float
    {
        $z = $this->db->eine(
            "SELECT (SELECT COUNT(*) FROM begegnung b JOIN phase p ON p.id = b.phase_id JOIN klasse k ON k.id = p.klasse_id WHERE k.turnier_id = ? AND b.status <> 'freilos') AS b,
                    (SELECT COUNT(*) FROM begegnung b JOIN phase p ON p.id = b.phase_id JOIN klasse k ON k.id = p.klasse_id WHERE k.turnier_id = ? AND b.status = 'beendet') AS bb,
                    (SELECT COUNT(*) FROM auftritt a JOIN phase p ON p.id = a.phase_id JOIN klasse k ON k.id = p.klasse_id WHERE k.turnier_id = ?) AS a,
                    (SELECT COUNT(*) FROM auftritt a JOIN phase p ON p.id = a.phase_id JOIN klasse k ON k.id = p.klasse_id WHERE k.turnier_id = ? AND a.status = 'beendet') AS ab",
            [$turnier, $turnier, $turnier, $turnier],
        );
        $gesamt = (int) $z['b'] + (int) $z['a'];
        return $gesamt === 0 ? 1.0 : ((int) $z['bb'] + (int) $z['ab']) / $gesamt;
    }

    /** Klassen ohne offene Phase, aber nicht beendet: Gleichstand → Stichentscheid in Startreihenfolge. */
    private function gleichstaendeLoesen(int $turnier, ErgebnisService $ergebnis): bool
    {
        $geloest = false;
        foreach ($this->db->spalte(
            "SELECT k.id FROM klasse k WHERE k.turnier_id = ? AND k.status = 'laeuft'
                AND NOT EXISTS (SELECT 1 FROM phase p WHERE p.klasse_id = k.id AND p.status <> 'beendet')",
            [$turnier],
        ) as $k) {
            $starts = array_map('intval', $this->db->spalte('SELECT id FROM start WHERE klasse_id = ? ORDER BY id', [$k]));
            $ergebnis->stichentscheid($turnier, (int) $k, 'start', array_combine($starts, range(1, count($starts))));
            $geloest = true;
        }
        return $geloest;
    }

    /**
     * Zufällige, gültige Eingabe wie vom Tablet.
     *
     * @param array<string, mixed> $e Eintrag aus TatamiAnsicht
     * @return array<string, mixed>
     */
    private function eingabe(array $e, Zufall $z): array
    {
        $kr = (int) $e['kampfrichter'];
        $wert = static fn() => number_format(6.5 + $z->zahl(16) / 10, 1, '.', '');
        $kata = static fn() => $e['kata_liste'] ? $e['kata_liste'][$z->zahl(count($e['kata_liste']))] : self::KATAS[$z->zahl(count(self::KATAS))];
        if ($e['typ'] === 'auftritt') {
            return ['werte' => array_map(static fn() => $wert(), range(1, $kr)), 'kata' => $kata()];
        }
        $daten = str_starts_with((string) $e['disziplin'], 'kata') ? ['kata_aka' => $kata(), 'kata_shiro' => $kata()] : [];
        $sieger = $z->zahl(2) === 0 ? 'aka' : 'shiro';
        $verlierer = $sieger === 'aka' ? 'shiro' : 'aka';
        if ($e['disziplin'] === 'kumite_team' && $e['system'] === 'kumite') {
            $kaempfe = [];
            foreach ([$sieger, $sieger, $z->zahl(2) === 0 ? $sieger : $verlierer] as $s) {
                $kaempfe[] = ['sieger' => $s, "{$s}_ippon" => 1];
            }
            return $daten + ['kaempfe' => $kaempfe];
        }
        if ($e['system'] === 'flaggen') {
            $mehrheit = intdiv($kr, 2) + 1 + $z->zahl(intdiv($kr, 2) + 1);
            return $daten + ($sieger === 'aka' ? ['flaggen_aka' => $mehrheit, 'flaggen_shiro' => $kr - $mehrheit] : ['flaggen_aka' => $kr - $mehrheit, 'flaggen_shiro' => $mehrheit]);
        }
        if ($e['system'] === 'punkte') {
            $a = array_map(static fn() => $wert(), range(1, $kr));
            $s = array_map(static fn() => $wert(), range(1, $kr));
            $mehr = static fn(array $x) => array_map(static fn($v) => number_format(min(10, (float) $v + 0.3), 1, '.', ''), $x);
            return $daten + ($sieger === 'aka' ? ['aka_werte' => $mehr($a), 'shiro_werte' => $s] : ['aka_werte' => $a, 'shiro_werte' => $mehr($s)]);
        }
        // Kumite: Ippon, zwei Wazaari oder Sieg nach Zeit
        return $daten + match ($z->zahl(3)) {
            0 => [$sieger . '_ippon' => 1],
            1 => [$sieger . '_wazaari' => 2, $verlierer . '_wazaari' => $z->zahl(2)],
            default => [$sieger . '_wazaari' => 1, 'zeit_abgelaufen' => 1],
        };
    }

    /** @return array<string, mixed> */
    private function erzeugen(DateTimeImmutable $tag, ?string $name, bool $mitTeams, ?int $logosVon): array
    {
        $db = $this->db;
        $datum = $tag->format('Y-m-d');
        $z = new Zufall('demopools' . $datum);
        $stamm = new StammdatenService($db);
        $turnier = $stamm->turnierAnlegen([
            'name' => $name ?? ($mitTeams ? 'Pool- und Team-Demo ' : 'Pool-Demo ') . substr($datum, 0, 4),
            'datum' => $datum,
            'ort' => 'Musterstadt',
            'halle' => 'Sporthalle Süd',
            'anzahl_tatamis' => 4,
            'min_graduierung' => Graduierung::kyu(7),
            'oeffentlich' => 1,
        ]);

        $teamService = new TeamService($db);
        $klassenService = new KlassenService($db);
        $k3 = Graduierung::kyu(3);
        $sortierung = 0;
        $teilnehmer = 0;
        $teams = 0;
        $person = static function (string $g, int $alter, int $grad, string $verein, array $disziplinen) use ($stamm, $turnier, $z, $tag): int {
            return $stamm->teilnehmerSpeichern($turnier, [
                'vorname' => self::VORNAMEN[$g][$z->zahl(count(self::VORNAMEN[$g]))],
                'nachname' => self::NACHNAMEN[$z->zahl(count(self::NACHNAMEN))],
                'geschlecht' => $g,
                'geburtsdatum' => $tag->modify("-$alter years")->modify('-' . (1 + $z->zahl(300)) . ' days')->format('Y-m-d'),
                'graduierung' => $grad,
                'verein_name' => $verein,
                'disziplinen' => $disziplinen,
            ]);
        };
        foreach ($this->klassen($mitTeams) as [$code, $bezeichnung, $g, $von, $bis, $gradMin, $gradMax, $optionen, $anzahl, $teamGroesse]) {
            $sortierung += 10;
            $klassenService->klasseSpeichern($turnier, $optionen + [
                'code' => $code, 'bezeichnung' => $bezeichnung, 'geschlecht' => $g,
                'alter_min' => $von, 'alter_max' => $bis, 'grad_min' => $gradMin, 'grad_max' => $gradMax,
                'dojo_trennung' => 1, 'min_starter' => 2, 'sortierung' => $sortierung,
            ]);
            $alterZufall = static fn() => $bis === null ? $von + $z->zahl(12) : $von + $z->zahl($bis - $von + 1);
            $gradZufall = static fn() => $gradMin === $k3 ? Graduierung::kyu(3 - $z->zahl(3)) : Graduierung::kyu(7 - $z->zahl(4));
            for ($i = 0; $i < $anzahl; $i++) {
                if (!$teamGroesse) {
                    $person($g, $alterZufall(), $gradZufall(), self::VEREINE[$z->zahl(count(self::VEREINE))], [$optionen['disziplin']]);
                    $teilnehmer++;
                    continue;
                }
                // Team aus Mitgliedern desselben Vereins (nur Teamstart, keine Einzelklasse)
                $verein = self::VEREINE[$i % count(self::VEREINE)];
                $nummer = intdiv($i, count(self::VEREINE)) + 1;
                $mitglieder = [];
                for ($j = 0; $j < $teamGroesse; $j++) {
                    $mitglieder[] = $person($g === 'mixed' ? ($z->zahl(2) === 0 ? 'w' : 'm') : $g, $alterZufall(), $gradZufall(), $verein, []);
                    $teilnehmer++;
                }
                $teamId = $teamService->teamSpeichern($turnier, "$verein $nummer", $optionen['disziplin'], (int) $db->wert('SELECT id FROM verein WHERE name = ?', [$verein]));
                foreach ($mitglieder as $mitglied) {
                    $teamService->mitgliedHinzufuegen($turnier, $teamId, $mitglied);
                }
                $teams++;
            }
        }
        (new ZuordnungService($db))->alleZuordnen($turnier);

        // Startgeld, Logos/Urkunde (optional aus einem anderen Turnier), PINs
        (new AnmeldungService($db))->einstellungenSpeichern($turnier, [
            'preis_kata' => '12', 'preis_kumite' => '12', 'preis_kata_team' => '24', 'preis_kumite_team' => '24',
            'hinweis' => 'Startgebühr bitte bis zum Meldeschluss überweisen.',
        ]);
        if ($logosVon !== null) {
            foreach ($db->alle('SELECT art, mime, daten, breite, hoehe FROM turnier_logo WHERE turnier_id = ?', [$logosVon]) as $l) {
                $db->insert('turnier_logo', $l + ['turnier_id' => $turnier, 'geaendert_am' => date('Y-m-d H:i:s')]);
            }
        }
        $db->update('turnier', ['meldeschluss' => $tag->modify('-72 hours')->format('Y-m-d'), 'leitung_pin_hash' => password_hash('9999', PASSWORD_DEFAULT)], ['id' => $turnier]);
        foreach ($db->alle('SELECT id, nummer FROM tatami WHERE turnier_id = ?', [$turnier]) as $t) {
            $db->update('tatami', ['pin_hash' => password_hash(str_repeat((string) $t['nummer'], 4), PASSWORD_DEFAULT)], ['id' => $t['id']]);
        }

        // Auslosen; die dynamische Verteilung gibt jeder Tatami ihre erste Gruppe, der Rest wartet
        $ausgelost = (new AuslosungService($db))->alleAuslosen($turnier);

        return [
            'turnier' => $turnier,
            'name' => (string) $db->wert('SELECT name FROM turnier WHERE id = ?', [$turnier]),
            'teilnehmer' => $teilnehmer,
            'teams' => $teams,
            'ausgelost' => $ausgelost,
        ];
    }

    /**
     * Klassen: Kürzel, Bezeichnung, Geschlecht, Alter von/bis, Graduierung von/bis, Modus-Optionen,
     * Starter (bzw. Teams), Teamgröße (0 = Einzel).
     *
     * @return list<array{string, string, string, int, int|null, int, int|null, array<string, mixed>, int, int}>
     */
    private function klassen(bool $mitTeams): array
    {
        $k7 = Graduierung::kyu(7);
        $k3 = Graduierung::kyu(3);
        $kata = ['disziplin' => 'kata', 'modus' => 'punkte', 'wertung' => 'punkte', 'wertung_finale' => 'punkte', 'finalisten' => 4, 'kampfrichter' => 5, 'gleiche_flaeche' => 1];
        $jiyu = ['disziplin' => 'kumite', 'kumite_form' => 'jiyu', 'modus' => 'pools', 'wertung' => 'kumite', 'wertung_finale' => 'kumite', 'finale_art' => 'ko', 'kampfrichter' => 5];
        $klassen = [
            ['A1', 'Kata Mädchen 8–9 Jahre', 'w', 8, 9, $k7, null, $kata, 6, 0],
            ['A2', 'Kata Jungen 8–9 Jahre', 'm', 8, 9, $k7, null, $kata, 9, 0],
            ['B1', 'Kata Mädchen 10–11 Jahre', 'w', 10, 11, $k7, null, $kata, 14, 0],
            ['B2', 'Kata Jungen 10–11 Jahre', 'm', 10, 11, $k7, null, $kata, 21, 0],
            ['C1', 'Kata Mädchen 12–14 Jahre', 'w', 12, 14, $k7, null, $kata, 38, 0],
            ['C2', 'Kata Jungen 12–14 Jahre', 'm', 12, 14, $k7, null, $kata, 8, 0],
            ['K1', 'Jiyu-Kumite Jungen 12–14 Jahre', 'm', 12, 14, $k3, null, $jiyu + ['anzahl_pools' => 4, 'aufsteiger_pro_pool' => 1], 12, 0],
            ['K2', 'Jiyu-Kumite Mädchen 12–14 Jahre', 'w', 12, 14, $k3, null, $jiyu + ['anzahl_pools' => 2, 'aufsteiger_pro_pool' => 2], 16, 0],
            ['K3', 'Jiyu-Kumite Männer ab 18 Jahre', 'm', 18, null, $k3, null, $jiyu + ['anzahl_pools' => 3, 'aufsteiger_pro_pool' => 1], 18, 0],
            ['K4', 'Jiyu-Kumite Frauen ab 18 Jahre', 'w', 18, null, $k3, null, $jiyu + ['anzahl_pools' => 2, 'aufsteiger_pro_pool' => 2], 10, 0],
            ['K5', 'Kihon-Ippon-Kumite Jungen 8–11 Jahre', 'm', 8, 11, $k7, null,
                ['disziplin' => 'kumite', 'kumite_form' => 'kihon_ippon', 'modus' => 'auto', 'wertung' => 'flaggen', 'kampfrichter' => 5], 7, 0],
        ];
        if ($mitTeams) {
            $kataTeam = ['disziplin' => 'kata_team'] + $kata;
            $kumiteTeam = ['disziplin' => 'kumite_team', 'kumite_form' => 'jiyu', 'modus' => 'auto', 'wertung' => 'kumite', 'kampfrichter' => 5];
            array_push(
                $klassen,
                ['T1', 'Kata-Team gemischt 10–14 Jahre', 'mixed', 10, 14, $k7, null, $kataTeam, 7, 3],
                ['T2', 'Kata-Team gemischt ab 15 Jahre', 'mixed', 15, null, $k7, null, $kataTeam, 4, 3],
                ['T3', 'Kumite-Team Männer ab 18 Jahre', 'm', 18, null, $k3, null, $kumiteTeam, 6, 3],
                ['T4', 'Kumite-Team Frauen ab 18 Jahre', 'w', 18, null, $k3, null, $kumiteTeam, 4, 3],
            );
        }
        return $klassen;
    }

    /** @return list<array{code: string, bezeichnung: string, starter: int, pools: list<string>}> */
    private function uebersicht(int $turnier): array
    {
        $klassen = [];
        foreach ($this->db->alle(
            'SELECT k.id, k.code, k.bezeichnung, (SELECT COUNT(*) FROM start s WHERE s.klasse_id = k.id) AS starter FROM klasse k WHERE k.turnier_id = ? ORDER BY k.sortierung',
            [$turnier],
        ) as $k) {
            $pools = [];
            foreach ($this->db->alle(
                "SELECT p.bezeichnung, p.starter, t.bezeichnung AS tatami FROM phase p LEFT JOIN tatami t ON t.id = p.tatami_id
                  WHERE p.klasse_id = ? AND p.typ IN ('pool', 'punkte_vorrunde') ORDER BY p.nummer",
                [$k['id']],
            ) as $p) {
                $pools[] = $p['bezeichnung'] . ' ' . count(json_decode((string) $p['starter'], true) ?: []) . ($p['tatami'] ? ' (' . $p['tatami'] . ')' : '');
            }
            $klassen[] = ['code' => $k['code'], 'bezeichnung' => $k['bezeichnung'], 'starter' => (int) $k['starter'], 'pools' => $pools];
        }
        return $klassen;
    }
}
