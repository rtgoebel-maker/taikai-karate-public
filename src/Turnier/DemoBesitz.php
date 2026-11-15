<?php

declare(strict_types=1);

namespace Wettkampf\Turnier;

use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;

/**
 * Demo-Turniere eines Demo-Zugangs (turnier.einstellungen):
 *
 * - 'demo_besitzer': Konto, für das bzw. von dem das Turnier angelegt wurde (Standard-Demos, eigene Turniere, Importe).
 *   Die Administration sieht es in der Turnierliste; der Demo-Zugang leitet das Turnier (turnier_rolle).
 * - 'demo_loeschen_ab': zum Löschen vorgemerkt – gesetzt, sobald das Konto kein aktiver Demo-Zugang mehr ist
 *   (entzogen, deaktiviert, gelöscht), aufgehoben, wenn es wieder einer wird. Nach FRIST_STUNDEN wird gelöscht,
 *   damit sich die Installation nicht mit Schulungsturnieren füllt.
 */
final class DemoBesitz
{
    public const BESITZER = 'demo_besitzer';
    public const LOESCHEN_AB = 'demo_loeschen_ab';
    public const FRIST_STUNDEN = 72;
    private const MERKER = 'demo_loeschen_lauf';

    public function __construct(private readonly Database $db) {}

    /** @param array<string, mixed> $turnier */
    public static function besitzer(array $turnier): ?int
    {
        $wert = self::einstellungen($turnier)[self::BESITZER] ?? null;
        return is_int($wert) && $wert > 0 ? $wert : null;
    }

    /** @param array<string, mixed> $turnier */
    public static function loeschenAb(array $turnier): ?string
    {
        $wert = self::einstellungen($turnier)[self::LOESCHEN_AB] ?? null;
        return is_string($wert) && $wert !== '' ? $wert : null;
    }

    public function besitzerSetzen(int $turnierId, int $benutzerId): void
    {
        $this->schreiben($turnierId, [self::BESITZER => $benutzerId]);
        // Demo eines Kontos: Tablets nur per QR-Code – die festen Demo-PINs öffneten sonst jedes Demo-Turnier
        $this->db->update('turnier', ['tisch_pin_erlaubt' => 0], ['id' => $turnierId]);
    }

    /**
     * Turniere eines Kontos zum Löschen vormerken bzw. die Vormerkung aufheben.
     *
     * @return int Anzahl betroffener Turniere
     */
    public function vormerken(int $benutzerId, bool $loeschen, ?int $jetzt = null): int
    {
        $ab = date('Y-m-d H:i:s', ($jetzt ?? time()) + self::FRIST_STUNDEN * 3600);
        $anzahl = 0;
        foreach ($this->turniereVon($benutzerId) as $t) {
            $vorgemerkt = self::loeschenAb($t) !== null;
            if ($loeschen && !$vorgemerkt) {
                $this->schreiben((int) $t['id'], [self::LOESCHEN_AB => $ab]);
                (new Protokoll($this->db))->eintragen((int) $t['id'], 'turnier', (int) $t['id'], 'demo_loeschen_vorgemerkt', ['ab' => $ab]);
                $anzahl++;
            } elseif (!$loeschen && $vorgemerkt) {
                $this->schreiben((int) $t['id'], [self::LOESCHEN_AB => null]);
                (new Protokoll($this->db))->eintragen((int) $t['id'], 'turnier', (int) $t['id'], 'demo_loeschen_aufgehoben');
                $anzahl++;
            }
        }
        return $anzahl;
    }

    /**
     * Vorgemerkte Turniere nach Ablauf der Frist löschen – nach einer Anfrage, höchstens einmal je Stunde.
     *
     * @return int|null gelöschte Turniere, null = noch nicht wieder dran
     */
    public function faelligeLoeschen(?int $jetzt = null): ?int
    {
        $jetzt ??= time();
        $system = new SystemEinstellungen($this->db);
        if ($jetzt - (int) $system->wert(self::MERKER, '0') < 3600) {
            return null;
        }
        $system->setzen(self::MERKER, (string) $jetzt);
        $grenze = date('Y-m-d H:i:s', $jetzt);
        $anzahl = 0;
        foreach ($this->db->alle('SELECT * FROM turnier WHERE einstellungen LIKE ? ORDER BY id', ['%"' . self::LOESCHEN_AB . '":"%']) as $t) {
            $ab = self::loeschenAb($t);
            if ($ab !== null && $ab <= $grenze) {
                (new StammdatenService($this->db))->turnierLoeschen((int) $t['id']);
                (new Protokoll($this->db))->eintragen(null, 'turnier', (int) $t['id'], 'demo_geloescht', ['name' => $t['name'], 'besitzer' => self::besitzer($t)]);
                $anzahl++;
            }
        }
        return $anzahl;
    }

    /** @return list<array<string, mixed>> Turniere dieses Kontos */
    public function turniereVon(int $benutzerId): array
    {
        return array_values(array_filter(
            $this->db->alle('SELECT * FROM turnier WHERE einstellungen LIKE ? ORDER BY id', ['%"' . self::BESITZER . '":%']),
            static fn(array $t): bool => self::besitzer($t) === $benutzerId,
        ));
    }

    /**
     * @param array<string, mixed> $turnier
     * @return array<string, mixed>
     */
    private static function einstellungen(array $turnier): array
    {
        $e = json_decode((string) ($turnier['einstellungen'] ?? ''), true);
        return is_array($e) ? $e : [];
    }

    /** @param array<string, int|string|null> $werte null = Schlüssel entfernen */
    private function schreiben(int $turnierId, array $werte): void
    {
        $e = json_decode((string) $this->db->wert('SELECT einstellungen FROM turnier WHERE id = ?', [$turnierId]), true);
        $e = is_array($e) ? $e : [];
        foreach ($werte as $k => $v) {
            if ($v === null) {
                unset($e[$k]);
            } else {
                $e[$k] = $v;
            }
        }
        $this->db->update('turnier', ['einstellungen' => json_encode($e, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)], ['id' => $turnierId]);
    }
}
