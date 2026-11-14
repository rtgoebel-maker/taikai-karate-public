<?php

declare(strict_types=1);

namespace Wettkampf\Meldung;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Mail\Postausgang;
use Wettkampf\Kern\Protokoll;

/**
 * Freiwillige Einladungen zu künftigen Turnieren (Art. 6 Abs. 1 lit. a DSGVO):
 *
 * - Die Checkbox im Anmeldeformular erscheint nur, wenn das Turnier sie anbietet (Datenschutz-Einstellungen), ist nie
 *   vorausgewählt und keine Bedingung für die Anmeldung (Kopplungsverbot, Art. 7 Abs. 4).
 * - Gespeichert werden E-Mail, Name, Dojo, Herkunft, Zeitpunkt und Textfassung (Nachweis, Art. 7 Abs. 1) – installationsweit,
 *   unabhängig von der Pseudonymisierung des Turniers.
 * - Widerruf jederzeit über den Link in jeder Einladung (ohne Anmeldung) oder durch die Administration; die Zeile wird gelöscht.
 * - Ohne erneute Einwilligung wird ein Eintrag nach GUELTIG_MONATE gelöscht (Speicherbegrenzung).
 */
final class Einladungen
{
    public const GUELTIG_MONATE = 24;

    public const TEXT = 'Ich möchte per E-Mail zu künftigen Turnieren des Veranstalters eingeladen werden (freiwillig, unabhängig von dieser Anmeldung, '
        . 'jederzeit über den Link in jeder Einladung widerrufbar).';

    public function __construct(private readonly Database $db, private readonly string $schluessel) {}

    public static function fassung(): string
    {
        return substr(hash('sha256', self::TEXT), 0, 12);
    }

    /**
     * Bietet das Turnier die Einwilligung an? (Datenschutz-Einstellungen des Turniers)
     *
     * @param array<string, mixed> $turnier
     */
    public static function angeboten(array $turnier): bool
    {
        $ds = (json_decode((string) ($turnier['einstellungen'] ?? ''), true) ?: [])['datenschutz'] ?? [];
        return is_array($ds) && !empty($ds['einladungen']);
    }

