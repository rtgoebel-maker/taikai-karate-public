<?php

declare(strict_types=1);

namespace Wettkampf\Meldung;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;

/**
 * Startgeld-Rechnungen an die Dojos: Beträge wie in der Startgeld-Übersicht des Coaching-Portals
 * (CoachService::startgeld – gemeldete Teilnehmer und Teams, ohne Abmeldungen und Nicht-Erschienene),
 * dazu Absender, Bankverbindung, Zahlungsziel und Steuerhinweis aus den Turnier-Einstellungen.
 * Die Rechnungsnummer ist fest je Turnier und Verein (Präfix + Turnier + Verein), ändert sich also
 * nicht, wenn später weitere Dojos dazukommen.
 */
final class RechnungService
{
    /** Steuerhinweis auf Quittungen gemeinnütziger Ausrichter, solange kein eigener eingetragen ist (Katalogschlüssel). */
    public const STEUERHINWEIS_GEMEINNUETZIG = 'Steuerfreie Leistung nach § 4 Nr. 22 Buchst. b UStG (Teilnehmergebühren für eine sportliche Veranstaltung eines gemeinnützigen Vereins).';

    public function __construct(private readonly Database $db) {}

    /**
     * Gemeinnütziger Ausrichter: Quittungen statt Rechnungen (Startgelder umsatzsteuerfrei nach § 4 Nr. 22 b UStG,
     * keine Rechnungspflicht nach § 14 Abs. 2 UStG).
     *
     * @param array<string, mixed> $turnier
     */
    public static function quittung(array $turnier): bool
    {
        return !empty(((json_decode((string) ($turnier['einstellungen'] ?? ''), true) ?: [])['rechnung'] ?? [])['quittung'] ?? null);
    }

    /** Betrag in Worten für die Quittung, z. B. „dreihundertvierundzwanzig Euro und fünfzig Cent“. */
    public static function inWorten(int $cent): string
    {
        $euro = intdiv(max(0, $cent), 100);
        $rest = max(0, $cent) % 100;
        $text = ($euro === 1 ? 'ein' : self::zahlwort($euro)) . ' Euro';
        return $rest > 0 ? $text . ' und ' . ($rest === 1 ? 'ein' : self::zahlwort($rest)) . ' Cent' : $text;
    }

