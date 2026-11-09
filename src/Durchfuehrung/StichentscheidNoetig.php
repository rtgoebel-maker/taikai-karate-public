<?php

declare(strict_types=1);

namespace Wettkampf\Durchfuehrung;

use Wettkampf\Domain\FachFehler;

/**
 * Gleichstand an einer entscheidenden Stelle (z. B. Grenze zum Finale),
 * den die Wettkampfleitung per Stichentscheid auflösen muss.
 */
final class StichentscheidNoetig extends FachFehler
{
    /** @param list<int> $startIds */
    public function __construct(public readonly array $startIds)
    {
        parent::__construct('Gleichstand – Stichentscheid durch die Wettkampfleitung erforderlich.');
    }
}
