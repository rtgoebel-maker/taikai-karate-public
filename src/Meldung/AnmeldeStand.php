<?php

declare(strict_types=1);

namespace Wettkampf\Meldung;

/**
 * Bearbeitungsstand einer Online-Anmeldung – für das Dojo (Übersicht, „Meine Meldungen“) und die Leitung:
 *
 *   1. Anmeldung eingegangen
 *   2. Zahlungsaufforderung erstellt (automatisch beim Absenden; jede Änderung des Betrags ergibt eine neue
 *      Version, bereits Gezahltes wird verrechnet – keine Stornos nötig)
 *   3. Zahlung eingegangen (erfasst die Wettkampfleitung) – die Übersicht dient dann als Anmelde- und
 *      Zahlungsbestätigung; eine Rechnung nur auf Wunsch (Leitung) bzw. wenn das Turnier sie den Dojos anbietet
 *   4. Teilnehmer und Dojo freigegeben (je Turnier einstellbar: erst nach Zahlungseingang)
 *
 * Ohne Startgeld (Betrag 0) entfallen Schritte 2 und 3.
 */
final class AnmeldeStand
{
    public const ERLEDIGT = 'erledigt';
    public const AKTIV = 'aktiv';
    public const OFFEN = 'offen';
    public const ENTFAELLT = 'entfaellt';
    public const ABGELEHNT = 'abgelehnt';

    /**
     * @param array<string, mixed> $a Zeile aus anmeldung
     * @return array{
     *     schritte: list<array{schluessel: string, titel: string, zustand: string, text: string, datum: string|null}>,
     *     kurz: string, art: string, betrag: int, bezahlt: int, offen: int, bezahlt_voll: bool
     * }
     */
    public static function berechnen(array $a, bool $zahlungVorFreigabe): array
    {
        $betrag = (int) ($a['aufforderung_cent'] ?? 0);
        $version = (int) ($a['aufforderung_version'] ?? 0);
        $bezahlt = (int) ($a['bezahlt_cent'] ?? 0);
        $offen = $betrag - $bezahlt;
        $mitGeld = $version > 0 && ($betrag > 0 || $bezahlt > 0);
        $bezahltVoll = $mitGeld && $offen <= 0 && $bezahlt > 0;
        $status = (string) $a['status'];
        $euro = static fn(int $c) => AnmeldungService::euro($c);

        $schritte = [[
            'schluessel' => 'eingegangen', 'titel' => 'Anmeldung eingegangen', 'zustand' => self::ERLEDIGT,
            'text' => !empty($a['geaendert_am']) ? 'zuletzt geändert am ' . self::datum((string) $a['geaendert_am']) : '',
            'datum' => (string) $a['eingereicht_am'],
        ]];

        if (!$mitGeld) {
            $schritte[] = ['schluessel' => 'aufforderung', 'titel' => 'Zahlungsaufforderung', 'zustand' => self::ENTFAELLT, 'text' => 'kein Startgeld zu zahlen', 'datum' => null];
            $schritte[] = ['schluessel' => 'zahlung', 'titel' => 'Zahlung', 'zustand' => self::ENTFAELLT, 'text' => '', 'datum' => null];
        } else {
            $schritte[] = [
                'schluessel' => 'aufforderung', 'titel' => 'Zahlungsaufforderung erstellt', 'zustand' => self::ERLEDIGT,
                'text' => 'über ' . $euro($betrag) . ($version > 1 ? " · Version $version (nach Änderung der Anmeldung)" : ''),
                'datum' => $a['aufforderung_am'] ?? null,
            ];
            $schritte[] = match (true) {
                $bezahltVoll => [
                    'schluessel' => 'zahlung', 'titel' => 'Zahlung eingegangen', 'zustand' => self::ERLEDIGT,
                    'text' => $euro($bezahlt) . ($offen < 0 ? ' · Guthaben ' . $euro(-$offen) . ' (wird verrechnet bzw. erstattet)' : ''),
                    'datum' => $a['bezahlt_am'] ?? null,
                ],
                $status === 'abgelehnt' => ['schluessel' => 'zahlung', 'titel' => 'Zahlung', 'zustand' => self::ENTFAELLT, 'text' => '', 'datum' => null],
                $bezahlt > 0 => [
                    'schluessel' => 'zahlung', 'titel' => 'Warte auf Restzahlung', 'zustand' => self::AKTIV,
                    'text' => 'bereits ' . $euro($bezahlt) . ' erhalten, noch ' . $euro($offen) . ' offen', 'datum' => $a['bezahlt_am'] ?? null,
                ],
                default => ['schluessel' => 'zahlung', 'titel' => 'Warte auf Zahlung', 'zustand' => self::AKTIV, 'text' => $euro($offen) . ' offen', 'datum' => null],
            };
        }

        $schritte[] = match ($status) {
            'freigegeben' => ['schluessel' => 'freigabe', 'titel' => 'Teilnehmer und Dojo freigegeben', 'zustand' => self::ERLEDIGT, 'text' => 'Die Teilnehmer sind den Klassen zugeordnet.', 'datum' => $a['freigegeben_am'] ?? null],
            'abgelehnt' => ['schluessel' => 'freigabe', 'titel' => 'Anmeldung abgelehnt', 'zustand' => self::ABGELEHNT, 'text' => (string) ($a['grund'] ?? ''), 'datum' => null],
            default => [
                'schluessel' => 'freigabe', 'titel' => 'Freigabe durch die Wettkampfleitung',
                'zustand' => $mitGeld && $zahlungVorFreigabe && !$bezahltVoll ? self::OFFEN : self::AKTIV,
                'text' => $mitGeld && $zahlungVorFreigabe && !$bezahltVoll ? 'nach Zahlungseingang' : 'Prüfung der Anmeldung',
                'datum' => null,
            ],
        };

        [$kurz, $art] = match (true) {
            $status === 'abgelehnt' => ['abgelehnt', 'fehler'],
            $status === 'freigegeben' && (!$mitGeld || $bezahltVoll) => ['freigegeben', 'ok'],
            $status === 'freigegeben' => ['freigegeben · Zahlung offen', 'warn'],
            $mitGeld && !$bezahltVoll => [$bezahlt > 0 ? 'Restzahlung offen' : 'warte auf Zahlung', 'warn'],
            default => ['in Prüfung', 'info'],
        };

        return [
            'schritte' => $schritte, 'kurz' => $kurz, 'art' => $art, 'betrag' => $betrag, 'bezahlt' => $bezahlt,
            'offen' => max(0, $offen), 'bezahlt_voll' => $bezahltVoll,
        ];
    }

    /**
     * Neue Daten der Zahlungsaufforderung, wenn sich der Betrag ändert (sonst leer).
     *
     * @param array<string, mixed>|null $alt bisherige Anmeldung (null = neu)
     * @return array<string, int|string>
     */
    public static function aufforderung(?array $alt, int $preis, string $jetzt): array
    {
        $version = (int) ($alt['aufforderung_version'] ?? 0);
        if ($alt === null || $version === 0) {
            return $preis > 0 ? ['aufforderung_version' => 1, 'aufforderung_cent' => $preis, 'aufforderung_am' => $jetzt] : [];
        }
        return $preis !== (int) $alt['aufforderung_cent']
            ? ['aufforderung_version' => $version + 1, 'aufforderung_cent' => $preis, 'aufforderung_am' => $jetzt]
            : [];
    }

    private static function datum(string $zeit): string
    {
        return date('d.m.Y H:i', (int) strtotime($zeit));
    }
}