    private static function zahlwort(int $n): string
    {
        if ($n === 0) {
            return 'null';
        }
        $unter1000 = static function (int $n, bool $amEnde): string {
            $einer = ['', 'ein', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun'];
            $zehn = ['zehn', 'elf', 'zwölf', 'dreizehn', 'vierzehn', 'fünfzehn', 'sechzehn', 'siebzehn', 'achtzehn', 'neunzehn'];
            $zehner = ['', '', 'zwanzig', 'dreißig', 'vierzig', 'fünfzig', 'sechzig', 'siebzig', 'achtzig', 'neunzig'];
            $h = intdiv($n, 100);
            $r = $n % 100;
            $text = $h > 0 ? $einer[$h] . 'hundert' : '';
            if ($r === 1) {
                $text .= $amEnde ? 'eins' : 'ein';
            } elseif ($r >= 2 && $r <= 9) {
                $text .= $einer[$r];
            } elseif ($r >= 10 && $r <= 19) {
                $text .= $zehn[$r - 10];
            } elseif ($r >= 20) {
                $text .= ($r % 10 > 0 ? $einer[$r % 10] . 'und' : '') . $zehner[intdiv($r, 10)];
            }
            return $text;
        };
        $millionen = intdiv($n, 1000000);
        $tausend = intdiv($n % 1000000, 1000);
        $rest = $n % 1000;
        return ($millionen > 0 ? ($millionen === 1 ? 'eine Million ' : $unter1000($millionen, false) . ' Millionen ') : '')
            . ($tausend > 0 ? $unter1000($tausend, false) . 'tausend' : '')
            . ($rest > 0 ? $unter1000($rest, true) : '');
    }

    /**
     * @param array<string, mixed> $turnier
     * @return array{absender: string, bank: string, steuerhinweis: string, zahlungsziel: int, praefix: string, datum: string, text: string, starterliste: bool, quittung: bool}
     */
    public function einstellungen(array $turnier): array
    {
        $e = (json_decode((string) ($turnier['einstellungen'] ?? ''), true) ?: [])['rechnung'] ?? [];
        $jahr = substr((string) ($turnier['datum'] ?? date('Y')), 0, 4);
        return [
            'absender' => (string) ($e['absender'] ?? ''),
            'bank' => (string) ($e['bank'] ?? ''),
            'steuerhinweis' => (string) ($e['steuerhinweis'] ?? ''),
            'zahlungsziel' => (int) ($e['zahlungsziel'] ?? 14),
            'praefix' => (string) ($e['praefix'] ?? (!empty($e['quittung']) ? "Q-$jahr-" : "R-$jahr-")),
            'datum' => (string) ($e['datum'] ?? ''),
            'text' => (string) ($e['text'] ?? ''),
            // Namen der Starter auf der Rechnung: nicht nötig (§ 14 UStG: Art und Menge der Leistung) und 8 Jahre im
            // Buchhaltungsarchiv – deshalb nur auf ausdrücklichen Wunsch
            'starterliste' => !empty($e['starterliste']),
            'quittung' => !empty($e['quittung']),
        ];
    }

    /** @param array<string, mixed> $daten */
    public function einstellungenSpeichern(int $turnierId, array $daten): void
    {
        $turnier = $this->db->eine('SELECT einstellungen, datum FROM turnier WHERE id = ?', [$turnierId]) ?? throw new FachFehler(t('Turnier nicht gefunden.'));
        $datum = trim(text_wert($daten['datum'] ?? ''));
        if ($datum !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) || strtotime($datum) === false)) {
            throw new FachFehler(t('Ungültiges Rechnungsdatum.'));
        }
        $ziel = (int) ($daten['zahlungsziel'] ?? 14);
        if ($ziel < 0 || $ziel > 365) {
            throw new FachFehler(t('Das Zahlungsziel muss zwischen 0 und 365 Tagen liegen.'));
        }
        $praefix = trim(text_wert($daten['praefix'] ?? ''));
        // Standard-Präfix folgt der Belegart: Quittung „Q-Jahr-“, Rechnung „R-Jahr-“ – ein eigenes Präfix bleibt
        $jahr = substr((string) ($turnier['datum'] ?? date('Y')), 0, 4);
        $quittung = !empty($daten['quittung']);
        if ($quittung && in_array($praefix, ['', "R-$jahr-"], true)) {
            $praefix = "Q-$jahr-";
        } elseif (!$quittung && in_array($praefix, ['', "Q-$jahr-"], true)) {
            $praefix = "R-$jahr-";
        }
        if (!preg_match('/^[A-Za-z0-9 ._\/-]{0,20}$/', $praefix)) {
            throw new FachFehler(t('Das Präfix der Rechnungsnummer darf nur Buchstaben, Ziffern und . _ / - enthalten (höchstens 20 Zeichen).'));
        }
        $neu = [
            'absender' => mb_substr(trim(text_wert($daten['absender'] ?? '')), 0, 500),
            'bank' => mb_substr(trim(text_wert($daten['bank'] ?? '')), 0, 300),
            'steuerhinweis' => mb_substr(trim(text_wert($daten['steuerhinweis'] ?? '')), 0, 300),
            'text' => mb_substr(trim(text_wert($daten['text'] ?? '')), 0, 1000),
            'zahlungsziel' => $ziel,
            'praefix' => $praefix,
            'datum' => $datum,
            'starterliste' => !empty($daten['starterliste']),
            'quittung' => $quittung,
        ];
        $alle = json_decode((string) $turnier['einstellungen'], true) ?: [];
        $alle['rechnung'] = $neu;
        $this->db->update('turnier', ['einstellungen' => json_encode($alle, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)], ['id' => $turnierId]);
        (new Protokoll($this->db))->eintragen($turnierId, 'turnier', $turnierId, 'rechnung_einstellungen', ['zahlungsziel' => $ziel, 'praefix' => $praefix]);
    }

    /**
     * Dojos mit Starts im Turnier (Einzel oder Team) und ihr Rechnungsbetrag.
     *
     * @return list<array{verein_id: int, verein: string, nummer: string, summe: int, teilnehmer: int, teams: int}>
     */
    public function uebersicht(int $turnierId): array
    {
        $turnier = $this->turnier($turnierId);
        $coach = new CoachService($this->db);
        $liste = [];
        foreach ($this->vereine($turnierId) as $v) {
            $s = $coach->startgeld($turnierId, (int) $v['id']);
            $liste[] = [
                'verein_id' => (int) $v['id'],
                'verein' => (string) $v['name'],
                'nummer' => $this->nummer($turnier, (int) $v['id']),
                'summe' => $s['summe'],
                'teilnehmer' => count($s['teilnehmer']),
                'teams' => count($s['teams']),
            ];
        }
        return $liste;
    }

    /**
     * Daten der Rechnungen (alle Dojos oder nur eines).
     *
     * @return list<array<string, mixed>>
     */
    public function rechnungen(int $turnierId, ?int $vereinId = null): array
    {
        $turnier = $this->turnier($turnierId);
        $e = $this->einstellungen($turnier);
        $datum = $e['datum'] !== '' ? $e['datum'] : date('Y-m-d');
        $coach = new CoachService($this->db);
        $liste = [];
        foreach ($this->vereine($turnierId) as $v) {
            if ($vereinId !== null && (int) $v['id'] !== $vereinId) {
                continue;
            }
            $anschrift = $this->db->eine(
                'SELECT ansprechpartner, email FROM anmeldung WHERE turnier_id = ? AND verein_id = ? AND status <> ? ORDER BY eingereicht_am DESC LIMIT 1',
                [$turnierId, (int) $v['id'], 'abgelehnt'],
            );
            $liste[] = [
                'verein_id' => (int) $v['id'],
                'verein' => (string) $v['name'],
                'ansprechpartner' => (string) ($anschrift['ansprechpartner'] ?? ''),
                'email' => (string) ($anschrift['email'] ?? ''),
                'nummer' => $this->nummer($turnier, (int) $v['id']),
                'datum' => $datum,
                'faellig' => date('Y-m-d', (int) strtotime($datum . ' +' . $e['zahlungsziel'] . ' days')),
                'startgeld' => $coach->startgeld($turnierId, (int) $v['id']),
                'bezahlt' => $this->bezahlt($turnierId, (int) $v['id'], (int) $coach->startgeld($turnierId, (int) $v['id'])['summe']),
            ];
        }
        if ($vereinId !== null && $liste === []) {
            throw new FachFehler(t('Für dieses Dojo gibt es in diesem Turnier keine Starts.'));
        }
        return $liste;
    }

    /**
     * Nummer für Zahlungsaufforderung und Rechnung einer Online-Anmeldung (zugleich Verwendungszweck):
     * Präfix + Turnier + „A“ + Anmelde-Nr. – bleibt über alle Versionen der Zahlungsaufforderung gleich.
     *
     * @param array<string, mixed> $turnier
     */
    public function nummerAnmeldung(array $turnier, int $anmeldungId): string
    {
        return $this->einstellungen($turnier)['praefix'] . (int) $turnier['id'] . '-A' . str_pad((string) $anmeldungId, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Daten für das Rechnungsblatt einer Online-Anmeldung (rechnung/blatt). Eine Rechnung ist für Anmeldungen nicht
     * vorgeschrieben (Anmeldebestätigung mit Zahlungsstand genügt in der Regel); die Leitung erstellt sie auf Wunsch,
     * auch vor der Zahlung (dann mit Zahlungsziel). Dojos rufen sie selbst nur ab, wenn das Turnier es anbietet
     * und die Zahlung vollständig eingegangen ist (Prüfung im Controller).
     *
     * @param array<string, mixed> $turnier
     * @param array<string, mixed> $anmeldung
     * @return array<string, mixed>
     */
    public function anmeldungRechnung(array $turnier, array $anmeldung): array
    {
        $anmeldungen = new AnmeldungService($this->db);
        if ($anmeldung['status'] === 'abgelehnt' || (int) $anmeldung['aufforderung_cent'] <= 0) {
            throw new FachFehler(t('Für diese Anmeldung gibt es keine Rechnung (abgelehnt bzw. kein Startgeld).'));
        }
        $bezahltVoll = $anmeldungen->stand($anmeldung, $turnier)['bezahlt_voll'];
        $e = $this->einstellungen($turnier);
        $datum = $e['datum'] !== '' ? $e['datum'] : date('Y-m-d');
        $daten = json_decode((string) $anmeldung['daten'], true) ?: ['teilnehmer' => [], 'teams' => []];
        $preis = $anmeldungen->preis($anmeldungen->einstellungen($turnier), $daten);
        return [
            'turnier' => $turnier,
            'einstellungen' => $e,
            'empfaenger' => ['verein' => (string) $anmeldung['dojo'], 'ansprechpartner' => (string) $anmeldung['ansprechpartner'], 'email' => (string) $anmeldung['email']],
            'nummer' => $this->nummerAnmeldung($turnier, (int) $anmeldung['id']),
            'datum' => $bezahltVoll && $e['datum'] === '' && $anmeldung['bezahlt_am'] ? (string) $anmeldung['bezahlt_am'] : $datum,
            'faellig' => $bezahltVoll ? null : date('Y-m-d', (int) strtotime($datum . ' +' . $e['zahlungsziel'] . ' days')),
            'bezahlt' => $bezahltVoll ? ['cent' => (int) $anmeldung['bezahlt_cent'], 'am' => $anmeldung['bezahlt_am']] : null,
            'preis' => ['posten' => $preis['posten'], 'summe' => (int) $anmeldung['aufforderung_cent']],
            'teilnehmer' => array_values(array_map(static fn(array $t) => [
                'name' => trim($t['nachname'] . ', ' . $t['vorname']), 'kata' => (bool) $t['kata'], 'kumite' => (string) $t['kumite'],
            ], $daten['teilnehmer'])),
            'teams' => array_map(static fn(array $t) => ['name' => (string) $t['name'], 'disziplin' => (string) $t['disziplin']], $daten['teams']),
        ];
    }

    /**
     * Zahlungseingang eines Dojos (Online-Anmeldungen dieses Vereins): nur wenn der volle Betrag erfasst ist.
     *
     * @return array{cent: int, am: string|null}|null
     */
    private function bezahlt(int $turnierId, int $vereinId, int $summe): ?array
    {
        $z = $this->db->eine(
            "SELECT SUM(bezahlt_cent) AS cent, MAX(bezahlt_am) AS am FROM anmeldung WHERE turnier_id = ? AND verein_id = ? AND status <> 'abgelehnt'",
            [$turnierId, $vereinId],
        );
        $cent = (int) ($z['cent'] ?? 0);
        return $summe > 0 && $cent >= $summe ? ['cent' => $cent, 'am' => $z['am'] !== null ? (string) $z['am'] : null] : null;
    }

    /** @param array<string, mixed> $turnier */
    public function nummer(array $turnier, int $vereinId): string
    {
        return $this->einstellungen($turnier)['praefix'] . (int) $turnier['id'] . '-' . str_pad((string) $vereinId, 3, '0', STR_PAD_LEFT);
    }

    /** @return list<array{id: int|string, name: string}> */
    private function vereine(int $turnierId): array
    {
        return $this->db->alle(
            "SELECT v.id, v.name FROM verein v
              WHERE v.id IN (SELECT a.verein_id FROM teilnahme ta JOIN athlet a ON a.id = ta.athlet_id
                              WHERE ta.turnier_id = ? AND ta.status NOT IN ('abgemeldet', 'nicht_erschienen'))
                 OR v.id IN (SELECT verein_id FROM team WHERE turnier_id = ?)
              ORDER BY v.name",
            [$turnierId, $turnierId],
        );
    }

    /** @return array<string, mixed> */
    private function turnier(int $turnierId): array
    {
        return $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$turnierId]) ?? throw new FachFehler(t('Turnier nicht gefunden.'));
    }
}
