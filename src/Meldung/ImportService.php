<?php

declare(strict_types=1);

namespace Wettkampf\Meldung;

use Wettkampf\Domain\Alter;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Domain\Graduierung;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Turnier\StammdatenService;
use Wettkampf\Durchfuehrung\KlassenService;

/**
 * Teilnehmer-Import aus CSV/XLSX in zwei Schritten: analysieren (Vorschau
 * mit Fehlern) und übernehmen. Spalten werden über ihre Überschrift erkannt.
 */
final class ImportService
{
    /** Spalte => erkannte Überschriften (normalisiert: klein, ohne Leer-/Sonderzeichen) */
    private const SPALTEN = [
        'nachname' => ['name', 'nachname', 'familienname', 'zuname'],
        'vorname' => ['vorname', 'rufname'],
        'geschlecht' => ['geschlecht', 'gender', 'mw', 'sex'],
        'geburtsdatum' => ['geburtsdatum', 'geburtstag', 'gebdatum', 'geb', 'geboren', 'jahrgangdatum'],
        'alter' => ['alter', 'age'],
        'dojo' => ['dojo', 'verein', 'club', 'dojoverein', 'vereindojo'],
        'graduierung' => ['graduierung', 'grad', 'kyu', 'kyudan', 'gurt'],
        'kata' => ['kata', 'kataeinzel', 'einzelkata'],
        'kumite' => ['kumite', 'kumiteeinzel', 'einzelkumite'],
        'kata_team' => ['katateam', 'teamkata', 'katamannschaft', 'mannschaftkata'],
        'kumite_team' => ['kumiteteam', 'teamkumite', 'kumitemannschaft', 'mannschaftkumite'],
        'klasse' => ['klasse', 'gruppe', 'startklasse', 'kategorie'],
    ];

    public const VORLAGE_KOPF = ['Name', 'Vorname', 'Geschlecht', 'Geburtsdatum', 'Alter', 'Dojo', 'Graduierung', 'Kata Einzel', 'Kumite Einzel', 'Kata Team', 'Kumite Team', 'Klasse'];

    public function __construct(private readonly Database $db) {}

    /** CSV-Vorlage (UTF-8 mit BOM, Semikolon) – öffnet sich direkt in Excel. */
    public static function vorlageCsv(): string
    {
        $zeilen = [
            self::VORLAGE_KOPF,
            ['Mustermann', 'Max', 'm', '03.05.2012', '', 'Karate Dojo Musterstadt', '5. Kyu', 'x', 'x', 'Musterstadt 1', '', ''],
            ['Musterfrau', 'Erika', 'w', '', '16', 'Karate Dojo Musterstadt', '2. Kyu', 'x', 'Jiyu', '', 'Musterstadt Damen', ''],
        ];
        return "\xEF\xBB\xBF" . implode("\r\n", array_map(static fn($z) => implode(';', $z), $zeilen)) . "\r\n";
    }

