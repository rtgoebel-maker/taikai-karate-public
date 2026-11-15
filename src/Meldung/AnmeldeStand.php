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
            'schluessel' => 'eingegangen', 'titel' => t('Anmeldung eingegangen'), 'zustand' => self::ERLEDIGT,
            'text' => !empty($a['geaendert_am']) ? t('zuletzt geändert am {datum}', ['datum' => self::datum((string) $a['geaendert_am'])]) : '',
            'datum' => (string) $a['eingereicht_am'],
        ]];

        if (!$mitGeld) {
            $schritte[] = ['schluessel' => 'aufforderung', 'titel' => t('Zahlungsaufforderung'), 'zustand' => self::ENTFAELLT, 'text' => t('kein Startgeld zu zahlen'), 'datum' => null];
            $schritte[] = ['schluessel' => 'zahlung', 'titel' => t('Zahlung'), 'zustand' => self::ENTFAELLT, 'text' => '', 'datum' => null];
        } else {
            $schritte[] = [
                'schluessel' => 'aufforderung', 'titel' => t('Zahlungsaufforderung erstellt'), 'zustand' => self::ERLEDIGT,
                'text' => t('über {betrag}', ['betrag' => $euro($betrag)]) . ($version > 1 ? t(' · Version {version} (nach Änderung der Anmeldung)', ['version' => $version]) : ''),
                'datum' => $a['aufforderung_am'] ?? null,
            ];
            $schritte[] = match (true) {
                $bezahltVoll => [
                    'schluessel' => 'zahlung', 'titel' => t('Zahlung eingegangen'), 'zustand' => self::ERLEDIGT,
                    'text' => $euro($bezahlt) . ($offen < 0 ? t(' · Guthaben {betrag} (wird verrechnet bzw. erstattet)', ['betrag' => $euro(-$offen)]) : ''),
                    'datum' => $a['bezahlt_am'] ?? null,
                ],
                $status === 'abgelehnt' => ['schluessel' => 'zahlung', 'titel' => t('Zahlung'), 'zustand' => self::ENTFAELLT, 'text' => '', 'datum' => null],
                $bezahlt > 0 => [
                    'schluessel' => 'zahlung', 'titel' => t('Warte auf Restzahlung'), 'zustand' => self::AKTIV,
                    'text' => t('bereits {bezahlt} erhalten, noch {offen} offen', ['bezahlt' => $euro($bezahlt), 'offen' => $euro($offen)]), 'datum' => $a['bezahlt_am'] ?? null,
                ],
                default => ['schluessel' => 'zahlung', 'titel' => t('Warte auf Zahlung'), 'zustand' => self::AKTIV, 'text' => t('{betrag} offen', ['betrag' => $euro($offen)]), 'datum' => null],
            };
        }

        $schritte[] = match ($status) {
            'freigegeben' => ['schluessel' => 'freigabe', 'titel' => t('Teilnehmer und Dojo freigegeben'), 'zustand' => self::ERLEDIGT, 'text' => t('Die Teilnehmer sind den Klassen zugeordnet.'), 'datum' => $a['freigegeben_am'] ?? null],
            'abgelehnt' => ['schluessel' => 'freigabe', 'titel' => t('Anmeldung abgelehnt'), 'zustand' => self::ABGELEHNT, 'text' => (string) ($a['grund'] ?? ''), 'datum' => null],
            default => [
                'schluessel' => 'freigabe', 'titel' => t('Freigabe durch die Wettkampfleitung'),
                'zustand' => $mitGeld && $zahlungVorFreigabe && !$bezahltVoll ? self::OFFEN : self::AKTIV,
                'text' => $mitGeld && $zahlungVorFreigabe && !$bezahltVoll ? t('nach Zahlungseingang') : t('Prüfung der Anmeldung'),
                'datum' => null,
            ],
        };

        [$kurz, $art] = match (true) {
            $status === 'abgelehnt' => [t('abgelehnt'), 'fehler'],
            $status === 'freigegeben' && (!$mitGeld || $bezahltVoll) => [t('freigegeben'), 'ok'],
            $status === 'freigegeben' => [t('freigegeben · Zahlung offen'), 'warn'],
            $mitGeld && !$bezahltVoll => [$bezahlt > 0 ? t('Restzahlung offen') : t('warte auf Zahlung'), 'warn'],
            default => [t('in Prüfung'), 'info'],
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
        $ts = (int) strtotime($zeit);
        return \Wettkampf\Kern\Sprache::datum(date('Y-m-d', $ts)) . ' ' . date('H:i', $ts);
    }
}
