<?php

declare(strict_types=1);

namespace Wettkampf\Domain\Wertung;

use Wettkampf\Domain\FachFehler;

/**
 * Auswertung der Wertungssysteme. Ergebnis einer Begegnung:
 *   ['sieger' => 'aka'|'shiro', 'entscheidung' => …, 'daten' => [… für Anzeige/Druck …]]
 *
 * Kampflose Entscheidungen (alle Systeme): Kiken (nicht angetreten/aufgegeben),
 * Hansoku (Disqualifikation im Kampf), Shikkaku (Ausschluss) – 'gegen' nennt
 * die Seite, die verliert.
 */
final class Wertung
{
    public const KAMPFLOS = ['kiken', 'hansoku', 'shikkaku'];

    /** Lesbare Bezeichnung je Entscheidung (Tablet, Listen) */
    public const ENTSCHEIDUNG_TEXT = [
        'wertung' => 'Wertung', 'punkte' => 'Punkte', 'flaggen' => 'Flaggen', 'hantei' => 'Hantei',
        'hansoku' => 'Hansoku', 'kiken' => 'Kiken', 'shikkaku' => 'Shikkaku', 'team' => 'Teamwertung',
        'entscheidungskampf' => 'Entscheidungskampf',
    ];

    public static function entscheidungText(?string $entscheidung): string
    {
        return self::ENTSCHEIDUNG_TEXT[(string) $entscheidung] ?? ucfirst((string) $entscheidung);
    }

    /** Kata-Wertungstafeln laut Wettkampfordnung (C 1.1): 0,0 bis 9,9 in Zehnteln */
    public const PUNKTE_MIN = 0.0;
    public const PUNKTE_MAX = 9.9;

    /**
     * Strafarten im Kumite (Wettkampfordnung B 8–10), je Art drei Stufen:
     * 1 Keikoku (Ermahnung), 2 Hansoku Chui (Verwarnung), 3 Hansoku (Disqualifikation).
     */
    public const STRAFARTEN = ['regel' => '', 'jogai' => 'Jogai', 'mubobi' => 'Mubobi'];

    /** Ziel im Kumite (in Wazaari): Shobu Ippon = 1 Ippon, Shobu Sanbon = 2 volle Punkte */
    public const ZIEL_IPPON = 2;
    public const ZIEL_SANBON = 4;

    /** @return array{sieger: string, entscheidung: string, daten: array<string, mixed>} */
    public static function kampflos(string $entscheidung, string $gegen, ?string $grund = null): array
    {
        if (!in_array($entscheidung, self::KAMPFLOS, true)) {
            throw new FachFehler('Unbekannte Entscheidung.');
        }
        self::seitePruefen($gegen);
        return [
            'sieger' => $gegen === 'aka' ? 'shiro' : 'aka',
            'entscheidung' => $entscheidung,
            'daten' => ['gegen' => $gegen, 'grund' => $grund],
        ];
    }

    /**
     * Flaggenentscheid: Jeder Kampfrichter zeigt Aka, Shiro oder Unentschieden
     * (Hikiwake). Es gewinnt die Seite mit mehr Flaggen; bei gleich vielen
     * Flaggen gibt es keinen Sieger (Verlängerung bzw. erneute Entscheidung).
     *
     * @return array{sieger: string, entscheidung: string, daten: array<string, mixed>}
     */
    public static function flaggen(int $aka, int $shiro, int $kampfrichter, int $unentschieden = 0): array
    {
        if ($aka < 0 || $shiro < 0 || $unentschieden < 0 || $aka + $shiro + $unentschieden !== $kampfrichter) {
            throw new FachFehler("Bitte genau $kampfrichter Flaggen erfassen (Aka $aka + Shiro $shiro + Unentschieden $unentschieden).");
        }
        if ($aka === $shiro) {
            throw new FachFehler("Gleichstand $aka : $shiro – kein Sieger. Bitte verlängern bzw. erneut entscheiden lassen.");
        }
        return [
            'sieger' => $aka > $shiro ? 'aka' : 'shiro',
            'entscheidung' => 'flaggen',
            'daten' => ['flaggen' => ['aka' => $aka, 'shiro' => $shiro, 'unentschieden' => $unentschieden]],
        ];
    }

