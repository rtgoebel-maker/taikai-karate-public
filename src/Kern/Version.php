<?php

declare(strict_types=1);

namespace Wettkampf\Kern;

/**
 * Programmversion und Sync-Protokoll.
 *
 * - Programmversion: Datei VERSION im Programmordner, gesetzt von der CI beim Bau der Pakete (staging-deploy, halle),
 *   Form „JJJJ.MM.TT-<commit>“. In einem Entwicklungs-Klon ohne VERSION: „entwicklung-<commit>“ aus .git.
 * - SYNC_PROTOKOLL: steigt, wenn sich das Format zwischen Hallen-Laptop und Online ändert (Übergabe, Hallenstand,
 *   Anträge, Rückgabe). Passt es nicht, weist die Gegenseite mit „bitte aktualisieren“ ab. Andere Programmversionen
 *   mit gleichem Protokoll arbeiten zusammen (Hinweis in der Anzeige).
 */
final class Version
{
    /** 1 = Stand 30.10.2026, 2 = Anmeldungen mit Zahlungsstand (Migration 032). */
    public const SYNC_PROTOKOLL = 2;

    public static function aktuell(?string $wurzel = null): string
    {
        $wurzel ??= dirname(__DIR__, 2);
        $datei = $wurzel . '/VERSION';
        if (is_file($datei)) {
            $version = trim((string) file_get_contents($datei));
            if (preg_match('/^[0-9A-Za-z.+-]{1,40}$/', $version)) {
                return $version;
            }
        }
        $commit = self::commit($wurzel . '/.git');
        return 'entwicklung' . ($commit !== null ? '-' . $commit : '');
    }

    /** Kurzer Commit aus .git (ohne git-Aufruf); null, wenn nicht lesbar. */
    private static function commit(string $git): ?string
    {
        $kopf = is_file("$git/HEAD") ? trim((string) file_get_contents("$git/HEAD")) : '';
        if (str_starts_with($kopf, 'ref: ')) {
            $ref = substr($kopf, 5);
            $kopf = is_file("$git/$ref") ? trim((string) file_get_contents("$git/$ref")) : '';
            if ($kopf === '' && is_file("$git/packed-refs")) {
                foreach (file("$git/packed-refs", FILE_IGNORE_NEW_LINES) ?: [] as $zeile) {
                    if (str_ends_with($zeile, " $ref")) {
                        $kopf = substr($zeile, 0, 40);
                    }
                }
            }
        }
        return preg_match('/^[0-9a-f]{40}$/', $kopf) ? substr($kopf, 0, 7) : null;
    }
}
