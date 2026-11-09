<?php

declare(strict_types=1);

namespace Wettkampf\Turnier;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;

/**
 * Einstellungen, die für alle Turniere gelten (Tabelle einstellung, Schlüssel → Wert).
 */
final class SystemEinstellungen
{
    public const FEEDBACK = 'feedback_aktiv';
    public const DEMO = 'demo_modus';
    /** Selbst angelegte Dojo-Zugänge (Online-Anmeldung) gelten so viele Monate nach dem Turnier. */
    public const KONTO_MONATE = 'konto_monate';
    public const KONTO_MONATE_STANDARD = 3;
    /** Selbst angelegter Dojo-Zugang gilt für mehrere Turniere (Standard: nur das Turnier, für das er angelegt wurde). */
    public const DOJO_MEHRERE_TURNIERE = 'dojo_zugang_mehrere_turniere';
    /** Zweiter Faktor bei der Anmeldung für alle Konten Pflicht (Standard: ja). */
    public const ZWEITER_FAKTOR_PFLICHT = 'zweiter_faktor_pflicht';
    public const FARBSCHEMA = 'farbschema';
    public const FARBE_EIGENE = 'farbe_eigene';

    /** Farbschemata: Schlüssel → [Name, Hauptfarbe]. Signalfarben (Aka, Erfolg, Warnung) bleiben fest. */
    public const FARBSCHEMATA = [
        'marine' => ['Marineblau (Standard)', '#1d2b45'],
        'royal' => ['Königsblau', '#1e4b8f'],
        'petrol' => ['Petrol', '#0f5e6b'],
        'wald' => ['Waldgrün', '#1f5130'],
        'bordeaux' => ['Bordeaux', '#6b1d2a'],
        'violett' => ['Violett', '#4b2a7a'],
        'anthrazit' => ['Anthrazit', '#2f3437'],
    ];

    /** Bereiche der Statusseite neben „Tatamis live“, die sich ausblenden lassen: Bereich → Schlüssel. */
    public const STATUS_BEREICHE = ['starterlisten' => 'status_starterlisten', 'ergebnisse' => 'status_ergebnisse'];

    /** Logos des Ausrichters, die auf der Statusseite erscheinen können: Art → Schlüssel. */
    public const STATUS_LOGOS = ['verein' => 'status_logo_verein', 'dojo' => 'status_logo_dojo'];

    public function __construct(private readonly Database $db) {}

    public function wert(string $schluessel, string $standard = ''): string
    {
        $wert = $this->db->wert('SELECT wert FROM einstellung WHERE schluessel = ?', [$schluessel]);
        return $wert === null ? $standard : (string) $wert;
    }

    public function setzen(string $schluessel, string $wert): void
    {
        $this->db->transaktion(function () use ($schluessel, $wert) {
            $this->db->delete('einstellung', ['schluessel' => $schluessel]);
            $this->db->insert('einstellung', ['schluessel' => $schluessel, 'wert' => $wert]);
        });
    }

    /** Feedback-Knopf auf allen Seiten der Leitung und auf den Tablets (Standard: aus). */
    public function feedbackAktiv(): bool
    {
        return $this->wert(self::FEEDBACK, '0') === '1';
    }

    /** Monate nach dem Turnier, bis ein selbst angelegter Dojo-Zugang gelöscht wird (1–24, Standard 3). */
    public function kontoMonate(): int
    {
        return max(1, min(24, (int) $this->wert(self::KONTO_MONATE, (string) self::KONTO_MONATE_STANDARD)));
    }

    public function dojoZugangMehrereTurniere(): bool
    {
        return $this->wert(self::DOJO_MEHRERE_TURNIERE, '0') === '1';
    }

    public function zweiterFaktorPflicht(): bool
    {
        return $this->wert(self::ZWEITER_FAKTOR_PFLICHT, '1') !== '0';
    }