    /**
     * @param list<list<string>> $zeilen erste Zeile = Überschriften
     * @return array{spalten: array<string, int>, fehlend: list<string>, zeilen: list<array<string, mixed>>}
     */
    public function analysieren(int $turnierId, array $zeilen): array
    {
        if (count($zeilen) < 2) {
            throw new FachFehler('Die Datei enthält keine Datenzeilen.');
        }
        $kopf = array_shift($zeilen);
        $spalten = $this->spaltenErkennen($kopf);
        $fehlend = array_values(array_diff(['nachname', 'vorname', 'geschlecht', 'dojo', 'graduierung'], array_keys($spalten)));
        if (!isset($spalten['geburtsdatum']) && !isset($spalten['alter'])) {
            $fehlend[] = 'geburtsdatum/alter';
        }

        $datum = (string) $this->db->wert('SELECT datum FROM turnier WHERE id = ?', [$turnierId]);
        $vorhanden = [];
        foreach ($this->db->alle(
            'SELECT LOWER(a.vorname) AS v, LOWER(a.nachname) AS n, a.geburtsdatum AS g FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id WHERE t.turnier_id = ?',
            [$turnierId],
        ) as $p) {
            $vorhanden["{$p['v']}|{$p['n']}|{$p['g']}"] = true;
        }
        $klassen = array_flip(array_map('mb_strtolower', $this->db->spalte('SELECT code FROM klasse WHERE turnier_id = ?', [$turnierId])));

        $ergebnis = [];
        $gesehen = [];
        foreach ($zeilen as $nr => $zeile) {
            $wert = static fn(string $feld) => isset($spalten[$feld]) ? trim((string) ($zeile[$spalten[$feld]] ?? '')) : '';
            if (implode('', $zeile) === '') {
                continue;
            }
            $fehler = [];
            $hinweise = [];

            $geschlecht = self::geschlecht($wert('geschlecht'));
            if ($geschlecht === null) {
                $fehler[] = 'Geschlecht fehlt/unbekannt';
            }
            $grad = Graduierung::parse($wert('graduierung'));
            if ($grad === null) {
                $fehler[] = "Graduierung „{$wert('graduierung')}“ nicht erkannt";
            }
            $geburt = Alter::datumParsen($wert('geburtsdatum'));
            if ($wert('geburtsdatum') !== '' && $geburt === null) {
                $fehler[] = "Geburtsdatum „{$wert('geburtsdatum')}“ ungültig";
            }
            $alter = is_numeric($wert('alter')) ? (int) $wert('alter') : null;
            if ($geburt !== null && $geburt > $datum) {
                $fehler[] = 'Geburtsdatum liegt nach dem Turnier';
                $geburt = null;
            }
            if ($geburt === null && $alter === null) {
                $fehler[] = 'Geburtsdatum oder Alter fehlt';
            }
            if ($wert('nachname') === '' || $wert('vorname') === '') {
                $fehler[] = 'Name unvollständig';
            }
            if ($wert('dojo') === '') {
                $hinweise[] = 'kein Dojo angegeben';
            }

            $disziplinen = [];
            if (self::ja($wert('kata'))) {
                $disziplinen[] = 'kata';
            }
            if (($kumite = self::kumite($wert('kumite'))) !== null) {
                $disziplinen[] = $kumite;
            }
            $klasse = $wert('klasse');
            if ($klasse !== '' && !isset($klassen[mb_strtolower($klasse)])) {
                $hinweise[] = "Klasse „{$klasse}“ unbekannt – wird ignoriert";
                $klasse = '';
            }
            if ($disziplinen === [] && $wert('kata_team') === '' && $wert('kumite_team') === '' && $klasse === '') {
                $hinweise[] = 'keine Disziplin gemeldet';
            }

            $schluessel = mb_strtolower($wert('vorname')) . '|' . mb_strtolower($wert('nachname')) . '|' . $geburt;
            $dublette = isset($vorhanden[$schluessel]);
            if ($dublette) {
                $hinweise[] = 'bereits erfasst – wird übersprungen';
            }
            if (isset($gesehen[$schluessel])) {
                $hinweise[] = 'doppelt in der Datei – wird übersprungen';
                $dublette = true;
            }
            $gesehen[$schluessel] = true;

            $ergebnis[] = [
                'zeile' => $nr + 2,
                'nachname' => $wert('nachname'),
                'vorname' => $wert('vorname'),
                'geschlecht' => $geschlecht,
                'geburtsdatum' => $geburt,
                'alter' => $geburt !== null ? Alter::amStichtag($geburt, $datum) : $alter,
                'alter_manuell' => $geburt === null ? $alter : null,
                'dojo' => $wert('dojo'),
                'graduierung' => $grad,
                'disziplinen' => $disziplinen,
                'kata_team' => $wert('kata_team'),
                'kumite_team' => $wert('kumite_team'),
                'klasse' => $klasse,
                'fehler' => $fehler,
                'hinweise' => $hinweise,
                'dublette' => $dublette,
            ];
        }
        return ['spalten' => $spalten, 'fehlend' => $fehlend, 'zeilen' => $ergebnis];
    }