    /**
     * Kata-Punktwertung eines Vortrags. Bei Streichung (ab 5 Kampfrichtern)
     * entfallen die höchste und die niedrigste Wertung. Nebenwertung = Summe
     * aller Wertungen (entscheidet bei Gleichstand).
     *
     * @param list<float|int|string> $werte
     * @param array{punkte_min?: float, punkte_max?: float, punkte_schritt?: float, streichung?: bool} $optionen
     * @return array{gesamt: float, nebenwertung: float, mit_niedrigster: float, werte: list<float>, gestrichen: list<int>}
     */
    public static function kataPunkte(array $werte, array $optionen, int $kampfrichter): array
    {
        $min = (float) ($optionen['punkte_min'] ?? self::PUNKTE_MIN);
        $max = (float) ($optionen['punkte_max'] ?? self::PUNKTE_MAX);
        $schritt = (float) ($optionen['punkte_schritt'] ?? 0.1);
        $zahlen = [];
        foreach (array_values($werte) as $w) {
            $text = str_replace(',', '.', trim((string) $w));
            if ($text === '' || !is_numeric($text)) {
                throw new FachFehler('Bitte alle Wertungen als Zahl eingeben.');
            }
            $zahl = (float) $text;
            if ($zahl < $min - 1e-9 || $zahl > $max + 1e-9) {
                throw new FachFehler(sprintf('Wertung %s liegt außerhalb von %s bis %s.', self::zahl($zahl), self::zahl($min), self::zahl($max)));
            }
            if ($schritt > 0 && abs(round(($zahl - $min) / $schritt) * $schritt - ($zahl - $min)) > 1e-6) {
                throw new FachFehler(sprintf('Wertung %s passt nicht zur Schrittweite %s.', self::zahl($zahl), self::zahl($schritt)));
            }
            $zahlen[] = round($zahl, 2);
        }
        if (count($zahlen) !== $kampfrichter) {
            throw new FachFehler("Bitte $kampfrichter Wertungen eingeben.");
        }
        $gestrichen = [];
        if (($optionen['streichung'] ?? true) && $kampfrichter >= 5) {
            $sortiert = $zahlen;
            asort($sortiert);
            $gestrichen = [array_key_first($sortiert), array_key_last($sortiert)];
        }
        $gesamt = 0.0;
        foreach ($zahlen as $i => $z) {
            if (!in_array($i, $gestrichen, true)) {
                $gesamt += $z;
            }
        }
        return [
            'gesamt' => round($gesamt, 2),
            // Gleichstand: zuerst die niedrigste (gestrichene) Wertung dazu, dann die höchste (= alle)
            'mit_niedrigster' => round($gesamt + ($gestrichen === [] ? 0.0 : min($zahlen)), 2),
            'nebenwertung' => round(array_sum($zahlen), 2),
            'werte' => $zahlen,
            'gestrichen' => $gestrichen,
        ];
    }

    /**
     * Zwischenwert für den Gleichstand: Summe ohne die höchste Wertung (gewertete + niedrigste).
     * Für ältere Wertungen ohne gespeicherten Wert aus den Einzelwertungen berechnet.
     */
    public static function mitNiedrigster(?string $wertungJson, float $gesamt): float
    {
        $w = json_decode((string) $wertungJson, true);
        if (!is_array($w)) {
            return $gesamt;
        }
        if (isset($w['mit_niedrigster'])) {
            return (float) $w['mit_niedrigster'];
        }
        $werte = array_map('floatval', (array) ($w['werte'] ?? []));
        return $werte !== [] && !empty($w['gestrichen']) ? round($gesamt + min($werte), 2) : $gesamt;
    }

