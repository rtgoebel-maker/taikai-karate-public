<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Mail;

/**
 * „Versand“ in Dateien (data/mail/*.txt) – zum Ausprobieren und Testen ohne Postfach
 * ('transport' => 'datei' in config.php). Nicht für den Betrieb: es geht nichts hinaus.
 */
final class DateiTransport implements Transport
{
    public function __construct(private readonly string $ordner) {}

    public function senden(string $empfaenger, string $betreff, string $text, array $anhaenge = []): void
    {
        if (!is_dir($this->ordner) && !mkdir($this->ordner, 0775, true) && !is_dir($this->ordner)) {
            throw new \RuntimeException('Ordner für Mails kann nicht angelegt werden.');
        }
        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.txt';
        file_put_contents($this->ordner . '/' . $name, "An: $empfaenger\nBetreff: $betreff\n\n$text");
        foreach ($anhaenge as $i => $a) {
            file_put_contents($this->ordner . '/' . substr($name, 0, -4) . '_anhang' . ($i + 1) . '.' . self::endung($a['name']), $a['daten']);
        }
    }

    /** @psalm-taint-escape file nur a-z und 0-9 */
    private static function endung(string $dateiname): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($dateiname, PATHINFO_EXTENSION))) ?: 'bin';
    }
}
