<?php

declare(strict_types=1);

namespace Wettkampf\Betrieb;

use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Meldung\AnmeldungService;
use Wettkampf\Meldung\RechnungService;

/**
 * Einrichtungs-Assistent für ein neues Turnier: Reihenfolge der Schritte und ob ein Schritt erledigt ist.
 * Die Formulare der Schritte speichern über dieselben Stellen wie die normalen Seiten (mit allen Prüfungen);
 * der Assistent führt nur und zeigt den Stand.
 */
final class TurnierAssistent
{
    /** Schritt => [Titel, Kurzbeschreibung] */
    public const SCHRITTE = [
        'grunddaten' => ['Grunddaten', 'Name, Datum, Ort, Tatamis, Meldeschluss'],
        'klassen' => ['Klassen', 'Altersklassen und Disziplinen aus einer Vorlage'],
        'tatamis' => ['Tatamis & PINs', 'PINs für Tablets, Leitungs-PIN, Kampfuhr'],
        'anmeldung' => ['Online-Anmeldung', 'Startgeld, Hinweise, Zahlung vor Freigabe'],
        'zahlung' => ['Zahlungsangaben', 'Empfänger, Bankverbindung, Zahlungsziel'],
        'portal' => ['Coaching-Portal', 'Was Coaches über ihre Starter sehen'],
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
        $phase = TurnierPhase::von($turnier);

        return [
            'grunddaten' => ['erledigt' => true, 'text' => (string) ($turnier['name'] ?? '') . ' · ' . (int) ($turnier['anzahl_tatamis'] ?? 0) . ' Tatamis'
                . (!empty($turnier['meldeschluss']) ? ' · Meldeschluss ' . date('d.m.Y', (int) strtotime($turnier['meldeschluss'])) : ' · ohne Meldeschluss')],
            'klassen' => ['erledigt' => $klassen > 0, 'text' => $klassen > 0 ? "$klassen Klassen" : 'noch keine Klassen'],
            'tatamis' => [
                'erledigt' => $ohnePin === 0 && $tatamis !== [] && !empty($turnier['leitung_pin_hash']),
                'text' => ($ohnePin === 0 ? 'alle Tatami-PINs gesetzt' : "$ohnePin Tatami(s) ohne PIN") . ' · ' . (empty($turnier['leitung_pin_hash']) ? 'Leitungs-PIN fehlt' : 'Leitungs-PIN gesetzt'),
            ],
            'anmeldung' => [
                'erledigt' => isset($e['anmeldung']),
                'text' => isset($e['anmeldung']) ? ($mitStartgeld ? 'Startgeld festgelegt' : 'ohne Startgeld') : 'noch nicht eingestellt',
            ],
            'zahlung' => [
                'erledigt' => $mitStartgeld ? ($zahlung['absender'] !== '' && $zahlung['bank'] !== '') : null,
                'text' => !$mitStartgeld ? 'entfällt (kein Startgeld)' : ($zahlung['bank'] !== '' ? 'Bankverbindung hinterlegt' : 'Bankverbindung fehlt'),
            ],
            'portal' => [
                'erledigt' => $coach !== null,
                'text' => $coach === null ? 'noch nicht eingestellt' : (!empty($coach['aktiv']) ? 'freigegeben' : 'ausgeschaltet'),
            ],
            'fertig' => [
                'erledigt' => $phase !== TurnierPhase::VORBEREITUNG,
                'text' => 'Phase: ' . TurnierPhase::text($phase),
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
