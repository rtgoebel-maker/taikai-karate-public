<?php

declare(strict_types=1);

namespace Wettkampf\Kommunikation;

use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Mail\Postausgang;
use Wettkampf\Kern\Version;

/**
 * Feedback-Meldungen per Mail an das Taikai-Team (Standard feedback@taikai-karate.de, mit config 'feedback_adresse'
 * änderbar, z. B. für eigene Weiterentwicklungen). Die Meldung bleibt zusätzlich in der eigenen Feedback-Liste.
 * Der Feedback-Knopf setzt deshalb einen eingerichteten E-Mail-Versand voraus (App::feedbackAktiv).
 */
final class FeedbackMail
{
    public const ADRESSE = 'feedback@taikai-karate.de';

    public function __construct(private readonly Database $db, private readonly Postausgang $post, private readonly string $adresse = self::ADRESSE) {}

    /** @param array<string, mixed> $config */
    public static function adresse(array $config): string
    {
        $a = trim((string) ($config['feedback_adresse'] ?? self::ADRESSE));
        return filter_var($a, FILTER_VALIDATE_EMAIL) !== false ? $a : self::ADRESSE;
    }

    /** Meldung (Zeile aus feedback) einreihen; Screenshot als Anhang. */
    public function senden(int $feedbackId, ?string $antwortAn): void
    {
        $f = $this->db->eine('SELECT * FROM feedback WHERE id = ?', [$feedbackId]);
        if ($f === null) {
            return;
        }
        $turnier = $f['turnier_id'] !== null ? (string) ($this->db->wert('SELECT name FROM turnier WHERE id = ?', [$f['turnier_id']]) ?? '') : '';
        $art = FeedbackService::ARTEN[$f['art']] ?? (string) $f['art'];
        $text = "Neue Feedback-Meldung aus einer Taikai-Installation\n\n"
            . "Art:          $art\n"
            . 'Seite:        ' . $this->post->link((string) $f['seite']) . "\n"
            . ($turnier !== '' ? "Turnier:      $turnier\n" : '')
            . "Von:          {$f['von']}\n"
            . ($antwortAn !== null ? "Rückfragen:   $antwortAn\n" : '')
            . 'Gerät:        ' . ($f['geraet'] ?? '–') . "\n"
            . 'Version:      ' . Version::aktuell() . "\n"
            . 'Installation: ' . $this->post->link('') . " (Meldung Nr. {$f['id']})\n\n"
            . "Beschreibung:\n" . $f['beschreibung'] . "\n"
            . ($f['screenshot'] ? "\nScreenshot im Anhang.\n" : '')
            . "\n-- \nVerschickt über den Feedback-Knopf von Taikai – Karate.";
        $anhaenge = [];
        if ($f['screenshot']) {
            $daten = base64_decode((string) $f['screenshot'], true);
            if ($daten !== false) {
                $anhaenge[] = ['name' => 'screenshot-' . $f['id'] . ($f['screenshot_mime'] === 'image/jpeg' ? '.jpg' : '.png'), 'mime' => (string) $f['screenshot_mime'], 'daten' => $daten];
            }
        }
        $kurz = mb_substr((string) preg_replace('/\s+/', ' ', (string) $f['beschreibung']), 0, 60);
        $this->post->einreihen($this->adresse, "[Taikai Feedback] $art: $kurz", $text, 'feedback', $f['turnier_id'] !== null ? (int) $f['turnier_id'] : null, $anhaenge);
    }
}
