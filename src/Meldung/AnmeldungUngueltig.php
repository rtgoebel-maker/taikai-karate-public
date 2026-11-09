<?php

declare(strict_types=1);

namespace Wettkampf\Meldung;

use Wettkampf\Domain\FachFehler;

/** Anmeldung mit Fehlern – enthält alle Meldungen, damit das Formular sie gesammelt anzeigt. */
final class AnmeldungUngueltig extends FachFehler
{
    /** @param list<string> $fehler */
    public function __construct(public readonly array $fehler)
    {
        parent::__construct(implode(' ', $fehler));
    }
}
