<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http;

final class Url
{
    private static string $basis = '';

    public static function setzeBasis(string $basis): void
    {
        self::$basis = rtrim($basis, '/');
    }

    public static function basis(): string
    {
        return self::$basis;
    }

    /**
     * Ermittelt den Basis-Pfad aus SCRIPT_NAME, z. B. '/wettkampf/public'
     * bei Installation in einem Unterordner.
     */
    public static function erkennen(): string
    {
        $skript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        // dirname() liefert unter Windows „\“ für das Wurzelverzeichnis
        $verzeichnis = rtrim(str_replace('\\', '/', dirname($skript)), '/');
        if ($verzeichnis === '.' || $verzeichnis === '') {
            return '';
        }
        // Shared Hosting ohne eigenes Document-Root: Die .htaccess im Hauptordner
        // leitet intern nach public/ um, die URLs enthalten „/public“ aber nicht.
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (str_ends_with($verzeichnis, '/public') && !str_starts_with($uri, $verzeichnis)) {
            $verzeichnis = substr($verzeichnis, 0, -strlen('/public'));
        }
        return $verzeichnis;
    }

    /**
     * Adresse innerhalb dieser Installation. Führende / und \ werden entfernt: „//evil.example“ oder „/\evil.example“
     * würden Browser sonst als fremde Seite lesen (offene Weiterleitung).
     *
     * @param array<string, scalar|null> $query
     * @psalm-taint-escape header
     */
    public static function zu(string $pfad, array $query = []): string
    {
        $query = array_filter($query, static fn($w) => $w !== null && $w !== '');
        return self::$basis . '/' . ltrim($pfad, '/\\') . ($query ? '?' . http_build_query($query) : '');
    }
}