    /**
     * Kata im Punktesystem als direkte Begegnung (Aka gegen Shiro).
     * Gleichstand → plus niedrigste Wertung → plus höchste (alle) → Flaggenentscheid ($hantei).
     *
     * @param list<float|int|string> $aka
     * @param list<float|int|string> $shiro
     * @param array<string, mixed> $optionen
     * @param array{aka: int, shiro: int, unentschieden?: int}|null $hantei
     * @return array{sieger: string, entscheidung: string, daten: array<string, mixed>}
     */
    public static function punkteBegegnung(array $aka, array $shiro, array $optionen, int $kampfrichter, ?array $hantei = null): array
    {
        $a = self::kataPunkte($aka, $optionen, $kampfrichter);
        $s = self::kataPunkte($shiro, $optionen, $kampfrichter);
        $daten = ['aka' => $a, 'shiro' => $s];
        $vergleich = [$a['gesamt'], $a['mit_niedrigster'], $a['nebenwertung']] <=> [$s['gesamt'], $s['mit_niedrigster'], $s['nebenwertung']];
        if ($vergleich !== 0) {
            return ['sieger' => $vergleich > 0 ? 'aka' : 'shiro', 'entscheidung' => 'punkte', 'daten' => $daten];
        }
        if ($hantei === null) {
            throw new FachFehler('Punktgleichstand – bitte per Flaggenentscheid (Hantei) entscheiden.');
        }
        $flaggen = self::flaggen($hantei['aka'], $hantei['shiro'], $kampfrichter, $hantei['unentschieden'] ?? 0);
        return ['sieger' => $flaggen['sieger'], 'entscheidung' => 'hantei', 'daten' => $daten + $flaggen['daten']];
    }

    /**
     * Kumite nach der Wettkampfordnung (B 2.2, 4, 6, 8–10). Ippon = 2 Wazaari (Awazete Ippon).
     * Shobu Ippon ($ziel = 2): ein Ippon beendet den Kampf; Shobu Sanbon ($ziel = 4, Finale):
     * zwei volle Punkte. Ohne erreichtes Ziel nur nach Ablauf der Kampfzeit ('zeit_abgelaufen'):
     * die höhere Wertung gewinnt, bei Gleichstand Hantei (Flaggen) – davor optional eine
     * Verlängerung (Saishiai, 'saishiai' mit dem Stand des Hauptkampfs in 'hauptkampf').
     *
     * Strafen geben dem Gegner keine Punkte; sie fließen ins Hantei ein. Je Art (Regelverstoß,
     * Jogai, Mubobi) Stufe 1 Keikoku, 2 Hansoku Chui, 3 Hansoku = Disqualifikation: der Gegner
     * gewinnt (zählt im Team als Ippon).
     *
     * @param array<string, mixed> $eingabe aka_ippon, aka_wazaari, aka_strafen{regel,jogai,mubobi}, shiro_…, zeit_abgelaufen, saishiai, hauptkampf
     * @param array{aka: int, shiro: int, unentschieden?: int}|null $hantei
     * @return array{sieger: string, entscheidung: string, daten: array<string, mixed>}
     */
    public static function kumite(array $eingabe, int $kampfrichter, ?array $hantei = null, int $ziel = self::ZIEL_IPPON): array
    {
        $daten = ['aka' => self::kumiteSeite($eingabe, 'aka'), 'shiro' => self::kumiteSeite($eingabe, 'shiro')];
        if ($ziel === self::ZIEL_SANBON) {
            $daten['sanbon'] = true;
        }
        if (!empty($eingabe['saishiai'])) {
            $daten['saishiai'] = true;
            $haupt = is_array($eingabe['hauptkampf'] ?? null) ? $eingabe['hauptkampf'] : [];
            $daten['hauptkampf'] = ['aka' => self::kumiteSeite($haupt, 'aka'), 'shiro' => self::kumiteSeite($haupt, 'shiro')];
        }
        // Hansoku (3. Stufe einer Strafart) entscheidet sofort für den Gegner
        $hansoku = array_values(array_filter(['aka', 'shiro'], static fn(string $s) => max($daten[$s]['strafen']) >= 3));
        if (count($hansoku) === 2) {
            throw new FachFehler('Beide Seiten mit Hansoku – bitte die Entscheidung als Hansoku bzw. Shikkaku gegen eine Seite eintragen.');
        }
        if ($hansoku !== []) {
            $gegen = $hansoku[0];
            return ['sieger' => $gegen === 'aka' ? 'shiro' : 'aka', 'entscheidung' => 'hansoku', 'daten' => $daten + ['gegen' => $gegen, 'grund' => self::strafenText($daten[$gegen]['strafen'])]];
        }
        $punkte = ['aka' => self::kumitePunkte($daten['aka']), 'shiro' => self::kumitePunkte($daten['shiro'])];
        $vergleich = $punkte['aka'] <=> $punkte['shiro'];
        if (max($punkte) >= $ziel && $vergleich !== 0) {
            return ['sieger' => $vergleich > 0 ? 'aka' : 'shiro', 'entscheidung' => 'wertung', 'daten' => $daten];
        }
        $zeitAbgelaufen = !empty($eingabe['zeit_abgelaufen']) || $hantei !== null;
        if (!$zeitAbgelaufen) {
            throw new FachFehler(sprintf(
                '%s (AKA %s : SHIRO %s). Der Kampf geht weiter – oder „Kampfzeit abgelaufen“ wählen.',
                $ziel === self::ZIEL_SANBON ? 'Noch keine zwei vollen Punkte erreicht' : 'Noch kein Ippon erreicht',
                self::kumiteText($punkte['aka']),
                self::kumiteText($punkte['shiro']),
            ));
        }
        $daten['zeit_abgelaufen'] = true;
        if ($vergleich !== 0) {
            return ['sieger' => $vergleich > 0 ? 'aka' : 'shiro', 'entscheidung' => 'wertung', 'daten' => $daten];
        }
        if ($hantei === null) {
            throw new FachFehler('Gleichstand – bitte Saishiai (Verlängerung) austragen oder Hantei (Flaggenentscheid) eintragen.');
        }
        $flaggen = self::flaggen($hantei['aka'], $hantei['shiro'], $kampfrichter, $hantei['unentschieden'] ?? 0);
        return ['sieger' => $flaggen['sieger'], 'entscheidung' => 'hantei', 'daten' => $daten + $flaggen['daten']];
    }

