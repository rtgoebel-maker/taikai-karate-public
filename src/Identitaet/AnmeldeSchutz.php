<?php

declare(strict_types=1);

namespace Wettkampf\Identitaet;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;

/**
 * Begrenzt Fehlversuche bei Login, Tatami-PIN und Leitungs-PIN (OWASP A07: Schutz vor
 * Durchprobieren). Gezählt wird je Ziel und IP in der Datenbank – ein neues Cookie hilft
 * also nicht. Nach MAX_VERSUCHE Fehlern ist das Ziel für diese IP gesperrt, mit jeder
 * weiteren Sperre länger (1, 2, 4 … höchstens 30 Minuten). Je Ziel statt je IP, weil sich in
 * der Halle oft alle Tablets eine öffentliche IP teilen.
 *
 * Zusätzlich je Ziel über alle IPs (SECURITY-FINDINGS Nr. 13): Wer die IP wechselt, umgeht die
 * Sperre sonst. Ab MAX_JE_ZIEL Fehlversuchen pro Stunde gilt für das Ziel eine kurze
 * Zwangspause (ZIEL_PAUSE_SEKUNDEN) – bewusst kurz, damit niemand ein Konto mit absichtlich
 * falschen Passwörtern dauerhaft aussperren kann.
 *
 * Ziel und IP stehen zusätzlich im Klartext (System → Gesperrte Anmeldungen). Einträge ohne Aktivität werden nach
 * AUFBEWAHRUNG_SEKUNDEN gelöscht (Datensparsamkeit); damit beginnt auch die Sperrstufe nach einem ruhigen Tag neu.
 */
final class AnmeldeSchutz
{
    public const MAX_VERSUCHE = 5;
    public const MAX_JE_ZIEL = 50;
    public const ZIEL_PAUSE_SEKUNDEN = 300;
    private const GRUND_SEKUNDEN = 60;
    private const MAX_SEKUNDEN = 1800;
    private const ZIEL_FENSTER_SEKUNDEN = 3600;
    public const AUFBEWAHRUNG_SEKUNDEN = 86400;
    /** Platzhalter statt IP für den Zähler je Ziel über alle IPs (kann keine echte IP sein) */
    private const ALLE_IPS = '*';

    public function __construct(private readonly Database $db) {}

    /** Wirft einen Fehler, solange das Ziel für diese IP oder für alle IPs gesperrt ist. */
    public function pruefen(string $ziel, string $ip, ?int $jetzt = null): void
    {
        $jetzt ??= time();
        foreach ([$ip, self::ALLE_IPS] as $wer) {
            $z = $this->db->eine('SELECT gesperrt_bis FROM anmelde_schutz WHERE schluessel = ?', [self::schluessel($ziel, $wer)]);
            $bis = $z['gesperrt_bis'] ?? null;
            if ($bis !== null && strtotime((string) $bis) > $jetzt) {
                $sekunden = max(1, strtotime((string) $bis) - $jetzt);
                throw new FachFehler('Zu viele Fehlversuche. Bitte ' . ($sekunden >= 120 ? ceil($sekunden / 60) . ' Minuten' : $sekunden . ' Sekunden') . ' warten.');
            }
        }
    }

    /** Fehlversuch zählen; ab MAX_VERSUCHE sperren (wird protokolliert). @return bool gesperrt (für diese IP) */
    public function fehlschlag(string $ziel, string $ip, ?int $turnierId = null, ?int $jetzt = null): bool
    {
        $jetzt ??= time();
        $schluessel = self::schluessel($ziel, $ip);
        $this->aufraeumen($jetzt);
        return $this->db->transaktion(function () use ($schluessel, $ziel, $ip, $turnierId, $jetzt) {
            $z = $this->db->eine('SELECT versuche, sperren FROM anmelde_schutz WHERE schluessel = ?', [$schluessel]);
            $versuche = (int) ($z['versuche'] ?? 0) + 1;
            $sperren = (int) ($z['sperren'] ?? 0);
            $felder = ['versuche' => $versuche, 'sperren' => $sperren, 'gesperrt_bis' => null, 'geaendert_am' => date('Y-m-d H:i:s', $jetzt)]
                + self::klartext($ziel, $ip);
            $gesperrt = $versuche >= self::MAX_VERSUCHE;
            if ($gesperrt) {
                $dauer = min(self::MAX_SEKUNDEN, self::GRUND_SEKUNDEN * (2 ** $sperren));
                $felder = ['versuche' => 0, 'sperren' => $sperren + 1, 'gesperrt_bis' => date('Y-m-d H:i:s', $jetzt + $dauer)] + $felder;
                (new Protokoll($this->db))->eintragen($turnierId, 'anmeldung', null, 'gesperrt', ['ziel' => $ziel, 'ip' => $ip, 'sekunden' => $dauer]);
            }
            $this->speichern($schluessel, $z !== null, $felder);
            $this->zielZaehlen($ziel, $turnierId, $jetzt);
            return $gesperrt;
        });
    }

    /** Erfolgreiche Anmeldung: Zähler und Sperrstufe dieser IP zurücksetzen (der Zähler je Ziel läuft weiter). */
    public function erfolg(string $ziel, string $ip): void
    {
        $this->db->delete('anmelde_schutz', ['schluessel' => self::schluessel($ziel, $ip)]);
    }

