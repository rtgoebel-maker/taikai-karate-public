<?php

declare(strict_types=1);

namespace Wettkampf\Domain\Klasse;

/**
 * Findet die passende(n) Klasse(n) für einen Kandidaten.
 */
final class Zuordnung
{
    /** @param list<KlassenRegel> $regeln */
    public function __construct(private readonly array $regeln) {}

    /** @return list<KlassenRegel> alle passenden Klassen */
    public function kandidaten(Kandidat $k): array
    {
        return array_values(array_filter($this->regeln, static fn(KlassenRegel $r) => $r->passt($k)));
    }

    /**
     * Ergebnis der Zuordnung:
     *  - 'eindeutig'  genau eine Klasse passt
     *  - 'mehrdeutig' mehrere Klassen passen (z. B. Jiyu-Ippon oder Jiyu-Kumite) → manuell wählen
     *  - 'keine'      keine Klasse passt
     *
     * @return array{status: string, klassen: list<KlassenRegel>}
     */
    public function zuordnen(Kandidat $k): array
    {
        $passend = $this->kandidaten($k);
        return [
            'status' => match (count($passend)) {
                0 => 'keine',
                1 => 'eindeutig',
                default => 'mehrdeutig',
            },
            'klassen' => $passend,
        ];
    }
}