    /**
     * Einwilligung speichern bzw. erneuern (gleiche Adresse: Zeitpunkt, Name und Dojo aktualisiert).
     *
     * @param array<string, mixed> $turnier
     */
    public function einwilligen(string $email, string $name, string $dojo, array $turnier): void
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $felder = [
            'name' => mb_substr(trim($name), 0, 200),
            'dojo' => mb_substr(trim($dojo), 0, 200),
            'herkunft' => mb_substr('Anmeldung zu „' . $turnier['name'] . '“ (' . $turnier['datum'] . ')', 0, 250),
            'eingewilligt_am' => date('Y-m-d H:i:s'),
            'fassung' => self::fassung(),
        ];
        $id = $this->db->wert('SELECT id FROM einladung WHERE email = ?', [$email]);
        if ($id === null) {
            $id = $this->db->insert('einladung', $felder + ['email' => mb_substr($email, 0, 190)]);
        } else {
            $this->db->update('einladung', $felder, ['id' => $id]);
        }
        (new Protokoll($this->db))->eintragen(null, 'einladung', (int) $id, 'eingewilligt', ['fassung' => $felder['fassung']]);
    }

    /** @return list<array<string, mixed>> */
    public function liste(): array
    {
        $this->aufraeumen();
        return $this->db->alle('SELECT * FROM einladung ORDER BY dojo, name, email');
    }

    /** @return array<string, mixed>|null */
    public function zuAdresse(string $email): ?array
    {
        return $this->db->eine('SELECT * FROM einladung WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    /** Abmeldelink-Prüfwert (HMAC über ID und Adresse – kein gespeichertes Geheimnis nötig). */
    public function token(int $id, string $email): string
    {
        return hash_hmac('sha256', "einladung|$id|" . mb_strtolower($email), $this->schluessel);
    }

    /**
     * Eintrag zum Abmeldelink oder null (ungültig bzw. schon abgemeldet).
     *
     * @return array<string, mixed>|null
     */
    public function perToken(int $id, string $token): ?array
    {
        $e = $this->db->eine('SELECT * FROM einladung WHERE id = ?', [$id]);
        return $e !== null && hash_equals($this->token($id, (string) $e['email']), $token) ? $e : null;
    }

    /** Widerruf: Eintrag löschen. */
    public function widerrufen(int $id, string $wie): void
    {
        if ($this->db->delete('einladung', ['id' => $id]) > 0) {
            (new Protokoll($this->db))->eintragen(null, 'einladung', $id, 'widerrufen', ['durch' => $wie]);
        }
    }

    /**
     * Einträge ohne erneute Einwilligung seit GUELTIG_MONATE löschen.
     *
     * @return int gelöschte Einträge
     */
    public function aufraeumen(?int $jetzt = null): int
    {
        $grenze = date('Y-m-d H:i:s', (int) strtotime('-' . self::GUELTIG_MONATE . ' months', $jetzt ?? time()));
        return $this->db->query('DELETE FROM einladung WHERE eingewilligt_am < ?', [$grenze])->rowCount();
    }

    /**
     * Einladung zu einem Turnier an alle Eingewilligten in die Warteschlange stellen (je Turnier einmal).
     *
     * @param array<string, mixed> $turnier
     * @param string $veranstalter Name des Veranstalters (erste Zeile der Datenschutz-Angaben bzw. Absender)
     * @return int Anzahl Mails
     */
    public function senden(array $turnier, string $anmeldepfad, string $hinweispfad, string $veranstalter, Postausgang $post): int
    {
        $e = json_decode((string) ($turnier['einstellungen'] ?? ''), true) ?: [];
        if (!empty($e['einladungen_versendet_am'])) {
            throw new FachFehler('Die Einladung zu diesem Turnier wurde bereits am ' . date('d.m.Y', (int) strtotime((string) $e['einladungen_versendet_am'])) . ' versendet.');
        }
        if ((string) $turnier['datum'] < date('Y-m-d')) {
            throw new FachFehler('Das Turnier liegt in der Vergangenheit.');
        }
        $anzahl = 0;
        $jetzt = date('Y-m-d H:i:s');
        foreach ($this->liste() as $z) {
            $abmelden = $post->link('einladung/abmelden/' . $z['id'] . '/' . $this->token((int) $z['id'], (string) $z['email']));
            $text = "Hallo " . ($z['name'] !== '' ? $z['name'] : 'zusammen') . ",\n\n"
                . "wir laden " . ($z['dojo'] !== '' ? "das Dojo {$z['dojo']}" : 'Sie') . " herzlich ein zu:\n\n"
                . "{$turnier['name']}\n" . date('d.m.Y', (int) strtotime((string) $turnier['datum'])) . ($turnier['ort'] ? " · {$turnier['ort']}" : '') . "\n\n"
                . ($turnier['meldeschluss'] ? 'Meldeschluss: ' . date('d.m.Y', (int) strtotime((string) $turnier['meldeschluss'])) . "\n" : '')
                . "Zur Online-Anmeldung: " . $post->link($anmeldepfad) . "\n"
                . "Datenschutzhinweise: " . $post->link($hinweispfad) . "\n\n"
                . "Viele Grüße\n" . ($veranstalter !== '' ? $veranstalter : 'Die Turnierleitung') . "\n\n"
                . "--\nSie erhalten diese E-Mail, weil Sie bei der " . $z['herkunft'] . " eingewilligt haben, zu künftigen Turnieren eingeladen zu werden.\n"
                . "Keine Einladungen mehr: $abmelden\n";
            $post->einreihen((string) $z['email'], 'Einladung: ' . $turnier['name'], $text, 'einladung', null);
            $this->db->update('einladung', ['zuletzt_eingeladen_am' => $jetzt], ['id' => $z['id']]);
            $anzahl++;
        }
        $e['einladungen_versendet_am'] = $jetzt;
        $this->db->update('turnier', ['einstellungen' => json_encode($e, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)], ['id' => $turnier['id']]);
        (new Protokoll($this->db))->eintragen((int) $turnier['id'], 'turnier', (int) $turnier['id'], 'einladungen_versendet', ['anzahl' => $anzahl]);
        return $anzahl;
    }
}
