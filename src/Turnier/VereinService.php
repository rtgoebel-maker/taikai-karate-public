<?php

declare(strict_types=1);

namespace Wettkampf\Turnier;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;

/**
 * Vereine/Dojos über alle Turniere: Übersicht, vermutliche Doppelungen, Zusammenführen mit Vorschau und die Vereinsseite
 * (Teilnehmer und ihre Turniere, Coach-Konten, Anmeldungen) – nur für die Administration.
 *
 * Datensparsamkeit: Die Vereinsseite zeigt je Person nur Name, Jahrgang, Graduierung und Turniere – kein Geburtsdatum,
 * keine Ausweisnummer. Personen verschwinden mit dem letzten Turnier, in dem sie gemeldet sind.
 */
final class VereinService
{
    public function __construct(private readonly Database $db) {}

    /** @return list<array<string, mixed>> Vereine mit Zahlen (Athleten, Turniere, Coach-Konten) */
    public function uebersicht(): array
    {
        return $this->db->alle(
            "SELECT v.id, v.name, v.kurzname,
                    (SELECT COUNT(*) FROM athlet a WHERE a.verein_id = v.id) AS athleten,
                    (SELECT COUNT(DISTINCT t.turnier_id) FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id WHERE a.verein_id = v.id) AS turniere,
                    (SELECT COUNT(*) FROM benutzer b WHERE b.verein_id = v.id AND b.aktiv = 1) AS konten
               FROM verein v ORDER BY v.name",
        );
    }

    /**
     * Paare mit sehr ähnlichem Namen („Karate Dojo Bergheim“ / „Karate-Dojo Bergheim“, „TSV Seefeld“ / „TSV Seefeld Karate“).
     *
     * @param list<array<string, mixed>> $vereine aus uebersicht()
     * @return list<array{a: array<string, mixed>, b: array<string, mixed>}> b ist der Verein mit mehr Athleten (Vorschlag „nach“)
     */
    public function vermutlichDoppelt(array $vereine): array
    {
        $paare = [];
        $anzahl = count($vereine);
        for ($i = 0; $i < $anzahl; $i++) {
            for ($j = $i + 1; $j < $anzahl; $j++) {
                if (self::aehnlich((string) $vereine[$i]['name'], (string) $vereine[$j]['name'])) {
                    [$a, $b] = (int) $vereine[$i]['athleten'] > (int) $vereine[$j]['athleten'] ? [$vereine[$j], $vereine[$i]] : [$vereine[$i], $vereine[$j]];
                    $paare[] = ['a' => $a, 'b' => $b];
                }
            }
        }
        return $paare;
    }

    public static function aehnlich(string $x, string $y): bool
    {
        $x = self::normal($x);
        $y = self::normal($y);
        if ($x === '' || $y === '') {
            return false;
        }
        if ($x === $y) {
            return true;
        }
        $kurz = strlen($x) <= strlen($y) ? $x : $y;
        $lang = $kurz === $x ? $y : $x;
        if (strlen($kurz) >= 6 && str_contains($lang, $kurz)) {
            return true;
        }
        return strlen($kurz) >= 8 && strlen($lang) <= 255 && levenshtein($x, $y) <= 2;
    }

