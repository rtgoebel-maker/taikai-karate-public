<?php

declare(strict_types=1);

namespace Wettkampf\Turnier;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Domain\Graduierung;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Kern\Uuid;

/**
 * Turniere, Tatamis, Vereine und Teilnehmer anlegen und pflegen.
 */
final class StammdatenService
{
    private readonly Protokoll $protokoll;

    public function __construct(private readonly Database $db)
    {
        $this->protokoll = new Protokoll($db);
    }

    /** Status einer Teilnahme → Bezeichnung */
    public const STATUS = [
        'gemeldet' => 'gemeldet',
        'anwesend' => 'anwesend',
        'gesperrt' => 'gesperrt (z. B. Wettkampfarzt)',
        'abgemeldet' => 'abgemeldet',
        'nicht_erschienen' => 'nicht erschienen',
    ];
    /** Diese Starter fallen aus: offene und künftige Kämpfe gehen kampflos (Kiken) an die Gegner. */
    public const AUSGEFALLEN = ['gesperrt', 'abgemeldet', 'nicht_erschienen'];

    /** @param array<string, mixed> $daten */
    /**
     * Löscht ein Turnier samt allem, was dazugehört. Athleten ohne Teilnahme an einem anderen
     * Turnier werden mitgelöscht (keine personenbezogenen Daten ohne Turnier), ebenso Vereine,
     * die nur durch dieses Turnier genutzt wurden und jetzt leer sind.
     *
     * @return array{athleten: int, vereine: int}
     */
    public function turnierLoeschen(int $turnierId): array
    {
        return $this->db->transaktion(function () use ($turnierId) {
            $athleten = array_map('intval', $this->db->spalte('SELECT athlet_id FROM teilnahme WHERE turnier_id = ?', [$turnierId]));
            $vereine = array_map('intval', $this->db->spalte(
                'SELECT a.verein_id FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id WHERE t.turnier_id = ? AND a.verein_id IS NOT NULL
                 UNION SELECT verein_id FROM team WHERE turnier_id = ? AND verein_id IS NOT NULL
                 UNION SELECT verein_id FROM anmeldung WHERE turnier_id = ? AND verein_id IS NOT NULL',
                [$turnierId, $turnierId, $turnierId],
            ));
            $name = (string) $this->db->wert('SELECT name FROM turnier WHERE id = ?', [$turnierId]);
            $this->db->delete('turnier', ['id' => $turnierId]);
            // Verlauf des Turniers geht mit; das Löschen selbst bleibt global vermerkt (90 Tage)
            $protokoll = new Protokoll($this->db);
            $protokoll->turnierEntfernen($turnierId);
            $protokoll->eintragen(null, 'turnier', $turnierId, 'geloescht', ['name' => $name]);
            $geloescht = ['athleten' => 0, 'vereine' => 0];
            foreach ($athleten as $id) {
                if ((int) $this->db->wert('SELECT COUNT(*) FROM teilnahme WHERE athlet_id = ?', [$id]) === 0) {
                    $this->db->delete('athlet', ['id' => $id]);
                    $geloescht['athleten']++;
                }
            }
            foreach ($vereine as $id) {
                if (!$this->vereinGenutzt($id)) {
                    $this->db->delete('verein', ['id' => $id]);
                    $geloescht['vereine']++;
                }
            }
            return $geloescht;
        });
    }

    /**
     * Vereine ohne Athleten, Teams und Anmeldungen (z. B. nach dem Löschen aller Turniere).
     *
     * @return list<int>
     */
    public function leereVereine(): array
    {
        return array_values(array_filter(
            array_map('intval', $this->db->spalte('SELECT id FROM verein ORDER BY name')),
            fn(int $id) => !$this->vereinGenutzt($id),
        ));
    }

    /** @return int Anzahl gelöschter Vereine */
    public function leereVereineLoeschen(): int
    {
        return $this->db->transaktion(function () {
            $leer = $this->leereVereine();
            foreach ($leer as $id) {
                $this->db->delete('verein', ['id' => $id]);
            }
            return count($leer);
        });
    }

