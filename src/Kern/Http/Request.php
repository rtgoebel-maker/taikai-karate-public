<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, string> $headers  Kleinbuchstaben-Schlüssel
     */
    public function __construct(
        public readonly string $methode,
        public readonly string $pfad,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $files = [],
        public readonly array $headers = [],
        public readonly string $body = '',
    ) {}

    public function get(string $name, mixed $standard = null): mixed
    {
        return $this->query[$name] ?? $standard;
    }

    public function post(string $name, mixed $standard = null): mixed
    {
        return $this->post[$name] ?? $standard;
    }

    public function text(string $name, string $standard = ''): string
    {
        $wert = $this->post[$name] ?? $this->query[$name] ?? $standard;
        return is_string($wert) ? trim($wert) : $standard;
    }

    public function zahl(string $name, ?int $standard = null): ?int
    {
        $wert = $this->post[$name] ?? $this->query[$name] ?? null;
        if ($wert === null || $wert === '' || !is_numeric($wert)) {
            return $standard;
        }
        return (int) $wert;
    }

    public function istJson(): bool
    {
        return str_contains($this->headers['content-type'] ?? '', 'application/json');
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        $daten = json_decode($this->body, true);
        return is_array($daten) ? $daten : [];
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
