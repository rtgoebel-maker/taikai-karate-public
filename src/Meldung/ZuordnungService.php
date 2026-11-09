<?php

declare(strict_types=1);

namespace Wettkampf\Meldung;

use Wettkampf\Domain\Alter;
use Wettkampf\Domain\Klasse\Kandidat;
use Wettkampf\Domain\Klasse\KlassenRegel;
use Wettkampf\Domain\Klasse\Zuordnung;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;

/**
 * Erzeugt aus den Meldungen (Teilnahme.disziplinen, Teams) die Starts in
 * den Klassen. Regeln:
 *  - Manuell gesetzte Starts bleiben unangetastet.
 *  - Nur Klassen im Status „offen“ (noch nicht ausgelost) werden automatisch geändert.
 *  - Zusammengelegte Klassen leiten auf ihre Zielklasse um.
 */
final class ZuordnungService
{
    /** @var array<int, array<string, mixed>> */
    private array $klassen = [];
    private ?int $geladenFuer = null;

    public function __construct(private readonly Database $db) {}

    /** @return list<string> Hinweise zu Problemen (leer = alles zugeordnet) */
    public function teilnehmerZuordnen(int $turnierId, int $teilnahmeId): array
    {
        $this->klassenLaden($turnierId);
        $t = $this->db->eine(
            'SELECT t.*, a.vorname, a.nachname, a.geschlecht, a.geburtsdatum, a.graduierung, tu.datum AS turnier_datum, tu.min_graduierung
               FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id JOIN turnier tu ON tu.id = t.turnier_id
              WHERE t.id = ? AND t.turnier_id = ?',
            [$teilnahmeId, $turnierId],
        );
        if ($t === null) {
            return [];
        }
        $name = "{$t['vorname']} {$t['nachname']}";
        $starts = $this->db->alle('SELECT * FROM start WHERE teilnahme_id = ?', [$teilnahmeId]);

        if ($t['status'] === 'abgemeldet') {
            $this->abmelden($starts);
            return [];
        }

        $alter = $t['geburtsdatum'] ? Alter::amStichtag($t['geburtsdatum'], $t['turnier_datum']) : ($t['alter_manuell'] === null ? null : (int) $t['alter_manuell']);
        $meldungen = [];
        foreach (array_filter(explode(',', (string) $t['disziplinen'])) as $eintrag) {
            [$disziplin, $form] = array_pad(explode(':', $eintrag, 2), 2, null);
            $meldungen[$disziplin] = $form;
        }

        $hinweise = [];
        if ($t['min_graduierung'] !== null && (int) $t['graduierung'] < (int) $t['min_graduierung']) {
            $hinweise[] = "$name: Graduierung unter der Mindestgraduierung des Turniers.";
        }
        foreach (['kata', 'kumite'] as $disziplin) {
            $vorhanden = array_values(array_filter($starts, fn($s) => ($this->klassen[$s['klasse_id']]['disziplin'] ?? null) === $disziplin));
            if (!array_key_exists($disziplin, $meldungen)) {
                $this->autoStartsEntfernen($vorhanden);
                continue;
            }
            $kandidat = new Kandidat($disziplin, $t['geschlecht'], $alter, (int) $t['graduierung'], $meldungen[$disziplin]);
            $hinweis = $this->einordnen($kandidat, $vorhanden, ['teilnahme_id' => $teilnahmeId]);
            if ($hinweis !== null) {
                $hinweise[] = "$name: $hinweis";
            }
        }
        return $hinweise;
    }

    /** @return list<string> */
    public function teamZuordnen(int $turnierId, int $teamId): array
    {
        $this->klassenLaden($turnierId);
        $team = $this->db->eine('SELECT * FROM team WHERE id = ? AND turnier_id = ?', [$teamId, $turnierId]);
        if ($team === null) {
            return [];
        }
        $datum = (string) $this->db->wert('SELECT datum FROM turnier WHERE id = ?', [$turnierId]);
        $mitglieder = [];
        foreach ($this->db->alle(
            'SELECT a.geschlecht, a.geburtsdatum, a.graduierung, t.alter_manuell
               FROM team_mitglied m JOIN teilnahme t ON t.id = m.teilnahme_id JOIN athlet a ON a.id = t.athlet_id
              WHERE m.team_id = ?',
            [$teamId],
        ) as $m) {
            $mitglieder[] = [
                'geschlecht' => $m['geschlecht'],
                'alter' => $m['geburtsdatum'] ? Alter::amStichtag($m['geburtsdatum'], $datum) : ($m['alter_manuell'] === null ? null : (int) $m['alter_manuell']),
                'graduierung' => (int) $m['graduierung'],
            ];
        }
        $starts = $this->db->alle('SELECT * FROM start WHERE team_id = ?', [$teamId]);
        if ($mitglieder === []) {
            $this->autoStartsEntfernen($starts);
            return ["Team {$team['name']}: noch keine Mitglieder."];
        }
        $hinweis = $this->einordnen(Kandidat::team($team['disziplin'], $mitglieder), $starts, ['team_id' => $teamId]);
        return $hinweis === null ? [] : ["Team {$team['name']}: $hinweis"];
    }

