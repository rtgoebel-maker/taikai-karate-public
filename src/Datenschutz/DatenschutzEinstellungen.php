<?php

declare(strict_types=1);

namespace Wettkampf\Datenschutz;

use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;

/**
 * Datenschutz-Angaben je Turnier (in turnier.einstellungen['datenschutz'], wird mit dem Turnier gesichert und synchronisiert):
 * Verantwortlicher, Hoster, Aufsichtsbehörde und Fristen für die Datenschutzhinweise nach Art. 13/14 DSGVO,
 * dazu der Stand von Buchhaltungsexport, Abschaltung der Statusseite und Pseudonymisierung.
 */
final class DatenschutzEinstellungen
{
    public const LOESCHFRIST_STANDARD = 4;
    public const LIVE_TAGE_STANDARD = 7;

    /** Textfelder mit Höchstlänge (Formular → Einstellungen). */
    private const TEXTE = [
        'verantwortlicher' => 600, 'vertreten' => 300, 'kontakt' => 200, 'dsb' => 300,
        'hoster' => 600, 'aufsicht' => 600, 'veroeffentlichung' => 300, 'zusatz' => 3000,
    ];

    /**
     * @param array<string, mixed> $turnier
     * @return array{verantwortlicher: string, vertreten: string, kontakt: string, dsb: string, hoster: string, aufsicht: string,
     *     veroeffentlichung: string, zusatz: string, cloudflare: bool, fotos: bool, loeschfrist_wochen: int, live_tage: int,
     *     platzierte_behalten: bool, einladungen: bool, eingerichtet: bool, exportiert_am: string|null, live_abgeschaltet_am: string|null, pseudonymisiert_am: string|null}
     */
    public static function lesen(array $turnier): array
    {
        $e = (json_decode((string) ($turnier['einstellungen'] ?? ''), true) ?: [])['datenschutz'] ?? [];
        $e = is_array($e) ? $e : [];
        $text = static fn(string $k): string => is_string($e[$k] ?? null) ? $e[$k] : '';
        $zeit = static fn(string $k): ?string => is_string($e[$k] ?? null) && $e[$k] !== '' ? $e[$k] : null;
        return [
            'verantwortlicher' => $text('verantwortlicher'),
            'vertreten' => $text('vertreten'),
            'kontakt' => $text('kontakt'),
            'dsb' => $text('dsb'),
            'hoster' => $text('hoster'),
            'aufsicht' => $text('aufsicht'),
            'veroeffentlichung' => $text('veroeffentlichung'),
            'zusatz' => $text('zusatz'),
            'cloudflare' => !empty($e['cloudflare']),
            'fotos' => !empty($e['fotos']),
            'loeschfrist_wochen' => max(1, min(52, (int) ($e['loeschfrist_wochen'] ?? self::LOESCHFRIST_STANDARD))),
            'live_tage' => max(0, min(365, (int) ($e['live_tage'] ?? self::LIVE_TAGE_STANDARD))),
            'platzierte_behalten' => !empty($e['platzierte_behalten']),
            'einladungen' => !empty($e['einladungen']),
            // Fristen gelten erst, wenn die Leitung die Angaben gespeichert hat (Bestandsturniere bleiben unberührt)
            'eingerichtet' => isset($e['loeschfrist_wochen']),
            'exportiert_am' => $zeit('exportiert_am'),
            'live_abgeschaltet_am' => $zeit('live_abgeschaltet_am'),
            'pseudonymisiert_am' => $zeit('pseudonymisiert_am'),
        ];
    }

    /**
     * Pflichtangaben für die Datenschutzhinweise vorhanden?
     *
     * @param array{verantwortlicher: string, kontakt: string, aufsicht: string} $e
     */
    public static function vollstaendig(array $e): bool
    {
        return trim($e['verantwortlicher']) !== '' && trim($e['kontakt']) !== '' && trim($e['aufsicht']) !== '';
    }

    /**
     * Ab diesem Tag ist die Löschfrist abgelaufen (Turniertag + Frist in Wochen).
     *
     * @param array<string, mixed> $turnier
     */
    public static function loeschenAb(array $turnier): string
    {
        return date('Y-m-d', (int) strtotime($turnier['datum'] . ' +' . (7 * self::lesen($turnier)['loeschfrist_wochen']) . ' days'));
    }

    /**
     * Ab diesem Tag ist die Statusseite abzuschalten; null = nicht automatisch.
     *
     * @param array<string, mixed> $turnier
     */
    public static function liveAusAb(array $turnier): ?string
    {
        $tage = self::lesen($turnier)['live_tage'];
        return $tage === 0 ? null : date('Y-m-d', (int) strtotime($turnier['datum'] . " +$tage days"));
    }

    public function __construct(private readonly Database $db) {}

    /**
     * Formularangaben speichern (Stand-Felder wie exportiert_am bleiben erhalten).
     *
     * @param array<string, mixed> $post
     */
    public function speichern(int $turnierId, array $post): void
    {
        $alle = $this->alle($turnierId);
        $alt = is_array($alle['datenschutz'] ?? null) ? $alle['datenschutz'] : [];
        $neu = [];
        foreach (self::TEXTE as $feld => $max) {
            $neu[$feld] = mb_substr(trim(str_replace("\r\n", "\n", (string) ($post[$feld] ?? ''))), 0, $max);
        }
        $neu['cloudflare'] = !empty($post['cloudflare']);
        $neu['fotos'] = !empty($post['fotos']);
        $neu['loeschfrist_wochen'] = max(1, min(52, (int) ($post['loeschfrist_wochen'] ?? self::LOESCHFRIST_STANDARD)));
        $neu['live_tage'] = max(0, min(365, (int) ($post['live_tage'] ?? self::LIVE_TAGE_STANDARD)));
        $neu['platzierte_behalten'] = !empty($post['platzierte_behalten']);
        $neu['einladungen'] = !empty($post['einladungen']);
        foreach (['exportiert_am', 'live_abgeschaltet_am', 'pseudonymisiert_am'] as $stand) {
            if (isset($alt[$stand])) {
                $neu[$stand] = $alt[$stand];
            }
        }
        $alle['datenschutz'] = $neu;
        $this->schreiben($turnierId, $alle);
        (new Protokoll($this->db))->eintragen($turnierId, 'turnier', $turnierId, 'datenschutz_gespeichert', [
            'loeschfrist_wochen' => $neu['loeschfrist_wochen'], 'live_tage' => $neu['live_tage'], 'platzierte_behalten' => $neu['platzierte_behalten'],
        ]);
    }

    /** Stand vermerken (exportiert_am, live_abgeschaltet_am, pseudonymisiert_am). */
    public function vermerken(int $turnierId, string $stand, string $zeit): void
    {
        $alle = $this->alle($turnierId);
        $ds = is_array($alle['datenschutz'] ?? null) ? $alle['datenschutz'] : [];
        $ds[$stand] = $zeit;
        $alle['datenschutz'] = $ds;
        $this->schreiben($turnierId, $alle);
    }

    /** @return array<string, mixed> */
    private function alle(int $turnierId): array
    {
        $e = json_decode((string) $this->db->wert('SELECT einstellungen FROM turnier WHERE id = ?', [$turnierId]), true);
        return is_array($e) ? $e : [];
    }

    /** @param array<string, mixed> $alle */
    private function schreiben(int $turnierId, array $alle): void
    {
        $this->db->update('turnier', [
            'einstellungen' => json_encode($alle, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
        ], ['id' => $turnierId]);
    }
}
