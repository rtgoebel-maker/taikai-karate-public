<?php

declare(strict_types=1);

namespace Wettkampf\Betrieb;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Turnier\DemoBesitz;
use Wettkampf\Turnier\StammdatenService;
use Wettkampf\Turnier\SystemEinstellungen;

/**
 * Demo-Turniere regelmäßig zurücksetzen (Schulungs- und Demo-Installationen): einmal täglich zur eingestellten Uhrzeit
 * wird jedes Demo-Turnier gelöscht und mit denselben Einstellungen neu angelegt (DemoTurnier::kennung) – das Datum
 * rückt mit, Turnierleitungs-Rollen bleiben. Echte Turniere (ohne Kennung) werden nie angefasst.
 *
 * Unterbrechbar: „pausieren bis“ (z. B. mehrtägige Schulung) und Schutz vor laufender Benutzung – gab es in den letzten
 * RUHE_MINUTEN Änderungen in einem Demo-Turnier, wartet das Zurücksetzen. Ohne Cronjob: nach einer Anfrage geprüft (App).
 */
final class DemoZuruecksetzen
{
    public const AKTIV = 'demo_reset_aktiv';
    public const UHRZEIT = 'demo_reset_uhrzeit';
    public const PAUSE_BIS = 'demo_reset_pause_bis';
    public const LETZTER = 'demo_reset_letzter';
    public const UHRZEIT_STANDARD = '03:00';
    public const RUHE_MINUTEN = 60;

    public function __construct(private readonly Database $db) {}