    /** Ablaufdatum eines Dojo-Zugangs für ein Turnier am $turnierDatum (Y-m-d). */
    public function kontoAblauf(string $turnierDatum): string
    {
        return date('Y-m-d', (int) strtotime($turnierDatum . ' +' . $this->kontoMonate() . ' months'));
    }

    /** Demo-Modus: unter System lassen sich Demo-Turniere anlegen (Standard: aus). */
    public function demoModus(): bool
    {
        return $this->wert(self::DEMO, '0') === '1';
    }

    /**
     * Gewähltes Farbschema.
     *
     * @return array{schema: string, primaer: string, hell: string, eigene: string}
     */
    public function farben(): array
    {
        $schema = $this->wert(self::FARBSCHEMA, 'marine');
        $eigene = $this->wert(self::FARBE_EIGENE, '#1d2b45');
        $primaer = $schema === 'eigene' ? $eigene : (self::FARBSCHEMATA[$schema][1] ?? self::FARBSCHEMATA['marine'][1]);
        return ['schema' => isset(self::FARBSCHEMATA[$schema]) || $schema === 'eigene' ? $schema : 'marine', 'primaer' => $primaer, 'hell' => self::hell($primaer), 'eigene' => $eigene];
    }

    /** Speichert das Farbschema; eine eigene Farbe muss dunkel genug für weiße Schrift sein. */
    public function farbenSetzen(string $schema, string $eigene): void
    {
        if ($schema !== 'eigene' && !isset(self::FARBSCHEMATA[$schema])) {
            throw new FachFehler('Unbekanntes Farbschema.');
        }
        $eigene = strtolower(trim($eigene));
        if (!preg_match('/^#[0-9a-f]{6}$/', $eigene)) {
            throw new FachFehler('Bitte die eigene Farbe als Hexwert angeben, z. B. #1d2b45.');
        }
        if ($schema === 'eigene' && self::kontrastZuWeiss($eigene) < 4.5) {
            throw new FachFehler('Die eigene Farbe ist zu hell – weiße Schrift in Kopfzeile und Knöpfen wäre schlecht lesbar. Bitte eine dunklere Farbe wählen.');
        }
        $this->setzen(self::FARBSCHEMA, $schema);
        $this->setzen(self::FARBE_EIGENE, $eigene);
    }

    /** CSS für alle Oberflächen; leer beim Standardschema. */
    public function farbenCss(): string
    {
        $f = $this->farben();
        if (strtolower($f['primaer']) === self::FARBSCHEMATA['marine'][1]) {
            return '';
        }
        return ":root{--primaer:{$f['primaer']};--primaer-hell:{$f['hell']};}";
    }

    /** @return list<string> Logo-Arten, die auf der Statusseite erscheinen sollen */
    public function statusLogos(): array
    {
        return array_keys(array_filter(self::STATUS_LOGOS, fn($schluessel) => $this->wert($schluessel, '0') === '1'));
    }

    /** @return array{starterlisten: bool, ergebnisse: bool} sichtbare Bereiche der Statusseite (Standard: alle) */
    public function statusBereiche(): array
    {
        return array_map(fn($schluessel) => $this->wert($schluessel, '1') === '1', self::STATUS_BEREICHE);
    }

    /** Helle Fläche zur Hauptfarbe (12 % Farbe auf Weiß), z. B. für Hover und Markierungen. */
    public static function hell(string $hex): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        return sprintf('#%02x%02x%02x', ...array_map(static fn($c) => (int) round($c * .12 + 255 * .88), [$r, $g, $b]));
    }

    /** Kontrastverhältnis nach WCAG gegenüber Weiß. */
    public static function kontrastZuWeiss(string $hex): float
    {
        $kanal = static function (int $c): float {
            $c /= 255;
            return $c <= .03928 ? $c / 12.92 : (($c + .055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $l = .2126 * $kanal($r) + .7152 * $kanal($g) + .0722 * $kanal($b);
        return 1.05 / ($l + .05);
    }
}
