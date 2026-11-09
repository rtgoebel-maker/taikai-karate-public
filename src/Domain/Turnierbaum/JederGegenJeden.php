<?php

declare(strict_types=1);

namespace Wettkampf\Domain\Turnierbaum;

/**
 * Paarungen „jeder gegen jeden“ nach dem Rundenverfahren (Kreismethode):
 * Pro Runde kämpft jeder höchstens einmal; bei ungerader Zahl hat je
 * Runde einer Pause. So bekommt niemand zwei Kämpfe direkt hintereinander,
 * soweit möglich.
 */
final class JederGegenJeden
{
    /**
     * @param list<int> $startIds
     * @return list<array{runde: int, position: int, aka: int, shiro: int}>
     */
    public static function paarungen(array $startIds): array
    {
        $teilnehmer = $startIds;
        if (count($teilnehmer) < 2) {
            return [];
        }
        if (count($teilnehmer) % 2 === 1) {
            $teilnehmer[] = null; // Pause
        }
        $n = count($teilnehmer);
        $paarungen = [];
        for ($runde = 1; $runde < $n; $runde++) {
            $position = 0;
            for ($i = 0; $i < $n / 2; $i++) {
                $a = $teilnehmer[$i];
                $b = $teilnehmer[$n - 1 - $i];
                if ($a === null || $b === null) {
                    continue;
                }
                // Seiten abwechseln, damit niemand immer Aka ist
                [$aka, $shiro] = ($runde + $i) % 2 === 0 ? [$a, $b] : [$b, $a];
                $paarungen[] = ['runde' => $runde, 'position' => ++$position, 'aka' => $aka, 'shiro' => $shiro];
            }
            // Kreismethode: erstes Element fest, Rest rotiert
            $fest = array_shift($teilnehmer);
            array_unshift($teilnehmer, array_pop($teilnehmer));
            array_unshift($teilnehmer, $fest);
        }
        return $paarungen;
    }
}
