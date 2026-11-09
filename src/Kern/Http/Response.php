<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {}

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $daten, int $status = 200): self
    {
        return new self(
            json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
        );
    }

    public static function weiterleiten(string $ziel): self
    {
        return new self('', 303, ['Location' => $ziel]);
    }

    public static function download(string $inhalt, string $dateiname, string $typ): self
    {
        return new self($inhalt, 200, [
            'Content-Type' => $typ,
            'Content-Disposition' => 'attachment; filename="' . addslashes($dateiname) . '"',
        ]);
    }

    /**
     * Sicherheits-Header (OWASP A02), gesetzt von Middleware\SicherheitsHeader, sofern die Antwort sie nicht selbst setzt. Die CSP lässt
     * nur Skripte aus eigenen Dateien zu (keine eingebetteten Skripte oder on…-Handler, siehe verhalten.js und
     * tests/Http/CspTest.php), Styles auch eingebettet ('unsafe-inline' wegen der style-Attribute), Bilder
     * auch als data:/blob: (Logos, Screenshots), keine fremden Frames oder Plugins.
     */
    public const SICHERHEIT = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'same-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: blob:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'self'; "
            . "form-action 'self'; frame-ancestors 'self'",
    ];
}
