<?php

declare(strict_types=1);

namespace Wettkampf\Betrieb;

use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Sprache;
use Wettkampf\Meldung\AnmeldungService;
use Wettkampf\Meldung\RechnungService;

/**
 * Einrichtungs-Assistent für ein neues Turnier: Reihenfolge der Schritte und ob ein Schritt erledigt ist.
 * Die Formulare der Schritte speichern über dieselben Stellen wie die normalen Seiten (mit allen Prüfungen);
 * der Assistent führt nur und zeigt den Stand.
 */
final class TurnierAssistent
{
    /** Schritt => [Titel, Kurzbeschreibung] (deutsch; übersetzt beim Anzeigen mit t(), Einträge in lang/en/turnier.php) */
    public const SCHRITTE = [
        'grunddaten' => ['Grunddaten', 'Name, Datum, Ort, Tatamis, Meldeschluss'],
        'klassen' => ['Klassen', 'Altersklassen und Disziplinen aus einer Vorlage'],
        'tatamis' => ['Tatamis & Leitungs-PIN', 'Bezeichnungen, Leitungs-PIN für Korrekturen, Kampfuhr'],
        'geraete' => ['Tablets & Ausruf', 'Geräte per QR-Code koppeln, PIN nur als Notlösung'],
        'anmeldung' => ['Online-Anmeldung', 'Startgeld, Hinweise, Zahlung vor Freigabe'],
        'zahlung' => ['Zahlungsangaben', 'Empfänger, Bankverbindung, Zahlungsziel'],
        'portal' => ['Coaching-Portal', 'Was Coaches über ihre Starter sehen'],
        'datenschutz' => ['Datenschutz', 'Datenschutzhinweise, Löschfrist, Pseudonymisierung'],
        'urkunden' => ['Urkunden', 'Logos, Unterschriften, Hintergrund – mit Vorschau'],
        'fertig' => ['Fertig', 'Überblick und Meldephase starten'],
    ];

    public function __construct(private readonly Database $db) {}

    public static function gueltig(string $schritt): bool
    {
        return isset(self::SCHRITTE[$schritt]);
    }

    public static function naechster(string $schritt): string
    {
        $liste = array_keys(self::SCHRITTE);
        $i = array_search($schritt, $liste, true);
        return $liste[min(count($liste) - 1, ($i === false ? 0 : $i) + 1)];
    }

    public static function vorheriger(string $schritt): ?string
    {
        $liste = array_keys(self::SCHRITTE);
        $i = array_search($schritt, $liste, true);
        return $i === false || $i === 0 ? null : $liste[$i - 1];
    }

    /**
     * Stand je Schritt: erledigt (true), offen (false) oder entfällt (null) – mit kurzer Begründung.
     *
     * @return array<string, array{erledigt: bool|null, text: string}>
     */
    public function stand(int $turnierId): array
    {
        $turnier = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$turnierId]) ?? [];
        $e = json_decode((string) ($turnier['einstellungen'] ?? ''), true) ?: [];
        $klassen = (int) $this->db->wert("SELECT COUNT(*) FROM klasse WHERE turnier_id = ? AND status <> 'zusammengelegt'", [$turnierId]);
        $tatamis = $this->db->alle('SELECT pin_hash FROM tatami WHERE turnier_id = ?', [$turnierId]);
        $ohnePin = count(array_filter($tatamis, static fn(array $t) => empty($t['pin_hash'])));
        $anmeldung = (new AnmeldungService($this->db))->einstellungen($turnier);
        $mitStartgeld = max($anmeldung['preis_kata'], $anmeldung['preis_kumite'], $anmeldung['preis_kata_team'], $anmeldung['preis_kumite_team']) > 0;
        $zahlung = (new RechnungService($this->db))->einstellungen($turnier);
        $coach = $e['coach'] ?? null;
        $pinErlaubt = (int) ($turnier['tisch_pin_erlaubt'] ?? 0) === 1;
        $logos = (int) $this->db->wert('SELECT COUNT(*) FROM turnier_logo WHERE turnier_id = ?', [$turnierId]);
        $phase = TurnierPhase::von($turnier);

        return [
            'grunddaten' => ['erledigt' => true, 'text' => (string) ($turnier['name'] ?? '') . ' · ' . t('{n} Tatamis', ['n' => (int) ($turnier['anzahl_tatamis'] ?? 0)])
                . (!empty($turnier['meldeschluss']) ? ' · ' . t('Meldeschluss {datum}', ['datum' => Sprache::datum(date('Y-m-d', (int) strtotime($turnier['meldeschluss'])))]) : ' · ' . t('ohne Meldeschluss'))],
            'klassen' => ['erledigt' => $klassen > 0, 'text' => $klassen > 0 ? t('{n} Klassen', ['n' => $klassen]) : t('noch keine Klassen')],
            'tatamis' => [
                // Tatami-PINs zählen nur, wenn die PIN-Anmeldung (Notlösung) eingeschaltet ist
                'erledigt' => $tatamis !== [] && !empty($turnier['leitung_pin_hash']) && (!$pinErlaubt || $ohnePin === 0),
                'text' => (empty($turnier['leitung_pin_hash']) ? t('Leitungs-PIN fehlt') : t('Leitungs-PIN gesetzt'))
                    . ($pinErlaubt ? ' · ' . ($ohnePin === 0 ? t('alle Tatami-PINs gesetzt') : t('{n} Tatami(s) ohne PIN', ['n' => $ohnePin])) : ''),
            ],
            'geraete' => [
                'erledigt' => true,
                'text' => $pinErlaubt ? t('PIN-Notlösung eingeschaltet') : t('nur QR-Code mit Freigabe (empfohlen)'),
            ],
            'anmeldung' => [
                'erledigt' => isset($e['anmeldung']),
                'text' => isset($e['anmeldung']) ? ($mitStartgeld ? t('Startgeld festgelegt') : t('ohne Startgeld')) : t('noch nicht eingestellt'),
            ],
            'zahlung' => [
                'erledigt' => $mitStartgeld ? ($zahlung['absender'] !== '' && $zahlung['bank'] !== '') : null,
                'text' => !$mitStartgeld ? t('entfällt (kein Startgeld)') : ($zahlung['bank'] !== '' ? t('Bankverbindung hinterlegt') : t('Bankverbindung fehlt')),
            ],
            'portal' => [
                'erledigt' => $coach !== null,
                'text' => $coach === null ? t('noch nicht eingestellt') : (!empty($coach['aktiv']) ? t('freigegeben') : t('ausgeschaltet')),
            ],
            'datenschutz' => [
                'erledigt' => isset($e['datenschutz']['loeschfrist_wochen']),
                'text' => isset($e['datenschutz']['loeschfrist_wochen']) ? t('eingerichtet') : t('noch nicht eingerichtet'),
            ],
            'urkunden' => [
                'erledigt' => isset($e['urkunde']) || $logos > 0,
                'text' => $logos > 0 ? t('{n} Bilder hochgeladen', ['n' => $logos]) : (isset($e['urkunde']) ? t('Texte eingestellt') : t('Standard (ohne Logos)')),
            ],
            'fertig' => [
                'erledigt' => $phase !== TurnierPhase::VORBEREITUNG,
                'text' => t('Phase: {phase}', ['phase' => TurnierPhase::text($phase)]),
            ],
        ];
    }

    /** Erster noch offener Schritt (für „Assistent fortsetzen“). */
    public function ersterOffener(int $turnierId): string
    {
        foreach ($this->stand($turnierId) as $schritt => $s) {
            if ($s['erledigt'] === false) {
                return $schritt;
            }
        }
        return 'fertig';
    }
}
