<?php

declare(strict_types=1);

namespace Wettkampf\Domain\Klasse;

/**
 * Bestimmt Wertungssystem, Kumite-Form und erlaubte Kata für eine Runde
 * unter Berücksichtigung der Abweichungen „ab letzte 8 / letzte 4 / Finale“.
 */
final class Rundenregel
{
    /**
     * @param array<string, mixed> $klasse
     * @param list<array<string, mixed>> $regeln  Zeilen aus klasse_regel
     * @param int $verbleibend  Starter, die in dieser Runde (klassenweit) noch im Wettbewerb sind
     * @param bool $istFinale   Finale, Kampf um Platz 3 oder Finalrunde
     * @param string $basisWertung Wertung der Phase (Vorrunde oder Finale)
     * @return array{system: string, kumite_form: ?string, kata_liste: ?string}
     */
    public static function fuer(array $klasse, array $regeln, int $verbleibend, bool $istFinale, string $basisWertung): array
    {
        $ergebnis = [
            'system' => $basisWertung,
            'kumite_form' => $klasse['kumite_form'] ?: null,
            'kata_liste' => $klasse['erlaubte_kata'] ?: null,
        ];
        // Reihenfolge: allgemeinere Regeln zuerst, damit spätere (engere) gewinnen
        $rang = ['letzte8' => 1, 'letzte4' => 2, 'finale' => 3];
        usort($regeln, static fn($a, $b) => ($rang[$a['ab_runde']] ?? 9) <=> ($rang[$b['ab_runde']] ?? 9));

        foreach ($regeln as $r) {
            $greift = match ($r['ab_runde']) {
                'letzte8' => $verbleibend <= 8,
                'letzte4' => $verbleibend <= 4,
                'finale' => $istFinale,
                default => false,
            };
            if (!$greift) {
                continue;
            }
            if (!empty($r['kumite_form'])) {
                $ergebnis['kumite_form'] = $r['kumite_form'];
            }
            if ($r['erlaubte_kata'] !== null) {
                $ergebnis['kata_liste'] = $r['erlaubte_kata'] === '' ? null : $r['erlaubte_kata'];
            }
            if (!empty($r['wertung'])) {
                $ergebnis['system'] = $r['wertung'];
            }
        }
        return $ergebnis;
    }
}
