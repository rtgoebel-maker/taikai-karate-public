<?php

declare(strict_types=1);

namespace Wettkampf\Domain\Klasse;

/**
 * Wer einer Klasse zugeordnet werden soll: Einzelstarter oder Team.
 * Bei Teams gilt: Alter = ältestes Mitglied (DJKB-Sportordnung C.2.1),
 * Graduierung = niedrigste im Team, Geschlecht = 'mixed' bei gemischten Teams.
 */
final class Kandidat
{
    public function __construct(
        public readonly string $disziplin,
        public readonly string $geschlecht,
        public readonly ?int $alter,
        public readonly int $graduierung,
        public readonly ?string $kumiteForm = null,
    ) {}

    /**
     * @param list<array{geschlecht: string, alter: int|null, graduierung: int}> $mitglieder
     */
    public static function team(string $disziplin, array $mitglieder): self
    {
        if ($mitglieder === []) {
            throw new \InvalidArgumentException('Ein Team ohne Mitglieder kann nicht zugeordnet werden.');
        }
        $geschlechter = array_unique(array_column($mitglieder, 'geschlecht'));
        $alter = array_filter(array_column($mitglieder, 'alter'), static fn($a) => $a !== null);
        return new self(
            $disziplin,
            count($geschlechter) === 1 ? $geschlechter[0] : 'mixed',
            $alter === [] ? null : max($alter),
            min(array_column($mitglieder, 'graduierung')),
        );
    }
}
