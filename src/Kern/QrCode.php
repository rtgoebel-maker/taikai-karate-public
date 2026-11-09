<?php

declare(strict_types=1);

namespace Wettkampf\Kern;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as Erzeuger;
use chillerlan\QRCode\QROptions;

/**
 * QR-Code als eingebettetes SVG, auf dem Server erzeugt (chillerlan/php-qrcode) – kein externer Dienst, funktioniert offline.
 * Das Ergebnis enthält nur Pfade aus der Bibliothek; der Inhalt steckt codiert im Muster, nicht als Text im Markup.
 */
final class QrCode
{
    public static function svg(string $inhalt): string
    {
        $optionen = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'eccLevel' => EccLevel::M,
            'outputBase64' => false,
            'addQuietzone' => true,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
            'svgUseFillAttributes' => true,
            'cssClass' => 'qr-code',
        ]);
        return (new Erzeuger($optionen))->render($inhalt);
    }
}