    private function vereinGenutzt(int $vereinId): bool
    {
        return (int) $this->db->wert(
            'SELECT (SELECT COUNT(*) FROM athlet WHERE verein_id = ?) + (SELECT COUNT(*) FROM team WHERE verein_id = ?)
                  + (SELECT COUNT(*) FROM team_mitglied WHERE verein_id = ?) + (SELECT COUNT(*) FROM anmeldung WHERE verein_id = ?)
                  + (SELECT COUNT(*) FROM benutzer WHERE verein_id = ?)',
            [$vereinId, $vereinId, $vereinId, $vereinId, $vereinId],
        ) > 0;
    }

    public function turnierAnlegen(array $daten): int
    {
        return $this->db->transaktion(function () use ($daten) {
            $id = $this->db->insert('turnier', $this->turnierFelder($daten) + [
                'uuid' => Uuid::v4(),
                'status' => TurnierPhase::VORBEREITUNG,
                'erstellt_am' => date('Y-m-d H:i:s'),
            ]);
            $this->tatamisAnpassen($id, (int) $daten['anzahl_tatamis']);
            $this->protokoll->eintragen($id, 'turnier', $id, 'angelegt');
            return $id;
        });
    }

    /** @param array<string, mixed> $daten */
    public function turnierAktualisieren(int $id, array $daten): void
    {
        $this->db->transaktion(function () use ($id, $daten) {
            $this->db->update('turnier', $this->turnierFelder($daten), ['id' => $id]);
            $this->tatamisAnpassen($id, (int) $daten['anzahl_tatamis']);
            $this->protokoll->eintragen($id, 'turnier', $id, 'geaendert');
        });
    }

    /**
     * Legt fehlende Tatamis an bzw. entfernt überzählige – aber nur, wenn
     * ihnen noch keine Phase zugeordnet ist.
     */
    public function tatamisAnpassen(int $turnierId, int $anzahl): void
    {
        if ($anzahl < 1 || $anzahl > 20) {
            throw new FachFehler('Die Anzahl der Tatamis muss zwischen 1 und 20 liegen.');
        }
        $vorhanden = $this->db->alle('SELECT * FROM tatami WHERE turnier_id = ? ORDER BY nummer', [$turnierId]);
        for ($nr = count($vorhanden) + 1; $nr <= $anzahl; $nr++) {
            $this->db->insert('tatami', ['turnier_id' => $turnierId, 'nummer' => $nr, 'bezeichnung' => "Tatami $nr"]);
        }
        foreach (array_slice($vorhanden, $anzahl) as $tatami) {
            $belegt = (int) $this->db->wert('SELECT COUNT(*) FROM phase WHERE tatami_id = ?', [$tatami['id']]);
            if ($belegt > 0) {
                throw new FachFehler("{$tatami['bezeichnung']} ist im Tatami-Plan belegt und kann nicht entfernt werden.");
            }
            $this->db->delete('tatami', ['id' => $tatami['id']]);
        }
    }