    /**
     * Übernimmt alle fehlerfreien, nicht doppelten Zeilen.
     *
     * @param list<array<string, mixed>> $zeilen aus analysieren()
     * @return array{angelegt: int, uebersprungen: int, teams: int, hinweise: list<string>}
     */
    public function uebernehmen(int $turnierId, array $zeilen): array
    {
        return $this->db->transaktion(function () use ($turnierId, $zeilen) {
            $stamm = new StammdatenService($this->db);
            $teams = new TeamService($this->db);
            $klassen = new KlassenService($this->db);
            $angelegt = 0;
            $uebersprungen = 0;
            $teamIds = [];

            foreach ($zeilen as $z) {
                if ($z['fehler'] !== [] || $z['dublette']) {
                    $uebersprungen++;
                    continue;
                }
                $vereinId = $stamm->vereinFinden((string) $z['dojo']);
                $teilnahmeId = $stamm->teilnehmerSpeichern($turnierId, [
                    'vorname' => $z['vorname'],
                    'nachname' => $z['nachname'],
                    'geschlecht' => $z['geschlecht'],
                    'geburtsdatum' => $z['geburtsdatum'],
                    'alter_manuell' => $z['alter_manuell'],
                    'graduierung' => $z['graduierung'],
                    'verein_id' => $vereinId,
                    'disziplinen' => $z['disziplinen'],
                ]);
                $angelegt++;

                foreach (['kata_team', 'kumite_team'] as $disziplin) {
                    if ($z[$disziplin] === '') {
                        continue;
                    }
                    $teamId = $teams->teamFinden($turnierId, (string) $z[$disziplin], $disziplin, $vereinId);
                    $teams->mitgliedHinzufuegen($turnierId, $teamId, $teilnahmeId);
                    $teamIds[$teamId] = true;
                }
                if ($z['klasse'] !== '') {
                    $klasseId = (int) $this->db->wert('SELECT id FROM klasse WHERE turnier_id = ? AND LOWER(code) = LOWER(?)', [$turnierId, $z['klasse']]);
                    $klassen->startSetzen($turnierId, $klasseId, $teilnahmeId, null);
                }
            }
            $zuordnung = (new ZuordnungService($this->db))->alleZuordnen($turnierId);
            (new Protokoll($this->db))->eintragen($turnierId, 'import', null, 'teilnehmer', ['angelegt' => $angelegt, 'uebersprungen' => $uebersprungen]);
            return ['angelegt' => $angelegt, 'uebersprungen' => $uebersprungen, 'teams' => count($teamIds), 'hinweise' => $zuordnung['hinweise']];
        });
    }

    /**
     * @param list<string> $kopf
     * @return array<string, int> Feld => Spaltenindex
     */
    private function spaltenErkennen(array $kopf): array
    {
        $spalten = [];
        foreach ($kopf as $index => $ueberschrift) {
            $norm = preg_replace('/[^a-z0-9äöüß]/u', '', mb_strtolower($ueberschrift)) ?? '';
            foreach (self::SPALTEN as $feld => $namen) {
                if (!isset($spalten[$feld]) && in_array($norm, $namen, true)) {
                    $spalten[$feld] = $index;
                    break;
                }
            }
        }
        return $spalten;
    }

    public static function geschlecht(string $text): ?string
    {
        $t = mb_strtolower(trim($text));
        return match (true) {
            in_array($t, ['m', 'männlich', 'maennlich', 'mann', 'junge', 'jungen', 'herr', 'male', 'j'], true) => 'm',
            in_array($t, ['w', 'weiblich', 'frau', 'mädchen', 'maedchen', 'dame', 'female', 'f'], true) => 'w',
            in_array($t, ['d', 'divers'], true) => 'd',
            default => null,
        };
    }

    public static function ja(string $text): bool
    {
        return in_array(mb_strtolower(trim($text)), ['x', 'ja', 'j', '1', 'yes', 'y', 'true', 'wahr', '✓', '✔'], true);
    }

    /** Kumite-Spalte: „x“ = gemeldet, oder Kampfform („Kihon“, „Jiyu Ippon“, „Jiyu“, „Shobu“). */
    public static function kumite(string $text): ?string
    {
        $t = preg_replace('/[^a-z]/', '', mb_strtolower($text)) ?? '';
        return match (true) {
            $t === '' => null,
            str_contains($t, 'kihon') => 'kumite:kihon_ippon',
            str_contains($t, 'jiyuippon') => 'kumite:jiyu_ippon',
            str_contains($t, 'jiyu') || str_contains($t, 'shobu') || str_contains($t, 'frei') => 'kumite:jiyu',
            self::ja($text) => 'kumite',
            default => null,
        };
    }
}
