<?php

declare(strict_types=1);

namespace Wettkampf\Datenschutz;

use Wettkampf\Kern\Db\Database;
use Wettkampf\Meldung\Einladungen;
use Wettkampf\Kern\Protokoll;

/**
 * Fristen ohne Cronjob, ausgelöst nach einer Anfrage (App), höchstens einmal je Stunde – nur für Turniere, deren
 * Datenschutz-Angaben die Leitung gespeichert hat (Bestandsturniere werden nicht ungefragt verändert):
 *
 * - Statusseite X Tage nach dem Turnier abschalten (turnier.oeffentlich = 0), falls eingestellt.
 * - Nach Ablauf der Löschfrist pseudonymisieren – aber nur, wenn der Buchhaltungsexport erstellt wurde oder es nichts zu
 *   exportieren gibt (keine Online-Anmeldung mit Startgeld). Sonst bleibt es beim Hinweis an die Turnierleitung,
 *   damit keine Abrechnungsunterlagen verloren gehen.
 */
final class Fristen
{
    public const MERKER = 'datenschutz_fristen_lauf';
    private const ABSTAND = 3600;

    /** @var (callable(): void)|null */
    private $nachPseudonymisierung;

    /** @param (callable(): void)|null $nachPseudonymisierung z. B. alte automatische Sicherungen entfernen */
    public function __construct(private readonly Database $db, private readonly string $eigeneInstanz, ?callable $nachPseudonymisierung = null)
    {
        $this->nachPseudonymisierung = $nachPseudonymisierung;
    }

    /** @return array{live_aus: int, pseudonymisiert: int}|null null = noch nicht fällig */
    public function beiBedarf(?int $jetzt = null): ?array
    {
        $jetzt ??= time();
        $letzter = (int) $this->db->wert('SELECT wert FROM einstellung WHERE schluessel = ?', [self::MERKER]);
        if ($jetzt - $letzter < self::ABSTAND) {
            return null;
        }
        if ($this->db->wert('SELECT 1 FROM einstellung WHERE schluessel = ?', [self::MERKER]) === null) {
            $this->db->insert('einstellung', ['schluessel' => self::MERKER, 'wert' => (string) $jetzt]);
        } else {
            $this->db->update('einstellung', ['wert' => (string) $jetzt], ['schluessel' => self::MERKER]);
        }
        return $this->pruefen($jetzt);
    }

    /** @return array{live_aus: int, pseudonymisiert: int} */
    public function pruefen(?int $jetzt = null): array
    {
        $heute = date('Y-m-d', $jetzt ?? time());
        $liveAus = 0;
        $pseudonymisiert = 0;
        foreach ($this->db->alle('SELECT * FROM turnier WHERE datum < ? ORDER BY id', [$heute]) as $t) {
            $e = DatenschutzEinstellungen::lesen($t);
            if (!$e['eingerichtet']) {
                continue; // Datenschutz-Angaben nie gespeichert: keine automatischen Fristen
            }
            $fuehrung = (string) ($t['fuehrung_instanz'] ?? '');
            if ($fuehrung !== '' && !hash_equals($this->eigeneInstanz, $fuehrung)) {
                continue; // wird woanders geführt
            }
            $ab = DatenschutzEinstellungen::liveAusAb($t);
            if ((int) $t['oeffentlich'] === 1 && $ab !== null && $heute >= $ab && $e['live_abgeschaltet_am'] === null) {
                $this->db->update('turnier', ['oeffentlich' => 0], ['id' => $t['id']]);
                (new DatenschutzEinstellungen($this->db))->vermerken((int) $t['id'], 'live_abgeschaltet_am', date('Y-m-d H:i:s', $jetzt ?? time()));
                (new Protokoll($this->db))->eintragen((int) $t['id'], 'turnier', (int) $t['id'], 'statusseite_abgeschaltet', ['nach_tagen' => $e['live_tage']]);
                $liveAus++;
            }
            if ($e['pseudonymisiert_am'] === null && $heute >= DatenschutzEinstellungen::loeschenAb($t) && $this->exportErledigt($t)) {
                (new Pseudonymisierung($this->db))->turnier((int) $t['id'], $this->eigeneInstanz);
                $pseudonymisiert++;
            }
        }
        (new Einladungen($this->db, ''))->aufraeumen($jetzt); // Einladungen ohne erneute Einwilligung (Speicherbegrenzung)
        if ($pseudonymisiert > 0 && $this->nachPseudonymisierung !== null) {
            ($this->nachPseudonymisierung)();
        }
        return ['live_aus' => $liveAus, 'pseudonymisiert' => $pseudonymisiert];
    }

    /**
     * Gibt es Abrechnungsunterlagen, die vorher exportiert werden müssen?
     *
     * @param array<string, mixed> $turnier
     */
    public function exportErledigt(array $turnier): bool
    {
        if (DatenschutzEinstellungen::lesen($turnier)['exportiert_am'] !== null) {
            return true;
        }
        // Rechnungen je Dojo eingerichtet (Absender eingetragen) → es gibt Belege
        $rechnung = (json_decode((string) ($turnier['einstellungen'] ?? ''), true) ?: [])['rechnung'] ?? [];
        if (is_array($rechnung) && trim((string) ($rechnung['absender'] ?? '')) !== '') {
            return false;
        }
        return (int) $this->db->wert(
            "SELECT COUNT(*) FROM anmeldung WHERE turnier_id = ? AND status <> 'abgelehnt' AND (aufforderung_cent > 0 OR bezahlt_cent > 0)",
            [$turnier['id']],
        ) === 0;
    }
}