    /**
     * Eine Seite eines Kumite-Kampfs aus der Eingabe: Ippon, Wazaari, Strafen je Art (0–3).
     *
     * @param array<string, mixed> $eingabe
     * @return array{ippon: int, wazaari: int, strafen: array<string, int>, verwarnung: string|null}
     */
    private static function kumiteSeite(array $eingabe, string $s): array
    {
        $ippon = (int) ($eingabe["{$s}_ippon"] ?? 0);
        $wazaari = (int) ($eingabe["{$s}_wazaari"] ?? 0);
        if ($ippon < 0 || $wazaari < 0 || $ippon > 5 || $wazaari > 10) {
            throw new FachFehler('Ungültige Anzahl Ippon/Wazaari.');
        }
        $roh = is_array($eingabe["{$s}_strafen"] ?? null) ? $eingabe["{$s}_strafen"] : [];
        $strafen = [];
        foreach (array_keys(self::STRAFARTEN) as $art) {
            $stufe = (int) ($roh[$art] ?? 0);
            if ($stufe < 0 || $stufe > 3) {
                throw new FachFehler('Ungültige Strafstufe (0 bis 3).');
            }
            $strafen[$art] = $stufe;
        }
        // „verwarnung“: lesbarer Text für Anzeige und Druck
        return ['ippon' => $ippon, 'wazaari' => $wazaari, 'strafen' => $strafen, 'verwarnung' => self::strafenText($strafen) ?: null];
    }

    /**
     * Wertung einer Seite in Wazaari (Strafen zählen nicht als Punkte).
     *
     * @param array{ippon: int, wazaari: int} $seite
     */
    public static function kumitePunkte(array $seite): int
    {
        return $seite['ippon'] * 2 + $seite['wazaari'];
    }

    /**
     * Strafen einer Seite als Text: „Keikoku, Jogai-Hansoku Chui“ (Stufe 0 entfällt).
     *
     * @param array<string, int> $strafen
     */
    public static function strafenText(array $strafen): string
    {
        $stufen = [1 => 'Keikoku', 2 => 'Hansoku Chui', 3 => 'Hansoku'];
        $teile = [];
        foreach (self::STRAFARTEN as $art => $vorsatz) {
            $stufe = (int) ($strafen[$art] ?? 0);
            if ($stufe > 0) {
                $teile[] = ($vorsatz !== '' ? $vorsatz . '-' : '') . $stufen[min(3, $stufe)];
            }
        }
        return implode(', ', $teile);
    }

