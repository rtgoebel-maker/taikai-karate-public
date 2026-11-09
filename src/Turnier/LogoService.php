<?php

declare(strict_types=1);

namespace Wettkampf\Turnier;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;

/**
 * Bilder je Turnier für die Urkunden: Logos (Verband, Verein und Dojo des
 * Ausrichters) und die beiden Unterschriften.
 * Gespeichert als Base64 in der Datenbank (Export/Umzug inklusive). Nur
 * Rasterbilder – SVG ist ausgeschlossen, weil es Skripte enthalten kann.
 */
final class LogoService
{
    public const ARTEN = [
        'verband' => 'Verbandslogo',
        'verein' => 'Vereinslogo des Ausrichters',
        'dojo' => 'Dojo-Logo des Ausrichters',
    ];
    public const UNTERSCHRIFTEN = [
        'unterschrift_links' => 'Unterschrift links',
        'unterschrift_rechts' => 'Unterschrift rechts',
    ];
    /** Eigenes Hintergrundbild der Urkunde (ersetzt Rahmen und Wasserzeichen). */
    public const HINTERGRUND = ['hintergrund' => 'Hintergrundbild der Urkunde'];
    public const MAX_BYTES = 2 * 1024 * 1024;
    /** Hintergrund: ein ganzes A4-Bild darf größer sein. */
    public const MAX_BYTES_HINTERGRUND = 5 * 1024 * 1024;
    private const MIME = [IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_WEBP => 'image/webp', IMAGETYPE_GIF => 'image/gif'];

    public function __construct(private readonly Database $db) {}

    /** Speichert (bzw. ersetzt) ein Logo aus einer hochgeladenen Datei. */
    public function speichern(int $turnierId, string $art, string $pfad): void
    {
        $name = (self::ARTEN + self::UNTERSCHRIFTEN + self::HINTERGRUND)[$art] ?? throw new FachFehler('Unbekannte Logo-Art.');
        $max = self::maxBytes($art);
        $groesse = is_file($pfad) ? (int) filesize($pfad) : 0;
        if ($groesse === 0) {
            throw new FachFehler($name . ': Datei ist leer oder fehlt.');
        }
        if ($groesse > $max) {
            throw new FachFehler($name . ': Datei ist zu groß (max. ' . intdiv($max, 1024 * 1024) . ' MB).');
        }
        $info = @getimagesize($pfad);
        if ($info === false || !isset(self::MIME[$info[2]])) {
            throw new FachFehler($name . ': bitte ein Bild als PNG, JPG, WebP oder GIF hochladen.');
        }
        $felder = [
            'mime' => self::MIME[$info[2]],
            'daten' => base64_encode((string) file_get_contents($pfad)),
            'breite' => (int) $info[0],
            'hoehe' => (int) $info[1],
            'geaendert_am' => date('Y-m-d H:i:s'),
        ];
        $this->db->transaktion(function () use ($turnierId, $art, $felder) {
            $this->db->delete('turnier_logo', ['turnier_id' => $turnierId, 'art' => $art]);
            $this->db->insert('turnier_logo', $felder + ['turnier_id' => $turnierId, 'art' => $art]);
            (new Protokoll($this->db))->eintragen($turnierId, 'turnier', $turnierId, 'logo', ['art' => $art, 'breite' => $felder['breite'], 'hoehe' => $felder['hoehe']]);
        });
    }

    public static function maxBytes(string $art): int
    {
        return isset(self::HINTERGRUND[$art]) ? self::MAX_BYTES_HINTERGRUND : self::MAX_BYTES;
    }

    public function entfernen(int $turnierId, string $art): void
    {
        $this->db->delete('turnier_logo', ['turnier_id' => $turnierId, 'art' => $art]);
    }

    /**
     * Vorhandene Logos (ohne Bilddaten).
     *
     * @return array<string, array{breite: int, hoehe: int, version: string}> Art → Angaben
     */
    public function vorhandene(int $turnierId): array
    {
        $liste = [];
        foreach ($this->db->alle('SELECT art, breite, hoehe, geaendert_am FROM turnier_logo WHERE turnier_id = ?', [$turnierId]) as $z) {
            $liste[$z['art']] = ['breite' => (int) $z['breite'], 'hoehe' => (int) $z['hoehe'], 'version' => preg_replace('/\D/', '', $z['geaendert_am'])];
        }
        return $liste;
    }

    /** @return array{mime: string, inhalt: string}|null */
    public function bild(int $turnierId, string $art): ?array
    {
        $z = $this->db->eine('SELECT mime, daten FROM turnier_logo WHERE turnier_id = ? AND art = ?', [$turnierId, $art]);
        return $z === null ? null : ['mime' => $z['mime'], 'inhalt' => (string) base64_decode($z['daten'], true)];
    }
}
