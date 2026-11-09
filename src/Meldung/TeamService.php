<?php

declare(strict_types=1);

namespace Wettkampf\Meldung;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;

/**
 * Teams für Kata-Team und Kumite-Team. Mitglieder können nach der
 * 2:1-Regel für einen anderen Verein starten (team_mitglied.verein_id).
 */
final class TeamService
{
    private readonly Protokoll $protokoll;

    public function __construct(private readonly Database $db)
    {
        $this->protokoll = new Protokoll($db);
    }

    public function teamSpeichern(int $turnierId, string $name, string $disziplin, ?int $vereinId, ?int $teamId = null): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new FachFehler('Bitte einen Teamnamen angeben.');
        }
        if (!in_array($disziplin, ['kata_team', 'kumite_team'], true)) {
            throw new FachFehler('Ungültige Team-Disziplin.');
        }
        $felder = ['name' => $name, 'disziplin' => $disziplin, 'verein_id' => $vereinId];
        if ($teamId === null) {
            $teamId = $this->db->insert('team', $felder + ['turnier_id' => $turnierId]);
        } else {
            $this->team($turnierId, $teamId);
            $this->db->update('team', $felder, ['id' => $teamId]);
        }
        $this->protokoll->eintragen($turnierId, 'team', $teamId, 'gespeichert');
        return $teamId;
    }

    /** Sucht ein Team nach Name und Disziplin oder legt es an (für den Import). */
    public function teamFinden(int $turnierId, string $name, string $disziplin, ?int $vereinId): int
    {
        $id = $this->db->wert(
            'SELECT id FROM team WHERE turnier_id = ? AND disziplin = ? AND LOWER(name) = LOWER(?)',
            [$turnierId, $disziplin, trim($name)],
        );
        return $id !== null ? (int) $id : $this->teamSpeichern($turnierId, $name, $disziplin, $vereinId);
    }

    public function mitgliedHinzufuegen(int $turnierId, int $teamId, int $teilnahmeId, string $rolle = 'stamm', ?int $vereinId = null): void
    {
        $team = $this->team($turnierId, $teamId);
        $teilnahme = $this->db->eine(
            'SELECT t.id, a.verein_id FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id WHERE t.id = ? AND t.turnier_id = ?',
            [$teilnahmeId, $turnierId],
        ) ?? throw new FachFehler('Teilnehmer nicht gefunden.');
        $schonImTeam = $this->db->eine(
            'SELECT tm.name FROM team_mitglied m JOIN team tm ON tm.id = m.team_id WHERE m.teilnahme_id = ? AND tm.disziplin = ?',
            [$teilnahmeId, $team['disziplin']],
        );
        if ($schonImTeam !== null) {
            throw new FachFehler("Der Teilnehmer ist bereits im Team „{$schonImTeam['name']}“ dieser Disziplin.");
        }
        $this->db->insert('team_mitglied', [
            'team_id' => $teamId,
            'teilnahme_id' => $teilnahmeId,
            'verein_id' => $vereinId ?? $teilnahme['verein_id'],
            'rolle' => $rolle === 'ersatz' ? 'ersatz' : 'stamm',
        ]);
        $this->protokoll->eintragen($turnierId, 'team', $teamId, 'mitglied_hinzu', ['teilnahme_id' => $teilnahmeId]);
    }

    public function mitgliedEntfernen(int $turnierId, int $teamId, int $mitgliedId): void
    {
        $this->team($turnierId, $teamId);
        $this->db->delete('team_mitglied', ['id' => $mitgliedId, 'team_id' => $teamId]);
        $this->protokoll->eintragen($turnierId, 'team', $teamId, 'mitglied_entfernt', ['mitglied_id' => $mitgliedId]);
    }

    public function teamLoeschen(int $turnierId, int $teamId): void
    {
        $this->team($turnierId, $teamId);
        $ausgelost = (int) $this->db->wert(
            "SELECT COUNT(*) FROM start s JOIN klasse k ON k.id = s.klasse_id WHERE s.team_id = ? AND k.status <> 'offen'",
            [$teamId],
        );
        if ($ausgelost > 0) {
            throw new FachFehler('Das Team ist bereits in einer ausgelosten Klasse und kann nicht gelöscht werden.');
        }
        $this->db->delete('team', ['id' => $teamId]);
        $this->protokoll->eintragen($turnierId, 'team', $teamId, 'geloescht');
    }

    /** @return array<string, mixed> */
    private function team(int $turnierId, int $teamId): array
    {
        return $this->db->eine('SELECT * FROM team WHERE id = ? AND turnier_id = ?', [$teamId, $turnierId])
            ?? throw new FachFehler('Team nicht gefunden.');
    }
}
