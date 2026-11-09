<?php

declare(strict_types=1);

namespace Wettkampf\Kommunikation;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;

/**
 * Feedback der Nutzer (Fehler, Verbesserungsvorschläge, Fragen) mit Seite und
 * optionalem Screenshot. Bearbeitung (Status, Notiz) und Export durch die Leitung.
 */
final class FeedbackService
{
    public const ARTEN = ['fehler' => 'Fehler', 'vorschlag' => 'Verbesserungsvorschlag', 'frage' => 'Frage'];
    public const STATUS = ['neu' => 'neu', 'in_arbeit' => 'in Bearbeitung', 'erledigt' => 'erledigt', 'abgelehnt' => 'abgelehnt'];
    public const MAX_BILD_BYTES = 4 * 1024 * 1024;
    private const MAX_TEXT = 5000;
    private const MIME = [IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg'];

    public function __construct(private readonly Database $db) {}

    /**
     * @param array<string, mixed> $daten art, beschreibung, seite, geraet, screenshot (Data-URL, optional)
     */
    public function einreichen(array $daten, string $von, ?int $turnierId): int
    {
        $art = (string) ($daten['art'] ?? '');
        if (!isset(self::ARTEN[$art])) {
            throw new FachFehler('Bitte wählen: Fehler, Verbesserungsvorschlag oder Frage.');
        }
        $beschreibung = trim((string) ($daten['beschreibung'] ?? ''));
        if ($beschreibung === '') {
            throw new FachFehler('Bitte beschreiben, was aufgefallen ist.');
        }
        $seite = self::einzeilig((string) ($daten['seite'] ?? ''), 500);
        if (!str_starts_with($seite, '/') || str_starts_with($seite, '//') || str_contains($seite, '\\')) {
            $seite = '/';
        }
        [$bild, $mime] = $this->screenshotPruefen($daten['screenshot'] ?? null);
        return $this->db->insert('feedback', [
            'art' => $art,
            'beschreibung' => mb_substr($beschreibung, 0, self::MAX_TEXT),
            'seite' => $seite,
            'turnier_id' => $turnierId,
            'von' => self::einzeilig($von, 200),
            'geraet' => self::einzeilig((string) ($daten['geraet'] ?? ''), 300) ?: null,
            'screenshot' => $bild,
            'screenshot_mime' => $mime,
            'status' => 'neu',
            'erstellt_am' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Status und/oder Notiz ändern (null = unverändert). */
    public function bearbeiten(int $id, ?string $status, ?string $notiz): void
    {
        $this->laden($id);
        $felder = ['geaendert_am' => date('Y-m-d H:i:s')];
        if ($status !== null) {
            if (!isset(self::STATUS[$status])) {
                throw new FachFehler('Unbekannter Status.');
            }
            $felder['status'] = $status;
        }
        if ($notiz !== null) {
            $felder['notiz'] = mb_substr(trim($notiz), 0, self::MAX_TEXT);
        }
        $this->db->update('feedback', $felder, ['id' => $id]);
    }

    public function loeschen(int $id): void
    {
        $this->db->delete('feedback', ['id' => $id]);
    }

    /**
     * Liste ohne Bilddaten, neueste zuerst.
     *
     * @return list<array<string, mixed>>
     */
    public function liste(?string $status = null, ?string $art = null): array
    {
        $wo = [];
        $parameter = [];
        if ($status !== null && isset(self::STATUS[$status])) {
            $wo[] = 'f.status = ?';
            $parameter[] = $status;
        }
        if ($art !== null && isset(self::ARTEN[$art])) {
            $wo[] = 'f.art = ?';
            $parameter[] = $art;
        }
        return $this->db->alle(
            'SELECT f.id, f.art, f.beschreibung, f.seite, f.turnier_id, t.name AS turnier, f.von, f.geraet, f.status, f.notiz,
                    f.erstellt_am, f.geaendert_am, f.screenshot_mime, CASE WHEN f.screenshot IS NULL THEN 0 ELSE 1 END AS hat_screenshot
             FROM feedback f LEFT JOIN turnier t ON t.id = f.turnier_id'
            . ($wo ? ' WHERE ' . implode(' AND ', $wo) : '') . ' ORDER BY f.erstellt_am DESC, f.id DESC',
            $parameter,
        );
    }

    public function anzahlNeu(): int
    {
        return (int) $this->db->wert("SELECT COUNT(*) FROM feedback WHERE status = 'neu'");
    }

    /** @return array{mime: string, inhalt: string}|null */
    public function screenshot(int $id): ?array
    {
        $z = $this->db->eine('SELECT screenshot, screenshot_mime FROM feedback WHERE id = ?', [$id]);
        if ($z === null || $z['screenshot'] === null) {
            return null;
        }
        return ['mime' => (string) $z['screenshot_mime'], 'inhalt' => (string) base64_decode((string) $z['screenshot'])];
    }

    /**
     * Einträge mit Status „neu“ für den Export; Screenshot als Data-URL.
     *
     * @return list<array<string, mixed>>
     */
    public function exportDaten(): array
    {
        $liste = [];
        foreach ($this->liste('neu') as $f) {
            $bild = $f['hat_screenshot'] ? $this->db->wert('SELECT screenshot FROM feedback WHERE id = ?', [$f['id']]) : null;
            $liste[] = self::exportZeile($f) + ['screenshot' => $bild === null ? null : "data:{$f['screenshot_mime']};base64,$bild"];
        }
        return $liste;
    }

    /**
     * ZIP mit den Einträgen im Status „neu“: feedback.json, Screenshots als Bilddateien, HTML-Übersicht.
     */
    public function exportZip(): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new FachFehler('ZIP-Export ist auf diesem Server nicht verfügbar (PHP-Erweiterung zip) – bitte den JSON-Export nutzen.');
        }
        $datei = (string) tempnam(sys_get_temp_dir(), 'fb');
        $zip = new \ZipArchive();
        $zip->open($datei, \ZipArchive::OVERWRITE);
        $eintraege = [];
        $html = '';
        foreach ($this->liste('neu') as $f) {
            $zeile = self::exportZeile($f) + ['screenshot' => null];
            $bild = $f['hat_screenshot'] ? $this->screenshot((int) $f['id']) : null;
            if ($bild !== null) {
                $zeile['screenshot'] = sprintf('screenshots/feedback-%d.%s', $f['id'], $bild['mime'] === 'image/png' ? 'png' : 'jpg');
                $zip->addFromString($zeile['screenshot'], $bild['inhalt']);
            }
            $eintraege[] = $zeile;
            $h = static fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
            $html .= '<tr><td>' . $h($zeile['id']) . '</td><td>' . $h($zeile['erstellt_am']) . '<br>' . $h($zeile['von']) . '</td><td>'
                . $h($zeile['art_text']) . '<br>' . $h($zeile['status_text']) . '</td><td>' . nl2br($h($zeile['beschreibung']))
                . '<br><small>' . $h($zeile['seite']) . ($zeile['turnier'] ? ' · ' . $h($zeile['turnier']) : '') . '</small>'
                . ($zeile['notiz'] ? '<p><em>' . nl2br($h($zeile['notiz'])) . '</em></p>' : '') . '</td><td>'
                . ($zeile['screenshot'] ? '<a href="' . $h($zeile['screenshot']) . '"><img src="' . $h($zeile['screenshot']) . '" width="320"></a>' : '') . '</td></tr>';
        }
        $zip->addFromString('feedback.json', (string) json_encode($eintraege, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        $zip->addFromString('uebersicht.html', '<!doctype html><meta charset="utf-8"><title>Feedback</title><style>body{font-family:sans-serif}td{vertical-align:top;border-top:1px solid #ccc;padding:6px}</style>'
            . '<table><tr><th>#</th><th>Zeitpunkt</th><th>Art / Status</th><th>Beschreibung</th><th>Screenshot</th></tr>' . $html . '</table>');
        $zip->close();
        $inhalt = (string) file_get_contents($datei);
        @unlink($datei);
        return $inhalt;
    }

    /** @return array<string, mixed> */
    private function laden(int $id): array
    {
        return $this->db->eine('SELECT id FROM feedback WHERE id = ?', [$id]) ?? throw new FachFehler('Feedback nicht gefunden.');
    }

    /**
     * Prüft eine Data-URL (PNG/JPEG) und liefert [Base64, MIME] bzw. [null, null].
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function screenshotPruefen(mixed $dataUrl): array
    {
        if ($dataUrl === null || $dataUrl === '') {
            return [null, null];
        }
        if (!is_string($dataUrl) || !preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $t)) {
            throw new FachFehler('Der Screenshot ist kein gültiges Bild.');
        }
        $inhalt = base64_decode($t[2], true);
        if ($inhalt === false || strlen($inhalt) > self::MAX_BILD_BYTES) {
            throw new FachFehler('Der Screenshot ist zu groß (max. 4 MB).');
        }
        $info = @getimagesizefromstring($inhalt);
        if ($info === false || !isset(self::MIME[$info[2]])) {
            throw new FachFehler('Der Screenshot ist kein gültiges Bild.');
        }
        return [base64_encode($inhalt), self::MIME[$info[2]]];
    }

    /**
     * @param array<string, mixed> $f
     * @return array<string, mixed>
     */
    private static function exportZeile(array $f): array
    {
        return [
            'id' => (int) $f['id'],
            'erstellt_am' => $f['erstellt_am'],
            'art' => $f['art'],
            'art_text' => self::ARTEN[$f['art']] ?? $f['art'],
            'status' => $f['status'],
            'status_text' => self::STATUS[$f['status']] ?? $f['status'],
            'beschreibung' => $f['beschreibung'],
            'seite' => $f['seite'],
            'turnier_id' => $f['turnier_id'] === null ? null : (int) $f['turnier_id'],
            'turnier' => $f['turnier'],
            'von' => $f['von'],
            'geraet' => $f['geraet'],
            'notiz' => $f['notiz'],
            'geaendert_am' => $f['geaendert_am'],
        ];
    }

    private static function einzeilig(string $text, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/[\s\x00-\x1F\x7F]+/u', ' ', $text)), 0, $max);
    }
}
