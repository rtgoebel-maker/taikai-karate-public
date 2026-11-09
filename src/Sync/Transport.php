<?php

declare(strict_types=1);

namespace Wettkampf\Sync;

/** HTTP-Aufruf vom Hallen-Laptop zum Online-Server; in Tests durch einen direkten Aufruf einer zweiten App ersetzt. */
interface Transport
{
    /**
     * @param array<string, string> $header
     * @return array{status: int, body: string}
     */
    public function post(string $url, string $body, array $header = []): array;
}
