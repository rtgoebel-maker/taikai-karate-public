<?php

declare(strict_types=1);

namespace Wettkampf\Meldung;

use Wettkampf\Kern\Db\Database;
use Wettkampf\Turnier\SystemEinstellungen;

/**
 * Für welche Turniere gilt ein selbst angelegter Dojo-Zugang? Standardmäßig nur für das Turnier, für das er bei der
 * Online-Anmeldung angelegt wurde (Coaches melden sich in der Regel je Turnier an). Mit der Systemeinstellung
 * „Ein Zugang für mehrere Turniere“ gilt er für alle. Von der Administration angelegte Konten (ohne Ablaufdatum)
 * sind nie beschränkt.
 */
final class DojoZugangBindung
{
    public function __construct(private readonly Database $db) {}

    /**
     * @return list<int>|null null = nicht beschränkt; sonst die Turniere, für die der Zugang gilt
     */
    public function turniere(?int $kontoId): ?array
    {
        if ($kontoId === null || $kontoId <= 0 || (new SystemEinstellungen($this->db))->dojoZugangMehrereTurniere()) {
            return null;
        }
        $ablauf = $this->db->eine('SELECT ablauf_am FROM benutzer WHERE id = ?', [$kontoId]);
        if ($ablauf === null || $ablauf['ablauf_am'] === null) {
            return null; // von der Administration angelegt
        }
        return array_map('intval', $this->db->spalte(
            'SELECT DISTINCT a.turnier_id FROM konto_anmeldung k JOIN anmeldung a ON a.uuid = k.anmeldung_uuid WHERE k.benutzer_id = ? ORDER BY a.turnier_id',
            [$kontoId],
        ));
    }

    /** Darf der Zugang in diesem Turnier melden bzw. das Portal nutzen? (Noch an kein Turnier gebunden: ja.) */
    public function erlaubt(?int $kontoId, int $turnierId): bool
    {
        $liste = $this->turniere($kontoId);
        return $liste === null || $liste === [] || in_array($turnierId, $liste, true);
    }

    /** Namen der Turniere, an die der Zugang gebunden ist (für Hinweise). */
    public function namen(?int $kontoId): string
    {
        $liste = $this->turniere($kontoId) ?? [];
        if ($liste === []) {
            return '';
        }
        return implode(', ', $this->db->spalte('SELECT name FROM turnier WHERE id IN (' . implode(',', $liste) . ') ORDER BY datum'));
    }
}
