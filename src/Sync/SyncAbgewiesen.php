<?php

declare(strict_types=1);

namespace Wettkampf\Sync;

use RuntimeException;

/**
 * Sendung der Halle abgewiesen. Status und Grund gehen an die Halle (ohne Details zum Inhalt):
 * 400 unlesbar, 401 Signatur, 409 Übergabe nicht (mehr) gültig bzw. wiederholt, 413 zu groß.
 */
final class SyncAbgewiesen extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $grund, string $text)
    {
        parent::__construct($text);
    }
}