    /** Zähler je Ziel über alle IPs im Stundenfenster; ab MAX_JE_ZIEL kurze Zwangspause. */
    private function zielZaehlen(string $ziel, ?int $turnierId, int $jetzt): void
    {
        $schluessel = self::schluessel($ziel, self::ALLE_IPS);
        $z = $this->db->eine('SELECT versuche, geaendert_am FROM anmelde_schutz WHERE schluessel = ?', [$schluessel]);
        // geaendert_am = Beginn des laufenden Fensters
        $imFenster = $z !== null && strtotime((string) $z['geaendert_am']) > $jetzt - self::ZIEL_FENSTER_SEKUNDEN;
        $versuche = ($imFenster ? (int) $z['versuche'] : 0) + 1;
        $felder = [
            'versuche' => $versuche,
            'sperren' => 0,
            'gesperrt_bis' => null,
            'geaendert_am' => $imFenster ? (string) $z['geaendert_am'] : date('Y-m-d H:i:s', $jetzt),
        ] + self::klartext($ziel, self::ALLE_IPS);
        if ($versuche >= self::MAX_JE_ZIEL) {
            $felder = ['versuche' => 0, 'gesperrt_bis' => date('Y-m-d H:i:s', $jetzt + self::ZIEL_PAUSE_SEKUNDEN), 'geaendert_am' => date('Y-m-d H:i:s', $jetzt)] + $felder;
            (new Protokoll($this->db))->eintragen($turnierId, 'anmeldung', null, 'pausiert', [
                'ziel' => $ziel, 'fehlversuche' => $versuche, 'sekunden' => self::ZIEL_PAUSE_SEKUNDEN, 'grund' => 'viele Fehlversuche von verschiedenen IPs',
            ]);
        }
        $this->speichern($schluessel, $z !== null, $felder);
    }

    /**
     * Für System → Gesperrte Anmeldungen: laufende Sperren zuerst, dann Fehlversuche ohne Sperre (neueste zuerst).
     * Den Zähler je Ziel über alle IPs nur, während er pausiert – sonst doppelt er die Zeilen je IP.
     *
     * @return list<array{schluessel: string, ziel: string|null, ip: string|null, alle_ips: bool, versuche: int, sperren: int, gesperrt_bis: string|null, geaendert_am: string, aktiv: bool}>
     */
    public function liste(?int $jetzt = null): array
    {
        $jetzt ??= time();
        $this->aufraeumen($jetzt);
        $liste = [];
        foreach ($this->db->alle('SELECT schluessel, ziel, ip, versuche, sperren, gesperrt_bis, geaendert_am FROM anmelde_schutz ORDER BY geaendert_am DESC') as $z) {
            $aktiv = $z['gesperrt_bis'] !== null && strtotime((string) $z['gesperrt_bis']) > $jetzt;
            if (!$aktiv && ((int) $z['versuche'] === 0 || $z['ip'] === self::ALLE_IPS)) {
                continue; // abgelaufene Sperre ohne neue Fehlversuche (nur für die Sperrstufe gemerkt) bzw. Zähler je Ziel ohne Pause
            }
            $liste[] = [
                'schluessel' => (string) $z['schluessel'],
                'ziel' => $z['ziel'] !== null ? (string) $z['ziel'] : null,
                'ip' => $z['ip'] !== null && $z['ip'] !== self::ALLE_IPS ? (string) $z['ip'] : null,
                'alle_ips' => $z['ip'] === self::ALLE_IPS,
                'versuche' => (int) $z['versuche'],
                'sperren' => (int) $z['sperren'],
                'gesperrt_bis' => $aktiv ? (string) $z['gesperrt_bis'] : null,
                'geaendert_am' => (string) $z['geaendert_am'],
                'aktiv' => $aktiv,
            ];
        }
        usort($liste, static fn($a, $b) => [$b['aktiv'], $b['geaendert_am']] <=> [$a['aktiv'], $a['geaendert_am']]);
        return $liste;
    }

    /** Sperre bzw. Fehlversuche eines Eintrags aufheben (Administration); im Änderungsverlauf festgehalten. */
    public function aufheben(string $schluessel): void
    {
        $z = $this->db->eine('SELECT ziel, ip FROM anmelde_schutz WHERE schluessel = ?', [$schluessel]);
        if ($z === null) {
            throw new FachFehler('Der Eintrag ist nicht mehr vorhanden – die Sperre ist wohl schon abgelaufen.');
        }
        $this->db->delete('anmelde_schutz', ['schluessel' => $schluessel]);
        (new Protokoll($this->db))->eintragen(null, 'anmeldung', null, 'entsperrt', ['ziel' => $z['ziel'], 'ip' => $z['ip']]);
    }

    /** Einträge ohne Aktivität seit AUFBEWAHRUNG_SEKUNDEN löschen (laufende Sperren bleiben). */
    private function aufraeumen(int $jetzt): void
    {
        $grenze = date('Y-m-d H:i:s', $jetzt - self::AUFBEWAHRUNG_SEKUNDEN);
        $this->db->query(
            'DELETE FROM anmelde_schutz WHERE geaendert_am < ? AND (gesperrt_bis IS NULL OR gesperrt_bis < ?)',
            [$grenze, date('Y-m-d H:i:s', $jetzt)],
        );
    }

    /** @return array{ziel: string, ip: string} */
    private static function klartext(string $ziel, string $ip): array
    {
        return ['ziel' => mb_substr($ziel, 0, 190), 'ip' => substr($ip, 0, 45)];
    }

    /** @param array<string, mixed> $felder */
    private function speichern(string $schluessel, bool $vorhanden, array $felder): void
    {
        if ($vorhanden) {
            $this->db->update('anmelde_schutz', $felder, ['schluessel' => $schluessel]);
        } else {
            $this->db->insert('anmelde_schutz', ['schluessel' => $schluessel] + $felder);
        }
    }

    private static function schluessel(string $ziel, string $ip): string
    {
        return hash('sha256', $ziel . '|' . $ip);
    }
}