    /** @return array{aktiv: bool, uhrzeit: string, pause_bis: string, letzter: string} */
    public function einstellungen(): array
    {
        $s = new SystemEinstellungen($this->db);
        $uhrzeit = $s->wert(self::UHRZEIT, self::UHRZEIT_STANDARD);
        return [
            'aktiv' => $s->wert(self::AKTIV, '0') === '1',
            'uhrzeit' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $uhrzeit) ? $uhrzeit : self::UHRZEIT_STANDARD,
            'pause_bis' => $s->wert(self::PAUSE_BIS),
            'letzter' => $s->wert(self::LETZTER),
        ];
    }

    public function speichern(bool $aktiv, string $uhrzeit, string $pauseBis): void
    {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $uhrzeit)) {
            throw new FachFehler(t('Bitte eine Uhrzeit im Format HH:MM angeben.'));
        }
        if ($pauseBis !== '' && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $pauseBis, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]))) {
            throw new FachFehler(t('Bitte ein gültiges Datum angeben.'));
        }
        $s = new SystemEinstellungen($this->db);
        $s->setzen(self::AKTIV, $aktiv ? '1' : '0');
        $s->setzen(self::UHRZEIT, $uhrzeit);
        $s->setzen(self::PAUSE_BIS, $pauseBis);
        (new Protokoll($this->db))->eintragen(null, 'system', null, 'demo_zuruecksetzen_eingestellt', ['aktiv' => $aktiv, 'uhrzeit' => $uhrzeit, 'pause_bis' => $pauseBis]);
    }

    /**
     * Nächster geplanter Zeitpunkt (Y-m-d H:i) oder null (aus bzw. Demo-Modus aus).
     */
    public function naechster(?int $jetzt = null): ?string
    {
        $e = $this->einstellungen();
        if (!$e['aktiv'] || !(new SystemEinstellungen($this->db))->demoModus()) {
            return null;
        }
        $jetzt ??= time();
        $tag = date('Y-m-d', $jetzt);
        if ($e['pause_bis'] !== '' && $e['pause_bis'] >= $tag) {
            $tag = date('Y-m-d', (int) strtotime($e['pause_bis'] . ' +1 day'));
        } elseif ($e['letzter'] >= "$tag {$e['uhrzeit']}") {
            $tag = date('Y-m-d', (int) strtotime("$tag +1 day"));
        }
        return "$tag {$e['uhrzeit']}";
    }

    /**
     * Nach einer Anfrage: zurücksetzen, wenn fällig und gerade niemand in einem Demo-Turnier arbeitet.
     *
     * @return int|null Anzahl neu angelegter Demo-Turniere, null = nicht fällig bzw. verschoben
     */
    public function beiBedarf(?int $jetzt = null): ?int
    {
        $jetzt ??= time();
        $naechster = $this->naechster($jetzt);
        if ($naechster === null || date('Y-m-d H:i', $jetzt) < $naechster) {
            return null;
        }
        $ruheGrenze = date('Y-m-d H:i:s', $jetzt - self::RUHE_MINUTEN * 60);
        foreach ($this->demoTurniere() as $t) {
            if ($this->db->wert('SELECT 1 FROM audit_log WHERE turnier_id = ? AND zeit >= ? LIMIT 1', [$t['id'], $ruheGrenze]) !== null) {
                return null; // Schulung läuft – später erneut prüfen
            }
        }
        return $this->jetzt($jetzt);
    }

    /**
     * Demo-Turniere sofort zurücksetzen – alle (Administration, täglicher Lauf) bzw. nur die eines Demo-Zugangs.
     *
     * @param int|null $nurBesitzer nur Turniere dieses Kontos (Demo-Nutzer setzt seine eigenen zurück)
     * @return int Anzahl neu angelegter Demo-Turniere
     */
    public function jetzt(?int $jetzt = null, ?int $nurBesitzer = null): int
    {
        $jetzt ??= time();
        if ($nurBesitzer === null) {
            // zuerst vermerken: Schlägt das Anlegen fehl, wird es nicht bei jeder Anfrage erneut versucht
            (new SystemEinstellungen($this->db))->setzen(self::LETZTER, date('Y-m-d H:i:s', $jetzt));
        }
        $demo = new DemoTurnier($this->db);
        $stamm = new StammdatenService($this->db);
        $anzahl = 0;
        $neueIds = []; // alt → neu: Logos aus einem Demo-Turnier, das im selben Lauf neu angelegt wird
        foreach ($this->demoTurniere() as $t) {
            $k = DemoTurnier::kennung($t);
            if ($k === null || ($nurBesitzer !== null && DemoBesitz::besitzer($t) !== $nurBesitzer)) {
                continue;
            }
            if (DemoBesitz::loeschenAb($t) !== null) {
                continue; // zum Löschen vorgemerkt (Demo-Zugang entzogen) – nicht neu anlegen
            }
            $besitzer = DemoBesitz::besitzer($t);
            $rollen = $this->db->alle('SELECT benutzer_id, rolle FROM turnier_rolle WHERE turnier_id = ?', [$t['id']]);
            // selbst hochgeladene Logos (Urkunden zeigen) bleiben erhalten
            $eigeneLogos = $this->db->alle('SELECT art, mime, daten, breite, hoehe FROM turnier_logo WHERE turnier_id = ?', [$t['id']]);
            $stamm->turnierLoeschen((int) $t['id']);
            $quelle = $k['logos_von'] === null ? null : ($neueIds[$k['logos_von']] ?? $k['logos_von']);
            $logos = $quelle !== null && $this->db->wert('SELECT 1 FROM turnier WHERE id = ?', [$quelle]) !== null ? $quelle : null;
            $neu = $demo->anlegen(date('Y-m-d', (int) strtotime(date('Y-m-d', $jetzt) . ' ' . ($k['tage'] >= 0 ? '+' : '') . $k['tage'] . ' days')), $k['name'], $k['teams'], $logos, $k['phase']);
            if ($eigeneLogos !== [] && $this->db->wert('SELECT 1 FROM turnier_logo WHERE turnier_id = ?', [$neu['turnier']]) === null) {
                foreach ($eigeneLogos as $l) {
                    $this->db->insert('turnier_logo', $l + ['turnier_id' => $neu['turnier'], 'geaendert_am' => date('Y-m-d H:i:s', $jetzt)]);
                }
            }
            if ($besitzer !== null) {
                (new DemoBesitz($this->db))->besitzerSetzen((int) $neu['turnier'], $besitzer);
            }
            foreach ($rollen as $r) {
                $this->db->insert('turnier_rolle', ['turnier_id' => $neu['turnier'], 'benutzer_id' => $r['benutzer_id'], 'rolle' => $r['rolle'], 'erstellt_am' => date('Y-m-d H:i:s', $jetzt), 'erstellt_von' => 'Demo-Zurücksetzen']);
            }
            $neueIds[(int) $t['id']] = (int) $neu['turnier'];
            $anzahl++;
        }
        // Quelle der Logos erst später im Lauf neu angelegt → Kennung auf die neue ID (sonst morgen ohne Logos)
        foreach ($neueIds as $neueId) {
            $zeile = $this->db->eine('SELECT einstellungen FROM turnier WHERE id = ?', [$neueId]);
            $e = json_decode((string) ($zeile['einstellungen'] ?? ''), true);
            $alt = is_array($e) && is_array($e['demo'] ?? null) ? (int) ($e['demo']['logos_von'] ?? 0) : 0;
            if ($alt > 0 && isset($neueIds[$alt])) {
                $e['demo']['logos_von'] = $neueIds[$alt];
                $this->db->update('turnier', ['einstellungen' => json_encode($e, JSON_UNESCAPED_UNICODE)], ['id' => $neueId]);
            }
        }
        (new Protokoll($this->db))->eintragen(null, 'system', null, 'demo_zurueckgesetzt', ['turniere' => $anzahl, 'besitzer' => $nurBesitzer]);
        return $anzahl;
    }

    /** @return list<array<string, mixed>> Demo-Turniere (mit Kennung) */
    public function demoTurniere(): array
    {
        return array_values(array_filter(
            $this->db->alle("SELECT * FROM turnier WHERE einstellungen LIKE '%\"demo\"%' ORDER BY id"),
            static fn(array $t): bool => DemoTurnier::kennung($t) !== null,
        ));
    }
}