    /** @return array{starts: int, hinweise: list<string>} */
    public function alleZuordnen(int $turnierId): array
    {
        return $this->db->transaktion(function () use ($turnierId) {
            $this->geladenFuer = null;
            $hinweise = [];
            foreach ($this->db->spalte('SELECT id FROM teilnahme WHERE turnier_id = ?', [$turnierId]) as $id) {
                array_push($hinweise, ...$this->teilnehmerZuordnen($turnierId, (int) $id));
            }
            foreach ($this->db->spalte('SELECT id FROM team WHERE turnier_id = ?', [$turnierId]) as $id) {
                array_push($hinweise, ...$this->teamZuordnen($turnierId, (int) $id));
            }
            (new Protokoll($this->db))->eintragen($turnierId, 'turnier', $turnierId, 'zuordnung');
            $starts = (int) $this->db->wert(
                "SELECT COUNT(*) FROM start s JOIN klasse k ON k.id = s.klasse_id WHERE k.turnier_id = ? AND s.status <> 'zurueckgezogen'",
                [$turnierId],
            );
            return ['starts' => $starts, 'hinweise' => $hinweise];
        });
    }

    /**
     * Ordnet einen Kandidaten ein und gibt ggf. einen Hinweis zurück.
     *
     * @param list<array<string, mixed>> $vorhanden Starts derselben Disziplin
     * @param array<string, int> $wer ['teilnahme_id' => …] oder ['team_id' => …]
     */
    private function einordnen(Kandidat $kandidat, array $vorhanden, array $wer): ?string
    {
        if (array_filter($vorhanden, static fn($s) => (int) $s['manuell'] === 1)) {
            return null; // manuelle Zuordnung hat Vorrang
        }
        $regeln = array_map(
            static fn(array $k) => KlassenRegel::ausZeile($k),
            array_values(array_filter($this->klassen, static fn($k) => $k['disziplin'] === $kandidat->disziplin)),
        );
        $ergebnis = (new Zuordnung($regeln))->zuordnen($kandidat);
        $ziele = [];
        foreach ($ergebnis['klassen'] as $regel) {
            $ziele[$this->ziel($regel->id)] = $regel->id;
        }
        $text = self::disziplinName($kandidat->disziplin);

        // Bereits in einer passenden Klasse → nichts tun
        foreach ($vorhanden as $s) {
            if (isset($ziele[(int) $s['klasse_id']]) && $s['status'] !== 'zurueckgezogen') {
                return null;
            }
        }
        if ($ergebnis['status'] === 'keine') {
            $this->autoStartsEntfernen($vorhanden);
            return "$text gemeldet, aber keine passende Klasse (Alter/Graduierung/Geschlecht prüfen).";
        }
        if (count($ziele) > 1) {
            $codes = implode(' / ', array_map(fn($id) => $this->klassen[$id]['code'], array_keys($ziele)));
            return "$text: mehrere Klassen möglich ($codes) – bitte Kumite-Form angeben oder Klasse manuell wählen.";
        }
        $zielId = (int) array_key_first($ziele);
        $herkunft = $ziele[$zielId] !== $zielId ? $ziele[$zielId] : null;
        if ($this->klassen[$zielId]['status'] !== 'offen') {
            return "$text: Klasse {$this->klassen[$zielId]['code']} ist bereits ausgelost – Start bitte manuell nachtragen.";
        }
        if (!$this->autoStartsEntfernen($vorhanden)) {
            return "$text: bisherige Klasse ist bereits ausgelost – Wechsel bitte manuell prüfen.";
        }
        $this->db->insert('start', $wer + ['klasse_id' => $zielId, 'herkunft_klasse_id' => $herkunft, 'manuell' => 0]);
        return null;
    }

    /**
     * Entfernt automatisch erzeugte Starts aus offenen Klassen.
     *
     * @param list<array<string, mixed>> $starts
     * @return bool false, wenn ein Start in einer bereits ausgelosten Klasse liegt
     */
    private function autoStartsEntfernen(array $starts): bool
    {
        $alleEntfernt = true;
        foreach ($starts as $s) {
            if ((int) $s['manuell'] === 1) {
                continue;
            }
            if (($this->klassen[$s['klasse_id']]['status'] ?? 'offen') === 'offen') {
                $this->db->delete('start', ['id' => $s['id']]);
            } elseif ($s['status'] !== 'zurueckgezogen') {
                $alleEntfernt = false;
            }
        }
        return $alleEntfernt;
    }

    /** @param list<array<string, mixed>> $starts */
    private function abmelden(array $starts): void
    {
        foreach ($starts as $s) {
            if (($this->klassen[$s['klasse_id']]['status'] ?? 'offen') === 'offen') {
                $this->db->delete('start', ['id' => $s['id']]);
            } else {
                $this->db->update('start', ['status' => 'zurueckgezogen'], ['id' => $s['id']]);
            }
        }
    }

    private function ziel(int $klasseId): int
    {
        $tiefe = 0;
        while (($naechste = $this->klassen[$klasseId]['zusammengelegt_in'] ?? null) !== null && $tiefe++ < 10) {
            $klasseId = (int) $naechste;
        }
        return $klasseId;
    }

    private function klassenLaden(int $turnierId): void
    {
        if ($this->geladenFuer === $turnierId) {
            return;
        }
        $this->klassen = [];
        foreach ($this->db->alle('SELECT * FROM klasse WHERE turnier_id = ?', [$turnierId]) as $k) {
            $this->klassen[(int) $k['id']] = $k;
        }
        $this->geladenFuer = $turnierId;
    }

    private static function disziplinName(string $d): string
    {
        return match ($d) {
            'kata' => 'Kata',
            'kumite' => 'Kumite',
            'kata_team' => 'Kata-Team',
            'kumite_team' => 'Kumite-Team',
            default => $d,
        };
    }
}
