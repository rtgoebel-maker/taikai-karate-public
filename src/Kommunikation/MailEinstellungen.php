<?php

declare(strict_types=1);

namespace Wettkampf\Kommunikation;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Kern\Tresor;
use Wettkampf\Turnier\SystemEinstellungen;

/**
 * E-Mail-Versand aus der Oberfläche (System → E-Mail-Versand). Steht der Abschnitt 'mail' bzw. 'oeffentliche_url'
 * in config.php, gilt der (Betreiber legt fest) – sonst diese Einstellungen.
 *
 * Sicherheit: Passwort nur verschlüsselt gespeichert (Tresor) und nie wieder ausgegeben; nur Mail-Ports (kein Abtasten
 * interner Dienste über den Server, OWASP A10); öffentliche Adresse nur https (außer lokal); Änderungen bestätigt die
 * Administration mit ihrem Passwort (MailController) und sie stehen im Verlauf.
 */
final class MailEinstellungen
{
    public const PORTS = [587 => '587 (STARTTLS, üblich)', 465 => '465 (SSL)', 25 => '25', 2525 => '2525'];
    public const VERSCHLUESSELUNG = ['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', '' => 'keine (nur im eigenen Netz)'];
    public const TRANSPORTE = ['' => 'aus', 'smtp' => 'SMTP (Postfach)', 'datei' => 'nur in data/mail/ ablegen (Test)'];

    private const FELDER = ['transport', 'url', 'host', 'port', 'verschluesselung', 'benutzer', 'passwort', 'absender', 'absender_name', 'antwort_an'];

    private readonly SystemEinstellungen $system;

    public function __construct(private readonly Database $db, private readonly Tresor $tresor)
    {
        $this->system = new SystemEinstellungen($db);
    }

    /**
     * Gespeicherte Werte (Passwort entschlüsselt) – nur für den Versand, nie für die Anzeige.
     *
     * @return array{oeffentliche_url: string|null, mail: array<string, mixed>}
     */
    public function fuerVersand(): array
    {
        $w = $this->werte();
        return [
            'oeffentliche_url' => $w['url'] !== '' ? $w['url'] : null,
            'mail' => [
                'transport' => $w['transport'] !== '' ? $w['transport'] : null,
                'host' => $w['host'], 'port' => (int) $w['port'], 'verschluesselung' => $w['verschluesselung'],
                'benutzer' => $w['benutzer'], 'passwort' => $this->passwort() ?? '',
                'absender' => $w['absender'], 'absender_name' => $w['absender_name'], 'antwort_an' => $w['antwort_an'] !== '' ? $w['antwort_an'] : null,
            ],
        ];
    }

    /**
     * Für das Formular: ohne Passwort, nur ob eines gesetzt ist bzw. ob es sich nicht mehr entschlüsseln lässt.
     *
     * @return array<string, string|int|bool>
     */
    public function anzeige(): array
    {
        $w = $this->werte();
        $gespeichert = $this->system->wert('mail_passwort');
        unset($w['passwort']);
        return $w + ['passwort_gesetzt' => $gespeichert !== '', 'passwort_unlesbar' => $gespeichert !== '' && $this->passwort() === null];
    }