    /**
     * @param array<string, mixed> $daten
     * @return array<string, mixed>
     */
    private function turnierFelder(array $daten): array
    {
        $name = trim((string) ($daten['name'] ?? ''));
        $datum = (string) ($daten['datum'] ?? '');
        if ($name === '') {
            throw new FachFehler('Bitte einen Turniernamen angeben.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            throw new FachFehler('Bitte ein gültiges Turnierdatum angeben.');
        }
        return [
            'name' => $name,
            'datum' => $datum,
            'ort' => trim((string) ($daten['ort'] ?? '')) ?: null,
            'halle' => trim((string) ($daten['halle'] ?? '')) ?: null,
            'anzahl_tatamis' => (int) $daten['anzahl_tatamis'],
            'min_graduierung' => ($daten['min_graduierung'] ?? '') === '' ? null : (int) $daten['min_graduierung'],
            'meldeschluss' => ($daten['meldeschluss'] ?? '') ?: null,
            'oeffentlich' => empty($daten['oeffentlich']) ? 0 : 1,
        ];
    }

    /** Sucht einen Verein nach Name (ohne Groß-/Kleinschreibung) oder legt ihn an. */
    public function vereinFinden(string $name, bool $anlegen = true): ?int
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return null;
        }
        $id = $this->db->wert('SELECT id FROM verein WHERE LOWER(name) = LOWER(?)', [$name]);
        if ($id !== null) {
            return (int) $id;
        }
        return $anlegen ? $this->db->insert('verein', ['uuid' => Uuid::v4(), 'name' => $name]) : null;
    }

    /**
     * Legt einen Teilnehmer an oder aktualisiert ihn (Athlet + Teilnahme).
     *
     * @param array<string, mixed> $daten  vorname, nachname, geschlecht, geburtsdatum,
     *        alter_manuell, graduierung, verein_id|verein_name, disziplinen (list),
     *        status, ausweis_geprueft, nachmeldung, vermerk
     * @return int Teilnahme-ID
     */
    public function teilnehmerSpeichern(int $turnierId, array $daten, ?int $teilnahmeId = null): int
    {
        $vorname = trim((string) ($daten['vorname'] ?? ''));
        $nachname = trim((string) ($daten['nachname'] ?? ''));
        $geschlecht = (string) ($daten['geschlecht'] ?? '');
        $grad = $daten['graduierung'] ?? null;
        $geburt = ($daten['geburtsdatum'] ?? '') ?: null;
        $alter = ($daten['alter_manuell'] ?? '') === '' || $daten['alter_manuell'] === null ? null : (int) $daten['alter_manuell'];

        if ($vorname === '' || $nachname === '') {
            throw new FachFehler('Vor- und Nachname sind Pflichtfelder.');
        }
        if (!in_array($geschlecht, ['w', 'm', 'd'], true)) {
            throw new FachFehler("Bitte das Geschlecht angeben ($vorname $nachname).");
        }
        if (!is_numeric($grad) || (int) $grad < Graduierung::MIN || (int) $grad > Graduierung::MAX) {
            throw new FachFehler("Bitte eine gültige Graduierung angeben ($vorname $nachname).");
        }
        if ($geburt === null && $alter === null) {
            throw new FachFehler("Bitte Geburtsdatum oder Alter angeben ($vorname $nachname).");
        }
        if ($geburt !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $geburt)) {
            throw new FachFehler("Ungültiges Geburtsdatum ($vorname $nachname).");
        }

        $vereinId = isset($daten['verein_id']) && $daten['verein_id'] !== '' ? (int) $daten['verein_id'] : null;
        if ($vereinId === null && !empty($daten['verein_name'])) {
            $vereinId = $this->vereinFinden((string) $daten['verein_name']);
        }

        // Einträge: 'kata', 'kumite' oder 'kumite:<form>' (Kampfform vorgegeben, z. B. D2A vs. D2B)
        $disziplinen = array_values(array_unique(array_filter(
            array_map('strval', (array) ($daten['disziplinen'] ?? [])),
            static fn(string $d) => (bool) preg_match('/^(kata|kumite)(:(kihon_ippon|jiyu_ippon|jiyu))?$/', $d),
        )));
        $athlet = [
            'vorname' => $vorname,
            'nachname' => $nachname,
            'geschlecht' => $geschlecht,
            'geburtsdatum' => $geburt,
            'graduierung' => (int) $grad,
            'verein_id' => $vereinId,
        ];
        $teilnahme = [
            'alter_manuell' => $geburt === null ? $alter : null,
            'disziplinen' => implode(',', $disziplinen),
            'status' => isset(self::STATUS[$daten['status'] ?? ''])
                ? ($daten['status'] ?? 'gemeldet') : 'gemeldet',
            'ausweis_geprueft' => empty($daten['ausweis_geprueft']) ? 0 : 1,
            'nachmeldung' => empty($daten['nachmeldung']) ? 0 : 1,
            'vermerk' => trim((string) ($daten['vermerk'] ?? '')) ?: null,
        ];

        return $this->db->transaktion(function () use ($turnierId, $teilnahmeId, $athlet, $teilnahme) {
            if ($teilnahmeId !== null) {
                $alt = $this->db->eine('SELECT * FROM teilnahme WHERE id = ? AND turnier_id = ?', [$teilnahmeId, $turnierId]);
                if ($alt === null) {
                    throw new FachFehler('Teilnehmer nicht gefunden.');
                }
                $athletId = (int) $alt['athlet_id'];
                // Athleten gehören zu genau einem Turnier: teilt sich diese Teilnahme den Athleten noch mit
                // einem anderen Turnier (ältere Importe), bekommt sie vor der Änderung eine eigene Kopie
                if ((int) $this->db->wert('SELECT COUNT(*) FROM teilnahme WHERE athlet_id = ?', [$athletId]) > 1) {
                    $kopie = $this->db->eine('SELECT * FROM athlet WHERE id = ?', [$athletId]) ?? [];
                    unset($kopie['id']);
                    $athletId = $this->db->insert('athlet', ['uuid' => Uuid::v4()] + $kopie);
                    $this->db->update('teilnahme', ['athlet_id' => $athletId], ['id' => $teilnahmeId]);
                }
                $this->db->update('athlet', $athlet, ['id' => $athletId]);
                $this->db->update('teilnahme', $teilnahme, ['id' => $teilnahmeId]);
                $this->protokoll->eintragen($turnierId, 'teilnahme', $teilnahmeId, 'geaendert', $athlet + $teilnahme);
                return $teilnahmeId;
            }
            $athletId = $this->db->insert('athlet', $athlet + ['uuid' => Uuid::v4()]);
            $startnummer = 1 + (int) $this->db->wert('SELECT MAX(startnummer) FROM teilnahme WHERE turnier_id = ?', [$turnierId]);
            $id = $this->db->insert('teilnahme', $teilnahme + [
                'turnier_id' => $turnierId,
                'athlet_id' => $athletId,
                'startnummer' => $startnummer,
            ]);
            $this->protokoll->eintragen($turnierId, 'teilnahme', $id, 'angelegt');
            return $id;
        });
    }

    public function teilnehmerLoeschen(int $turnierId, int $teilnahmeId): void
    {
        $this->db->transaktion(function () use ($turnierId, $teilnahmeId) {
            // zuerst die Zugehörigkeit zum Turnier – sonst verriete die Meldung unten etwas über fremde Turniere (OWASP A01)
            $teilnahme = $this->db->eine('SELECT * FROM teilnahme WHERE id = ? AND turnier_id = ?', [$teilnahmeId, $turnierId])
                ?? throw new NichtGefunden('Teilnehmer nicht gefunden.');
            $hatErgebnis = (int) $this->db->wert(
                "SELECT COUNT(*) FROM start s
                   JOIN begegnung b ON b.aka_start_id = s.id OR b.shiro_start_id = s.id
                  WHERE s.teilnahme_id = ? AND b.status = 'beendet'",
                [$teilnahmeId],
            );
            if ($hatErgebnis > 0) {
                throw new FachFehler('Der Teilnehmer hat bereits Ergebnisse. Bitte stattdessen auf „abgemeldet“ oder „nicht erschienen“ setzen.');
            }
            $this->db->delete('teilnahme', ['id' => $teilnahmeId]);
            // Athlet nur löschen, wenn er an keinem anderen Turnier teilnimmt
            if ((int) $this->db->wert('SELECT COUNT(*) FROM teilnahme WHERE athlet_id = ?', [$teilnahme['athlet_id']]) === 0) {
                $this->db->delete('athlet', ['id' => $teilnahme['athlet_id']]);
            }
            $this->protokoll->eintragen($turnierId, 'teilnahme', $teilnahmeId, 'geloescht');
        });
    }
}
