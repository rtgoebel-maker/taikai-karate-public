<?php

declare(strict_types=1);

namespace Wettkampf\Oeffentlich;

use Wettkampf\Domain\Wertung\Wertung;
use Wettkampf\Durchfuehrung\KlassenAnsicht;
use Wettkampf\Durchfuehrung\TatamiAnsicht;
use Wettkampf\Kern\Db\Database;

/**
 * Einblendung für Livestreams (z. B. OBS-Browserquelle): was läuft gerade auf einer Tatami, und – kurz
 * nach dem Ende – wer hat gewonnen. Nur Name und Verein (wie die Statusseite), keine Geburtsdaten.
 */
final class EinblendungAnsicht
{
    /** So lange nach dem Ende bleibt das Ergebnis eingeblendet (Sekunden). */
    public const ERGEBNIS_SEKUNDEN = 20;

    public function __construct(private readonly Database $db) {}

    /** @return array{tatami: string, jetzt: array<string, mixed>|null, ergebnis: array<string, mixed>|null} */
    public function fuerTatami(int $tatamiId, ?int $jetzt = null): array
    {
        $t = (new TatamiAnsicht($this->db))->fuerTatami($tatamiId, 0);
        $l = $t['laufend'];
        // Kata-Vortrag „steht an“, sobald die Gruppe begonnen hat bzw. sein Ausruf abgeschlossen ist; Kampf erst, wenn er läuft
        $sichtbar = $l !== null && ($l['status'] === 'laeuft' || ($l['typ'] === 'auftritt' && (!empty($l['gruppe_begonnen']) || !empty($l['aufruf']['fertig']))));
        return [
            'tatami' => (string) $t['tatami']['bezeichnung'],
            'jetzt' => $sichtbar ? self::eintrag($l) : null,
            'ergebnis' => $this->letztesErgebnis($tatamiId, $jetzt ?? time()),
        ];
    }

    /**
     * @param array<string, mixed> $e
     * @return array<string, mixed>
     */
    private static function eintrag(array $e): array
    {
        return [
            'typ' => $e['typ'],
            'klasse' => $e['klasse'],
            'klasse_name' => $e['klasse_name'],
            'runde' => $e['runde'],
            'nr' => $e['nr'],
            'disziplin' => self::disziplin($e['disziplin'], $e['kumite_form'] ?? null),
            'unterbrochen' => (bool) ($e['unterbrochen'] ?? false),
            'aka' => self::person($e['aka'] ?? null),
            'shiro' => self::person($e['shiro'] ?? null),
            'starter' => self::person($e['starter'] ?? null),
        ];
    }

    /**
     * Zuletzt beendeter Kampf bzw. Vortrag, wenn er höchstens ERGEBNIS_SEKUNDEN zurückliegt.
     *
     * @return array<string, mixed>|null
     */
    private function letztesErgebnis(int $tatamiId, int $jetzt): ?array
    {
        $seit = date('Y-m-d H:i:s', $jetzt - self::ERGEBNIS_SEKUNDEN);
        $b = $this->db->eine(
            "SELECT b.*, k.code, k.bezeichnung AS klasse_name, k.disziplin FROM begegnung b JOIN phase p ON p.id = b.phase_id JOIN klasse k ON k.id = p.klasse_id
              WHERE p.tatami_id = ? AND b.status = 'beendet' AND b.beendet_am >= ? ORDER BY b.beendet_am DESC, b.id DESC LIMIT 1",
            [$tatamiId, $seit],
        );
        $a = $this->db->eine(
            "SELECT a.*, k.code, k.bezeichnung AS klasse_name, k.disziplin FROM auftritt a JOIN phase p ON p.id = a.phase_id JOIN klasse k ON k.id = p.klasse_id
              WHERE p.tatami_id = ? AND a.status = 'beendet' AND a.beendet_am >= ? ORDER BY a.beendet_am DESC, a.id DESC LIMIT 1",
            [$tatamiId, $seit],
        );
        if ($b === null && $a === null) {
            return null;
        }
        $namen = new KlassenAnsicht($this->db);
        if ($b !== null && ($a === null || (string) $b['beendet_am'] >= (string) $a['beendet_am'])) {
            $seite = (int) $b['sieger_start_id'] === (int) $b['aka_start_id'] ? 'aka' : 'shiro';
            return [
                'typ' => 'begegnung',
                'klasse' => $b['code'],
                'klasse_name' => $b['klasse_name'],
                'disziplin' => self::disziplin($b['disziplin'], $b['kumite_form'] ?? null),
                'sieger_seite' => $seite,
                'sieger' => self::person($namen->person((int) $b['sieger_start_id'])),
                'text' => Wertung::entscheidungText($b['entscheidung']),
            ];
        }
        $kiken = !empty((json_decode((string) $a['wertung'], true) ?: [])['kiken']);
        return [
            'typ' => 'auftritt',
            'klasse' => $a['code'],
            'klasse_name' => $a['klasse_name'],
            'disziplin' => self::disziplin($a['disziplin'], null),
            'starter' => self::person($namen->person((int) $a['start_id'])),
            'text' => $kiken ? 'Kiken' : number_format((float) $a['gesamt'], 1, ',', '') . ' Punkte',
        ];
    }

    /**
     * @param array<string, mixed>|null $p
     * @return array{name: string, verein: string}|null
     */
    private static function person(?array $p): ?array
    {
        return $p === null ? null : ['name' => (string) ($p['name'] ?? ''), 'verein' => (string) ($p['verein'] ?? '')];
    }

    private static function disziplin(string $disziplin, ?string $kumiteForm): string
    {
        return match (true) {
            $disziplin === 'kumite' && !empty($kumiteForm) => kumite_form_text($kumiteForm),
            !empty($kumiteForm) => disziplin_text($disziplin) . ' · ' . kumite_form_text($kumiteForm),
            default => disziplin_text($disziplin),
        };
    }
}