    /**
     * Speichern (geprüft). Leeres Passwortfeld lässt das bisherige unverändert.
     *
     * @param array<string, mixed> $daten
     * @param bool $adresseFest öffentliche Adresse steht in config.php – dann bleibt 'url' hier leer und fehlt nicht
     */
    public function speichern(array $daten, string $von, bool $adresseFest = false): void
    {
        $t = static fn(string $f, int $max) => mb_substr(trim((string) ($daten[$f] ?? '')), 0, $max);
        $neu = [
            'transport' => $t('transport', 10),
            'url' => rtrim($t('url', 300), '/'),
            'host' => strtolower($t('host', 200)),
            'port' => (string) (int) ($daten['port'] ?? 587),
            'verschluesselung' => $t('verschluesselung', 5),
            'benutzer' => $t('benutzer', 200),
            'absender' => strtolower($t('absender', 190)),
            'absender_name' => str_replace(["\r", "\n"], ' ', $t('absender_name', 100)),
            'antwort_an' => strtolower($t('antwort_an', 190)),
        ];
        if ($adresseFest) {
            $neu['url'] = '';
        }
        if (!isset(self::TRANSPORTE[$neu['transport']])) {
            throw new FachFehler('Unbekannte Versandart.');
        }
        if ($neu['url'] !== '') {
            $teile = parse_url($neu['url']);
            $lokal = in_array($teile['host'] ?? '', ['localhost', '127.0.0.1'], true) || preg_match('/^(10|192\.168|172\.(1[6-9]|2\d|3[01]))\./', $teile['host'] ?? '');
            if (!preg_match('#^https?://[^/?\#@\s]+(/[^?\#\s]*)?$#', $neu['url']) || (($teile['scheme'] ?? '') !== 'https' && !$lokal)) {
                throw new FachFehler('Die öffentliche Adresse muss mit https:// beginnen (z. B. https://turnier.example.de), ohne ? oder #.');
            }
        }
        if ($neu['transport'] !== '' && $neu['url'] === '' && !$adresseFest) {
            throw new FachFehler('Für den Versand bitte die öffentliche Adresse angeben – daraus entstehen die Links in den Mails.');
        }
        if ($neu['transport'] === 'smtp') {
            if (!preg_match('/^(?=.{1,200}$)[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $neu['host'])) {
                throw new FachFehler('Bitte den SMTP-Server als Namen angeben, z. B. smtp.example.de (ohne https:// und ohne Port).');
            }
            if (!isset(self::PORTS[(int) $neu['port']])) {
                throw new FachFehler('Erlaubt sind nur die Mail-Ports ' . implode(', ', array_keys(self::PORTS)) . '.');
            }
            if (!isset(self::VERSCHLUESSELUNG[$neu['verschluesselung']])) {
                throw new FachFehler('Unbekannte Verschlüsselung.');
            }
            if (filter_var($neu['absender'], FILTER_VALIDATE_EMAIL) === false) {
                throw new FachFehler('Bitte eine gültige Absenderadresse angeben.');
            }
        }
        if ($neu['antwort_an'] !== '' && filter_var($neu['antwort_an'], FILTER_VALIDATE_EMAIL) === false) {
            throw new FachFehler('Die Antwortadresse ist keine gültige E-Mail-Adresse.');
        }
        foreach ($neu as $feld => $wert) {
            $this->system->setzen('mail_' . $feld, $wert);
        }
        $passwort = (string) ($daten['passwort'] ?? '');
        if ($passwort !== '') {
            if (strlen($passwort) > 500) {
                throw new FachFehler('Das Passwort ist zu lang.');
            }
            $this->system->setzen('mail_passwort', $this->tresor->verschluesseln($passwort));
        }
        if (!empty($daten['passwort_loeschen'])) {
            $this->system->setzen('mail_passwort', '');
        }
        (new Protokoll($this->db))->eintragen(null, 'system', null, 'mail_einstellungen', [
            'transport' => $neu['transport'], 'host' => $neu['host'], 'benutzer' => $neu['benutzer'], 'absender' => $neu['absender'],
            'passwort_geaendert' => $passwort !== '', 'von' => $von,
        ]);
    }

    /** @return array<string, string> */
    private function werte(): array
    {
        $w = [];
        foreach (self::FELDER as $feld) {
            $w[$feld] = $this->system->wert('mail_' . $feld, match ($feld) {
                'port' => '587', 'verschluesselung' => 'tls', 'absender_name' => 'Taikai – Karate', default => '',
            });
        }
        return $w;
    }

    private function passwort(): ?string
    {
        $wert = $this->system->wert('mail_passwort');
        return $wert === '' ? null : $this->tresor->entschluesseln($wert);
    }
}
