<?php

declare(strict_types=1);

namespace Wettkampf\Sync;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Instanz;

/**
 * Wer ein Turnier führt, also schreiben darf (Schritt 9c, Konzept Kap. 4 „genau ein Schreiber“).
 *
 * turnier.fuehrung_instanz leer = keine Übergabe, diese Installation führt. Sonst führt nur die Installation mit
 * dieser Instanz-ID: online nach der Übergabe die Halle, in der Halle sie selbst, nach der Rückgabe wieder Online.
 */
final class Fuehrung
{
    public function __construct(private readonly Database $db, private readonly Instanz $instanz) {}

    /** @param array<string, mixed> $turnier Zeile aus turnier */
    public function fuehrt(array $turnier): bool
    {
        $fuehrend = $turnier['fuehrung_instanz'] ?? null;
        return $fuehrend === null || $fuehrend === '' || hash_equals($this->instanz->id(), (string) $fuehrend);
    }

    /** Wirft, wenn diese Installation das Turnier nicht führt (unbekannte Turniere prüft der Controller). */
    public function pruefen(int $turnierId): void
    {
        $turnier = $this->db->eine('SELECT fuehrung_instanz, fuehrung_name, fuehrung_seit, fuehrung_stand FROM turnier WHERE id = ?', [$turnierId]);
        if ($turnier !== null && !$this->fuehrt($turnier)) {
            throw new FachFehler(self::text($turnier));
        }
    }

    /** @param array<string, mixed> $turnier */
    public static function text(array $turnier): string
    {
        $wo = ($turnier['fuehrung_name'] ?? '') !== '' ? '„' . $turnier['fuehrung_name'] . '“' : t('einer anderen Installation');
        $text = ($turnier['fuehrung_seit'] ?? '') !== ''
            ? t('Nur lesend: Das Turnier wird seit {seit} auf {wo} geführt. Änderungen bitte dort vornehmen.', ['seit' => date('d.m.Y H:i', (int) strtotime((string) $turnier['fuehrung_seit'])), 'wo' => $wo])
            : t('Nur lesend: Das Turnier wird auf {wo} geführt. Änderungen bitte dort vornehmen.', ['wo' => $wo]);
        $stand = ($turnier['fuehrung_stand'] ?? '') !== '' ? ' ' . t('Angezeigt wird der Stand von {zeit} Uhr.', ['zeit' => date('d.m.Y H:i', (int) strtotime((string) $turnier['fuehrung_stand']))]) : '';
        return $text . $stand;
    }
}
