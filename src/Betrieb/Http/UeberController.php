<?php

declare(strict_types=1);

namespace Wettkampf\Betrieb\Http;

use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Markdown;
use Wettkampf\Kern\Version;

/**
 * „Über diese Software“ (öffentlich): Zweck, Version, Urheber, Lizenz (AGPL-3.0), verwendete
 * Bibliotheken, Changelog und Lizenztext. Zugleich die „angemessenen rechtlichen Hinweise“ und
 * das Quellcode-Angebot im Sinne der AGPL (Abschnitte 5 und 13).
 */
final class UeberController extends Controller
{
    /** Urheber – müssen bei jeder Weitergabe genannt bleiben (NOTICE, AGPL 7 b) */
    /** Projektseite von Taikai – Karate. */
    public const WEBSITE = 'https://taikai-karate.de';

    public const URHEBER = ['Robert Göbel', 'Wolfgang Maier'];
    public const LIZENZ = 'GNU Affero General Public License, Version 3 oder später (AGPL-3.0-or-later)';
    /** Spendenseite des Projekts (PayPal); per Konfiguration 'spenden_url' änderbar, '' blendet aus */
    public const SPENDEN_URL = 'https://www.paypal.com/donate/?hosted_button_id=D6HGYUXLQUBKJ';

    public function index(Request $request): Response
    {
        return $this->seite('ueber/index', [
            'titel' => 'Über diese Software',
            'version' => Version::aktuell($this->app->wurzel),
            'syncProtokoll' => Version::SYNC_PROTOKOLL,
            'quellcode' => $this->quellcodeUrl(),
            'spenden' => self::spendenUrl($this->app->config),
            'bibliotheken' => $this->bibliotheken(),
            'changelog' => is_file($this->app->wurzel . '/CHANGELOG.md'),
        ]);
    }

    public function changelog(Request $request): Response
    {
        $datei = $this->app->wurzel . '/CHANGELOG.md';
        if (!is_file($datei)) {
            throw new NichtGefunden('Changelog nicht vorhanden.');
        }
        return $this->seite('ueber/changelog', [
            'titel' => 'Changelog',
            'html' => Markdown::html((string) file_get_contents($datei)),
            'version' => Version::aktuell($this->app->wurzel),
        ]);
    }

    public function lizenz(Request $request): Response
    {
        $datei = $this->app->wurzel . '/LICENSE';
        if (!is_file($datei)) {
            throw new NichtGefunden('Lizenztext nicht vorhanden.');
        }
        $hinweis = is_file($this->app->wurzel . '/NOTICE') ? (string) file_get_contents($this->app->wurzel . '/NOTICE') : '';
        return $this->seite('ueber/lizenz', ['titel' => 'Lizenz', 'text' => (string) file_get_contents($datei), 'hinweis' => $hinweis]);
    }

    /**
     * Spendenlink: Standard ist die Spendenseite des Projekts; die Konfiguration kann ihn ersetzen
     * oder mit '' ausblenden. Nur http(s).
     *
     * @param array<string, mixed> $config
     */
    public static function spendenUrl(array $config): ?string
    {
        $url = array_key_exists('spenden_url', $config) && $config['spenden_url'] !== null ? trim((string) $config['spenden_url']) : self::SPENDEN_URL;
        return preg_match('#^https?://[^\s"<>]+$#i', $url) === 1 ? $url : null;
    }

    /** Adresse des Quellcodes aus der Konfiguration ('quellcode_url'), nur http(s). */
    private function quellcodeUrl(): ?string
    {
        $url = trim((string) ($this->app->config['quellcode_url'] ?? ''));
        return preg_match('#^https?://[^\s"<>]+$#i', $url) === 1 ? $url : null;
    }

    /**
     * Laufzeit-Bibliotheken (ohne Entwicklungswerkzeuge) mit Lizenz aus vendor/composer/installed.json.
     *
     * @return list<array{name: string, version: string, lizenz: string}>
     */
    private function bibliotheken(): array
    {
        $datei = $this->app->wurzel . '/vendor/composer/installed.json';
        $daten = is_file($datei) ? json_decode((string) file_get_contents($datei), true) : null;
        if (!is_array($daten)) {
            return [];
        }
        $pakete = $daten['packages'] ?? $daten;
        $dev = array_flip((array) ($daten['dev-package-names'] ?? []));
        $liste = [];
        foreach ((array) $pakete as $p) {
            if (!is_array($p) || !isset($p['name']) || isset($dev[$p['name']])) {
                continue;
            }
            $liste[] = [
                'name' => (string) $p['name'],
                'version' => ltrim((string) ($p['version'] ?? ''), 'v'),
                'lizenz' => implode(', ', array_map('strval', (array) ($p['license'] ?? []))),
            ];
        }
        usort($liste, static fn($a, $b) => strcmp($a['name'], $b['name']));
        return $liste;
    }
}
