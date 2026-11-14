<?php

declare(strict_types=1);

namespace Wettkampf\Datenschutz;

use Dompdf\Dompdf;
use Dompdf\Options;
use Wettkampf\Domain\FachFehler;

/**
 * HTML → PDF mit dompdf (LGPL-2.1). Sicher eingestellt: keine Dateien oder Adressen von außen nachladen (SSRF, OWASP A10),
 * kein eingebettetes PHP. Schrift DejaVu Sans (freie Lizenz, mit dompdf ausgeliefert) – auch für € und ✓.
 */
final class PdfBeleg
{
    public static function verfuegbar(): bool
    {
        return extension_loaded('dom') && class_exists(Dompdf::class);
    }

    public static function erzeugen(string $html): string
    {
        if (!self::verfuegbar()) {
            throw new FachFehler('PDF-Erzeugung ist auf diesem Server nicht verfügbar (PHP-Erweiterung dom fehlt, unter Linux: sudo apt install php-xml).');
        }
        $optionen = new Options();
        $optionen->setIsRemoteEnabled(false);
        $optionen->setIsPhpEnabled(false);
        $optionen->setIsJavascriptEnabled(false);
        $optionen->setDefaultFont('DejaVu Sans');
        $optionen->setTempDir(sys_get_temp_dir());
        $optionen->setChroot([dirname(__DIR__, 2) . '/vendor/dompdf/dompdf']);
        $pdf = new Dompdf($optionen);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        return (string) $pdf->output();
    }
}