    /** Kleinbuchstaben, Umlaute ausgeschrieben, ohne „e. V.“ und ohne Satz-/Leerzeichen. */
    private static function normal(string $name): string
    {
        $name = mb_strtolower($name);
        $name = strtr($name, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $name = (string) preg_replace('/\be\.?\s?v\.?(?=\s|$)/u', '', $name);
        return (string) preg_replace('/[^a-z0-9]/', '', $name);
    }

    /**
     * Was beim Zusammenführen umzieht.
     *
     * @return array{von: array<string, mixed>, nach: array<string, mixed>, athleten: int, teams: int, anmeldungen: int, konten: int}
     */
    public function vorschau(int $von, int $nach): array
    {
        if ($von === $nach || $von <= 0 || $nach <= 0) {
            throw new FachFehler('Bitte zwei verschiedene Vereine wählen.');
        }
        $v = $this->db->eine('SELECT id, name FROM verein WHERE id = ?', [$von]) ?? throw new FachFehler('Verein nicht gefunden.');
        $n = $this->db->eine('SELECT id, name FROM verein WHERE id = ?', [$nach]) ?? throw new FachFehler('Verein nicht gefunden.');
        return [
            'von' => $v,
            'nach' => $n,
            'athleten' => (int) $this->db->wert('SELECT COUNT(*) FROM athlet WHERE verein_id = ?', [$von]),
            'teams' => (int) $this->db->wert('SELECT COUNT(*) FROM team WHERE verein_id = ?', [$von]),
            'anmeldungen' => (int) $this->db->wert('SELECT COUNT(*) FROM anmeldung WHERE verein_id = ?', [$von]),
            'konten' => (int) $this->db->wert('SELECT COUNT(*) FROM benutzer WHERE verein_id = ?', [$von]),
        ];
    }

    /** Doppelten Verein in einen anderen überführen; der erste wird gelöscht. Im Änderungsverlauf festgehalten. */
    public function zusammenfuehren(int $von, int $nach): void
    {
        $vorschau = $this->vorschau($von, $nach);
        $this->db->transaktion(function () use ($von, $nach, $vorschau) {
            $this->db->update('athlet', ['verein_id' => $nach], ['verein_id' => $von]);
            $this->db->update('team', ['verein_id' => $nach], ['verein_id' => $von]);
            $this->db->update('team_mitglied', ['verein_id' => $nach], ['verein_id' => $von]);
            $this->db->update('anmeldung', ['verein_id' => $nach], ['verein_id' => $von]);
            $this->db->update('benutzer', ['verein_id' => $nach], ['verein_id' => $von]); // Coach-Konten des Dojos
            $this->db->delete('verein', ['id' => $von]);
            (new Protokoll($this->db))->eintragen(null, 'verein', $nach, 'zusammengefuehrt', [
                'von' => $vorschau['von']['name'], 'nach' => $vorschau['nach']['name'],
                'athleten' => $vorschau['athleten'], 'teams' => $vorschau['teams'], 'anmeldungen' => $vorschau['anmeldungen'], 'konten' => $vorschau['konten'],
            ]);
        });
    }

    /**
     * Vereinsseite: Teilnehmer mit ihren Turnieren, Coach-Konten, Online-Anmeldungen.
     *
     * @return array{verein: array<string, mixed>, personen: list<array<string, mixed>>, konten: list<array<string, mixed>>, anmeldungen: list<array<string, mixed>>, turniere: list<array<string, mixed>>}
     */
    public function seite(int $id): array
    {
        $verein = $this->db->eine('SELECT id, name, kurzname FROM verein WHERE id = ?', [$id]) ?? throw new FachFehler('Verein nicht gefunden.');
        $zeilen = $this->db->alle(
            "SELECT a.id AS athlet_id, a.vorname, a.nachname, SUBSTR(a.geburtsdatum, 1, 4) AS jahrgang, a.graduierung,
                    t.id AS teilnahme_id, t.status, tu.id AS turnier_id, tu.name AS turnier, tu.datum
               FROM athlet a LEFT JOIN teilnahme t ON t.athlet_id = a.id LEFT JOIN turnier tu ON tu.id = t.turnier_id
              WHERE a.verein_id = ?
              ORDER BY a.nachname, a.vorname, a.id, tu.datum DESC",
            [$id],
        );
        $personen = [];
        foreach ($zeilen as $z) {
            $aid = (int) $z['athlet_id'];
            $personen[$aid] ??= ['id' => $aid, 'name' => trim($z['nachname'] . ', ' . $z['vorname'], ', '), 'jahrgang' => $z['jahrgang'], 'graduierung' => (int) $z['graduierung'], 'turniere' => []];
            if ($z['turnier_id'] !== null) {
                $personen[$aid]['turniere'][] = ['id' => (int) $z['turnier_id'], 'name' => (string) $z['turnier'], 'datum' => (string) $z['datum'], 'teilnahme_id' => (int) $z['teilnahme_id'], 'status' => (string) $z['status']];
            }
        }
        $turniere = [];
        foreach ($personen as $p) {
            foreach ($p['turniere'] as $t) {
                $turniere[$t['id']] ??= $t + ['personen' => 0];
                $turniere[$t['id']]['personen']++;
            }
        }
        usort($turniere, static fn($x, $y) => strcmp($y['datum'], $x['datum']));
        return [
            'verein' => $verein,
            'personen' => array_values($personen),
            'konten' => $this->db->alle('SELECT id, name, email, aktiv FROM benutzer WHERE verein_id = ? ORDER BY name', [$id]),
            'anmeldungen' => $this->db->alle(
                'SELECT an.id, an.turnier_id, an.status, an.eingereicht_am, an.ansprechpartner, tu.name AS turnier, tu.datum
                   FROM anmeldung an JOIN turnier tu ON tu.id = an.turnier_id WHERE an.verein_id = ? ORDER BY tu.datum DESC, an.id DESC',
                [$id],
            ),
            'turniere' => $turniere,
        ];
    }
}