    /** 3 → „1 Ippon 1 Wazaari“, 0 → „0“ */
    private static function kumiteText(int $punkte): string
    {
        $teile = [];
        if (intdiv($punkte, 2) > 0) {
            $teile[] = intdiv($punkte, 2) . ' Ippon';
        }
        if ($punkte % 2) {
            $teile[] = '1 Wazaari';
        }
        return $teile ? implode(' ', $teile) : '0';
    }

    /**
     * Kumite-Team (Wettkampfordnung B 2.3): mehr Einzelsiege gewinnt; bei Gleichstand die
     * Unterbewertung – nur die Wertungen der Sieger zählen: zuerst die Ippon-Siege (ein
     * Disqualifikationssieg zählt als Ippon, Awazete Ippon nicht), dann die Wazaari der Sieger.
     * Unentschiedene Kämpfe und die Wertungen der Verlierer bleiben unberücksichtigt. Danach
     * entscheidet ein Stichkampf (Eintrag mit 'entscheidungskampf' => true).
     *
     * @param list<array{sieger: string, aka_ippon?: int, aka_wazaari?: int, shiro_ippon?: int, shiro_wazaari?: int, hansoku?: bool, entscheidungskampf?: bool}> $kaempfe
     * @param int|null $anzahl vorgeschriebene Zahl der Einzelkämpfe (ungerade, laut Ausschreibung)
     * @return array{sieger: string, entscheidung: string, daten: array<string, mixed>}
     */
    public static function team(array $kaempfe, ?int $anzahl = null): array
    {
        $summe = ['aka' => ['siege' => 0, 'ippon' => 0, 'wazaari' => 0], 'shiro' => ['siege' => 0, 'ippon' => 0, 'wazaari' => 0]];
        $entscheidung = null;
        $einzel = 0;
        foreach ($kaempfe as $k) {
            if (!in_array($k['sieger'], ['aka', 'shiro', 'hikiwake'], true)) {
                throw new FachFehler('Jeder Einzelkampf braucht ein Ergebnis (Aka, Shiro oder Hikiwake).');
            }
            if (!empty($k['entscheidungskampf'])) {
                $entscheidung = $k['sieger'];
                continue;
            }
            $einzel++;
            $s = $k['sieger'];
            if ($s === 'hikiwake') {
                continue;
            }
            $summe[$s]['siege']++;
            if (!empty($k['hansoku']) || (int) ($k["{$s}_ippon"] ?? 0) > 0) {
                $summe[$s]['ippon']++;
            } else {
                $summe[$s]['wazaari'] += (int) ($k["{$s}_wazaari"] ?? 0);
            }
        }
        // B 11 9.5: auch ein schon entschiedener Mannschaftskampf wird zu Ende gekämpft
        if ($anzahl !== null && $einzel !== $anzahl) {
            throw new FachFehler("Bitte alle $anzahl Einzelkämpfe eintragen – auch wenn der Mannschaftskampf schon entschieden ist.");
        }
        $daten = ['kaempfe' => $kaempfe, 'summe' => $summe, 'aka' => ['ippon' => $summe['aka']['ippon'], 'wazaari' => $summe['aka']['wazaari']],
            'shiro' => ['ippon' => $summe['shiro']['ippon'], 'wazaari' => $summe['shiro']['wazaari']]];
        $vergleich = array_values($summe['aka']) <=> array_values($summe['shiro']);
        if ($vergleich !== 0) {
            return ['sieger' => $vergleich > 0 ? 'aka' : 'shiro', 'entscheidung' => 'team', 'daten' => $daten];
        }
        if ($entscheidung === null || $entscheidung === 'hikiwake') {
            throw new FachFehler('Gleichstand nach Einzelsiegen, Ippon-Siegen und Wazaari der Sieger – bitte den Stichkampf eintragen.');
        }
        return ['sieger' => $entscheidung, 'entscheidung' => 'entscheidungskampf', 'daten' => $daten];
    }

    private static function seitePruefen(string $seite): void
    {
        if (!in_array($seite, ['aka', 'shiro'], true)) {
            throw new FachFehler('Seite muss Aka oder Shiro sein.');
        }
    }

    private static function zahl(float $z): string
    {
        return rtrim(rtrim(number_format($z, 2, ',', ''), '0'), ',');
    }
}
