<?php

declare(strict_types=1);

namespace Wettkampf\Domain;

use DateTimeImmutable;

final class Alter
{
    /** Alter in vollen Jahren am Stichtag (Turniertag). */
    public static function amStichtag(string $geburtsdatum, string $stichtag): int
    {
        $geburt = new DateTimeImmutable($geburtsdatum);
        $tag = new DateTimeImmutable($stichtag);
        if ($geburt > $tag) {
            throw new \InvalidArgumentException('Geburtsdatum liegt nach dem Stichtag.');
        }
        return $geburt->diff($tag)->y;
    }

    /**
     * Liest ein Datum in den Formaten 03.05.2012, 3.5.2012, 2012-05-03 oder
     * als Excel-Seriennummer. Gibt 'YYYY-MM-DD' oder null zurück.
     */
    public static function datumParsen(string $text): ?string
    {
        $t = trim($text);
        if ($t === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})$/', $t, $m)) {
            $jahr = (int) $m[3];
            if ($jahr < 100) {
                $jahr += $jahr > (int) date('y') ? 1900 : 2000;
            }
            return checkdate((int) $m[2], (int) $m[1], $jahr) ? sprintf('%04d-%02d-%02d', $jahr, $m[2], $m[1]) : null;
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $t, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
        }
        // Excel-Seriennummer (Tage seit 30.12.1899), plausibler Bereich 1900–2100
        if (preg_match('/^\d{4,5}(\.\d+)?$/', $t) && (float) $t > 1 && (float) $t < 73051) {
            return (new DateTimeImmutable('1899-12-30'))->modify('+' . (int) $t . ' days')->format('Y-m-d');
        }
        return null;
    }
}
