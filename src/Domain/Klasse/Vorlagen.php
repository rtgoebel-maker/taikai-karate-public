<?php

declare(strict_types=1);

namespace Wettkampf\Domain\Klasse;

use Wettkampf\Domain\Graduierung as G;

/**
 * Klassen-Vorlagen. Jede Vorlage liefert Datensätze für die Tabelle
 * klasse; 'regeln' enthält rundenabhängige Abweichungen (klasse_regel).
 */
final class Vorlagen
{
    private const HEIAN_1_3 = 'Heian Shodan, Heian Nidan, Heian Sandan';
    private const HEIAN_1_5_TEKKI = 'Heian Shodan, Heian Nidan, Heian Sandan, Heian Yondan, Heian Godan, Tekki Shodan';

    /** @return array<string, string> Schlüssel => Bezeichnung */
    public static function liste(): array
    {
        return [
            'gruppen_ap' => 'Gruppenschema A–P (Einzel A1–G4, Teams H–P; Kata im Punktesystem)',
            'djkb' => 'DJKB-Standard (Sportordnung, Deutsche Meisterschaft)',
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function laden(string $schluessel): array
    {
        return match ($schluessel) {
            'gruppen_ap' => self::gruppenAp(),
            'djkb' => self::djkb(),
            default => throw new \InvalidArgumentException("Unbekannte Vorlage: $schluessel"),
        };
    }

    /**
     * Gruppenschema A–P einer typischen Ausschreibung:
     * Kata Vorrunde und Finale im Punktesystem (Finalkata frei),
     * Kumite-Form je Gruppe, D2A/D2B und D4A/D4B schließen sich gegenseitig aus.
     *
     * @return list<array<string, mixed>>
     */
    private static function gruppenAp(): array
    {
        $kata = static fn(string $code, string $g, int $von, ?int $bis, ?int $gradMin, ?int $gradMax, string $katas) => [
            'code' => $code,
            'bezeichnung' => 'Kata ' . ($g === 'w' ? ($von >= 18 ? 'Frauen' : 'Mädchen') : ($von >= 18 ? 'Männer' : 'Jungen'))
                . ' ' . self::alterText($von, $bis) . ' · ' . G::bereichText($gradMin, $gradMax),
            'disziplin' => 'kata', 'kumite_form' => null, 'geschlecht' => $g,
            'alter_min' => $von, 'alter_max' => $bis, 'grad_min' => $gradMin, 'grad_max' => $gradMax,
            'erlaubte_kata' => $katas,
            'modus' => 'punkte', 'wertung' => 'punkte', 'wertung_finale' => 'punkte', 'finalisten' => 4,
            'regeln' => [['ab_runde' => 'finale', 'erlaubte_kata' => '', 'wertung' => 'punkte']],
        ];
        $kumite = static fn(string $code, string $g, int $von, ?int $bis, ?int $gradMin, ?int $gradMax, string $form, ?string $exklusiv = null) => [
            'code' => $code,
            'bezeichnung' => self::kumiteName($form) . ' ' . ($g === 'w' ? ($von >= 18 ? 'Frauen' : 'Mädchen') : ($von >= 18 ? 'Männer' : 'Jungen'))
                . ' ' . self::alterText($von, $bis) . ' · ' . G::bereichText($gradMin, $gradMax),
            'disziplin' => 'kumite', 'kumite_form' => $form, 'geschlecht' => $g,
            'alter_min' => $von, 'alter_max' => $bis, 'grad_min' => $gradMin, 'grad_max' => $gradMax,
            'exklusiv_gruppe' => $exklusiv,
            'modus' => 'auto', 'wertung' => $form === 'jiyu' ? 'kumite' : 'flaggen',
        ];

        $k7 = G::kyu(7);
        $k6 = G::kyu(6);
        $k4 = G::kyu(4);
        $k3 = G::kyu(3);

        $klassen = [];
        foreach ([['A', 8, 9], ['B', 10, 11]] as [$band, $von, $bis]) {
            $klassen[] = $kata("{$band}1", 'w', $von, $bis, $k7, null, self::HEIAN_1_3);
            $klassen[] = $kumite("{$band}2", 'w', $von, $bis, $k7, null, 'kihon_ippon');
            $klassen[] = $kata("{$band}3", 'm', $von, $bis, $k7, null, self::HEIAN_1_3);
            $klassen[] = $kumite("{$band}4", 'm', $von, $bis, $k7, null, 'kihon_ippon');
        }
        $klassen[] = $kata('C1', 'w', 12, 14, $k6, $k4, self::HEIAN_1_3);
        $klassen[] = $kumite('C2', 'w', 12, 14, $k6, $k4, 'kihon_ippon');
        $klassen[] = $kata('C3', 'm', 12, 14, $k6, $k4, self::HEIAN_1_3);
        $klassen[] = $kumite('C4', 'm', 12, 14, $k6, $k4, 'kihon_ippon');
        $klassen[] = $kata('D1', 'w', 12, 14, $k3, null, self::HEIAN_1_5_TEKKI);
        $klassen[] = $kumite('D2A', 'w', 12, 14, $k3, null, 'jiyu_ippon', 'D2');
        $klassen[] = $kumite('D2B', 'w', 12, 14, $k3, null, 'jiyu', 'D2');
        $klassen[] = $kata('D3', 'm', 12, 14, $k3, null, self::HEIAN_1_5_TEKKI);
        $klassen[] = $kumite('D4A', 'm', 12, 14, $k3, null, 'jiyu_ippon', 'D4');
        $klassen[] = $kumite('D4B', 'm', 12, 14, $k3, null, 'jiyu', 'D4');
        $klassen[] = $kata('E1', 'w', 15, 17, $k6, $k4, self::HEIAN_1_3);
        $klassen[] = $kumite('E2', 'w', 15, 17, $k6, $k4, 'jiyu_ippon');
        $klassen[] = $kata('E3', 'm', 15, 17, $k6, $k4, self::HEIAN_1_3);
        $klassen[] = $kumite('E4', 'm', 15, 17, $k6, $k4, 'jiyu_ippon');
        foreach ([['F', 15, 17], ['G', 18, null]] as [$band, $von, $bis]) {
            $klassen[] = $kata("{$band}1", 'w', $von, $bis, $k3, null, self::HEIAN_1_5_TEKKI);
            $klassen[] = $kumite("{$band}2", 'w', $von, $bis, $k3, null, 'jiyu');
            $klassen[] = $kata("{$band}3", 'm', $von, $bis, $k3, null, self::HEIAN_1_5_TEKKI);
            $klassen[] = $kumite("{$band}4", 'm', $von, $bis, $k3, null, 'jiyu');
        }

        // Mannschaften: Kata gemischt (Vor- und Finalrunde Kata frei), Kumite (Jiyu-Kumite)
        foreach ([['H', 8, 9, $k7], ['I', 10, 11, $k7], ['J', 12, 14, $k7], ['K', 15, 17, G::kyu(6)], ['L', 18, null, G::kyu(6)]] as [$code, $von, $bis, $grad]) {
            $klassen[] = [
                'code' => $code,
                'bezeichnung' => 'Kata-Team gemischt ' . self::alterText($von, $bis) . ' · ' . G::bereichText($grad, null),
                'disziplin' => 'kata_team', 'geschlecht' => 'mixed',
                'alter_min' => $von, 'alter_max' => $bis, 'grad_min' => $grad, 'grad_max' => null,
                'erlaubte_kata' => '', 'modus' => 'punkte', 'wertung' => 'punkte', 'wertung_finale' => 'punkte', 'finalisten' => 4,
            ];
        }
        foreach ([['M', 'w', 15, 17], ['N', 'm', 15, 17], ['O', 'w', 18, null], ['P', 'm', 18, null]] as [$code, $g, $von, $bis]) {
            $klassen[] = [
                'code' => $code,
                'bezeichnung' => 'Kumite-Team ' . ($g === 'w' ? ($von >= 18 ? 'Frauen' : 'Mädchen') : ($von >= 18 ? 'Männer' : 'Jungen'))
                    . ' ' . self::alterText($von, $bis) . ' · ab 3. Kyu',
                'disziplin' => 'kumite_team', 'kumite_form' => 'jiyu', 'geschlecht' => $g,
                'alter_min' => $von, 'alter_max' => $bis, 'grad_min' => $k3, 'grad_max' => null,
                'modus' => 'auto', 'wertung' => 'kumite',
            ];
        }
        return self::nummerieren($klassen);
    }

    /**
     * Altersklassen und Disziplinen der Deutschen Meisterschaft laut
     * DJKB-Sportordnung (Abschnitt C und D). Kata-Vorrunde mit festgelegter
     * Heian-Auswahl im Flaggen-K.-o., Finale (letzte 4) Tokui im Punktesystem.
     *
     * @return list<array<string, mixed>>
     */
    private static function djkb(): array
    {
        $heian = static fn(int $bis) => implode(', ', array_slice(
            ['Heian Shodan', 'Heian Nidan', 'Heian Sandan', 'Heian Yondan', 'Heian Godan'],
            0,
            $bis,
        ));
        // [Kürzel, Name, von, bis, gradMin, gradMax, Kumite-Form, Heian bis, Regel „letzte 8 Jiyu-Kumite“]
        $gruppen = [
            ['S1', 'Schüler 1', 8, 10, null, G::kyu(6), 'kihon_ippon', 2, false],
            ['S1', 'Schüler 1', 8, 10, G::kyu(5), null, 'jiyu_ippon', 3, false],
            ['S2', 'Schüler 2', 11, 12, null, G::kyu(5), 'kihon_ippon', 3, false],
            ['S2', 'Schüler 2', 11, 12, G::kyu(4), null, 'jiyu_ippon', 4, true],
            ['S3', 'Schüler 3', 13, 14, G::kyu(6), G::kyu(5), 'jiyu_ippon', 4, false],
            ['S3', 'Schüler 3', 13, 14, G::kyu(4), null, 'jiyu', 5, false],
            ['J1', 'Jugend 1', 15, 16, G::kyu(4), null, 'jiyu', 5, false],
            ['J2', 'Jugend 2', 17, 18, G::kyu(4), null, 'jiyu', 5, false],
            ['JUN', 'Junioren', 19, 20, G::kyu(4), null, 'jiyu', 5, false],
            ['AKT', 'Aktive', 21, null, G::kyu(4), null, 'jiyu', 5, false],
        ];
        $klassen = [];
        $nr = [];
        foreach ($gruppen as [$kuerzel, $name, $von, $bis, $gradMin, $gradMax, $form, $heianBis, $letzte8]) {
            foreach (['w', 'm'] as $g) {
                $n = $nr["$kuerzel$g"] = ($nr["$kuerzel$g"] ?? 0) + 1;
                $suffix = strtoupper($g === 'w' ? 'W' : 'M');
                $grad = G::bereichText($gradMin, $gradMax);
                $klassen[] = [
                    'code' => "$kuerzel-K$n $suffix",
                    'bezeichnung' => "Kata $name " . self::alterText($von, $bis) . " · $grad",
                    'disziplin' => 'kata', 'geschlecht' => $g,
                    'alter_min' => $von, 'alter_max' => $bis, 'grad_min' => $gradMin, 'grad_max' => $gradMax,
                    'erlaubte_kata' => $heian($heianBis),
                    'modus' => 'pools', 'anzahl_pools' => 1, 'aufsteiger_pro_pool' => 4,
                    'finale_art' => 'punkte', 'wertung' => 'flaggen', 'wertung_finale' => 'punkte',
                    'regeln' => [['ab_runde' => 'finale', 'erlaubte_kata' => '', 'wertung' => 'punkte']],
                ];
                $klassen[] = [
                    'code' => "$kuerzel-KU$n $suffix",
                    'bezeichnung' => self::kumiteName($form) . " $name " . self::alterText($von, $bis) . " · $grad",
                    'disziplin' => 'kumite', 'kumite_form' => $form, 'geschlecht' => $g,
                    'alter_min' => $von, 'alter_max' => $bis, 'grad_min' => $gradMin, 'grad_max' => $gradMax,
                    'modus' => 'auto', 'wertung' => $form === 'jiyu' ? 'kumite' : 'flaggen',
                    'regeln' => $letzte8 ? [['ab_runde' => 'letzte8', 'kumite_form' => 'jiyu', 'wertung' => 'kumite']] : [],
                ];
            }
        }
        return self::nummerieren($klassen);
    }

    private static function kumiteName(string $form): string
    {
        return match ($form) {
            'kihon_ippon' => 'Kihon-Ippon-Kumite',
            'jiyu_ippon' => 'Jiyu-Ippon-Kumite',
            default => 'Jiyu-Kumite',
        };
    }

    private static function alterText(int $von, ?int $bis): string
    {
        return $bis === null ? "ab $von Jahre" : "{$von}–{$bis} Jahre";
    }

    /**
     * @param list<array<string, mixed>> $klassen
     * @return list<array<string, mixed>>
     */
    private static function nummerieren(array $klassen): array
    {
        foreach ($klassen as $i => &$k) {
            $k['sortierung'] = ($i + 1) * 10;
        }
        return $klassen;
    }
}
