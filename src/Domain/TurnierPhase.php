<?php

declare(strict_types=1);

namespace Wettkampf\Domain;

/**
 * Phasen eines Turniers (Spalte turnier.status):
 * Vorbereitung → Meldephase (Online-Anmeldung offen) → Turnier läuft
 * (Ergebnisse erlaubt) → Abgeschlossen (Ergebnisse gesperrt).
 */
final class TurnierPhase
{
    public const VORBEREITUNG = 'vorbereitung';
    public const MELDEPHASE = 'meldephase';
    public const LAEUFT = 'laeuft';
    public const ABGESCHLOSSEN = 'abgeschlossen';

    /** Reihenfolge und Bezeichnung */
    public const ALLE = [
        self::VORBEREITUNG => 'Vorbereitung',
        self::MELDEPHASE => 'Meldephase',
        self::LAEUFT => 'Turnier läuft',
        self::ABGESCHLOSSEN => 'Abgeschlossen',
    ];

    /**
     * Hat die Leitung das Turnier gestartet (läuft oder abgeschlossen)? Vorher zeigt die
     * Statusseite noch keine Tatami-Belegung.
     *
     * @param array<string, mixed> $turnier
     */
    public static function gestartet(array $turnier): bool
    {
        return in_array(self::von($turnier), [self::LAEUFT, self::ABGESCHLOSSEN], true);
    }

    /** @param array<string, mixed> $turnier */
    public static function von(array $turnier): string
    {
        $status = (string) ($turnier['status'] ?? '');
        return isset(self::ALLE[$status]) ? $status : self::VORBEREITUNG; // auch alte Werte wie „planung“
    }

    public static function text(string $phase): string
    {
        return self::ALLE[$phase] ?? self::ALLE[self::VORBEREITUNG];
    }
}
