<?php

declare(strict_types=1);

namespace Wettkampf\Meldung;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Domain\Graduierung;
use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Domain\Alter;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Identitaet\Konten;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Kern\Uuid;
use Wettkampf\Turnier\StammdatenService;

/**
 * Online-Anmeldung der Dojos: Formulardaten prüfen, Preis berechnen,
 * Anmeldung speichern (mit Bearbeitungscode) und nach Freigabe durch die
 * Wettkampfleitung als Verein, Teilnehmer und Teams übernehmen.
 *
 * Datenformat (Spalte `daten`, JSON):
 *   teilnehmer: { <schlüssel>: {vorname, nachname, geschlecht, geburtsdatum, graduierung, kata: bool, kumite: ''|'kumite'|'kumite:<form>'} }
 *   teams:      [ {name, disziplin: kata_team|kumite_team, mitglieder: [<schlüssel> ×3], ersatz: <schlüssel>|null} ]
 */
final class AnmeldungService
{
    public const STATUS = ['eingereicht' => 'eingereicht', 'freigegeben' => 'freigegeben', 'abgelehnt' => 'abgelehnt'];
    public const KUMITE = ['kumite', 'kumite:kihon_ippon', 'kumite:jiyu_ippon', 'kumite:jiyu'];
    /** Preisposten: Schlüssel der Einstellung → Bezeichnung */
    public const POSTEN = [
        'preis_kata' => 'Kata Einzel',
        'preis_kumite' => 'Kumite Einzel',
        'preis_kata_team' => 'Kata Team',
        'preis_kumite_team' => 'Kumite Team',
    ];

    private Protokoll $protokoll;

    public function __construct(private readonly Database $db)
    {
        $this->protokoll = new Protokoll($db);
    }

    // --- Anmeldelink -------------------------------------------------------------

    /**
     * Zufallsschlüssel des Anmeldeformulars (/melden/{schluessel}, 128 Bit) – die Turnier-Nummer wäre erratbar (OWASP A01).
     * Wird bei der ersten Verwendung angelegt.
     */
    public function anmeldeSchluessel(int $turnierId): string
    {
        $schluessel = $this->db->wert('SELECT anmelde_schluessel FROM turnier WHERE id = ?', [$turnierId]);
        if (is_string($schluessel) && $schluessel !== '') {
            return $schluessel;
        }
        $schluessel = bin2hex(random_bytes(16));
        $this->db->update('turnier', ['anmelde_schluessel' => $schluessel], ['id' => $turnierId]);
        return $schluessel;
    }

    /** Turnier zum Schlüssel aus dem Anmeldelink, sonst null. */
    public function turnierPerSchluessel(string $schluessel): ?int
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $schluessel)) {
            return null;
        }
        $id = $this->db->wert('SELECT id FROM turnier WHERE anmelde_schluessel = ?', [$schluessel]);
        return $id === null ? null : (int) $id;
    }

    /** Neuer Anmeldelink – der bisherige gilt nicht mehr (z. B. an die falsche Stelle geraten). Bearbeitungslinks bleiben gültig. */
    public function schluesselErneuern(int $turnierId): string
    {
        $schluessel = bin2hex(random_bytes(16));
        $this->db->update('turnier', ['anmelde_schluessel' => $schluessel], ['id' => $turnierId]);
        $this->protokoll->eintragen($turnierId, 'turnier', $turnierId, 'anmeldelink_erneuert');
        return $schluessel;
    }

    // --- Einstellungen ---------------------------------------------------------

    /**
     * @return array{preis_kata: int, preis_kumite: int, preis_kata_team: int, preis_kumite_team: int, hinweis: string, zahlung_vor_freigabe: bool, rechnung_fuer_dojos: bool}
     *         Preise in Cent; zahlung_vor_freigabe: Freigabe erst nach vollständigem Zahlungseingang;
     *         rechnung_fuer_dojos: Dojos können nach Zahlungseingang selbst eine Rechnung abrufen (sonst nur über die Leitung)
     */
    public function einstellungen(array $turnier): array
    {
        $e = (json_decode((string) ($turnier['einstellungen'] ?? ''), true) ?: [])['anmeldung'] ?? [];
        return [
            'preis_kata' => (int) ($e['preis_kata'] ?? 0),
            'preis_kumite' => (int) ($e['preis_kumite'] ?? 0),
            'preis_kata_team' => (int) ($e['preis_kata_team'] ?? 0),
            'preis_kumite_team' => (int) ($e['preis_kumite_team'] ?? 0),
            'hinweis' => (string) ($e['hinweis'] ?? ''),
            'zahlung_vor_freigabe' => !empty($e['zahlung_vor_freigabe']),
            'rechnung_fuer_dojos' => !empty($e['rechnung_fuer_dojos']),
        ];
    }

    /** @param array<string, mixed> $daten preis_* (Euro, Komma erlaubt), hinweis */
    public function einstellungenSpeichern(int $turnierId, array $daten): void
    {
        $turnier = $this->db->eine('SELECT einstellungen FROM turnier WHERE id = ?', [$turnierId]) ?? throw new FachFehler('Turnier nicht gefunden.');
        $alle = json_decode((string) $turnier['einstellungen'], true) ?: [];
        $neu = ['hinweis' => trim((string) ($daten['hinweis'] ?? '')), 'zahlung_vor_freigabe' => !empty($daten['zahlung_vor_freigabe']), 'rechnung_fuer_dojos' => !empty($daten['rechnung_fuer_dojos'])];
        foreach (array_keys(self::POSTEN) as $schluessel) {
            $neu[$schluessel] = self::cent((string) ($daten[$schluessel] ?? '0'));
        }
        $alle['anmeldung'] = $neu;
        $this->db->update('turnier', ['einstellungen' => json_encode($alle, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)], ['id' => $turnierId]);
        $this->protokoll->eintragen($turnierId, 'turnier', $turnierId, 'anmeldung_einstellungen', $neu);
    }

    /** „15,50“ → 1550 */
    public static function cent(string $euro): int
    {
        $euro = str_replace([' ', '€'], '', $euro);
        $euro = str_contains($euro, ',') ? str_replace(['.', ','], ['', '.'], $euro) : $euro;
        if ($euro === '') {
            return 0;
        }
        if (!is_numeric($euro) || (float) $euro < 0) {
            throw new FachFehler("Ungültiger Betrag: $euro");
        }
        return (int) round((float) $euro * 100);
    }

    /** 1550 → „15,50 €“ */
    public static function euro(int $cent): string
    {
        return number_format($cent / 100, 2, ',', '.') . ' €';
    }

    /**
     * Ist die Anmeldung für Dojos möglich (Meldephase, vor Meldeschluss)? Liefert null oder den Grund, warum nicht.
     *
     * @param array<string, mixed> $turnier
     */
    public function geschlossenGrund(array $turnier, ?string $heute = null): ?string
    {
        if (TurnierPhase::von($turnier) !== TurnierPhase::MELDEPHASE) {
            return 'Die Online-Anmeldung ist für dieses Turnier derzeit nicht geöffnet.';
        }
        $heute ??= date('Y-m-d');
        if (!empty($turnier['meldeschluss']) && $heute > $turnier['meldeschluss']) {
            return 'Der Meldeschluss (' . date('d.m.Y', strtotime($turnier['meldeschluss'])) . ') ist vorbei.';
        }
        if ($heute > $turnier['datum']) {
            return 'Das Turnier hat bereits stattgefunden.';
        }
        return null;
    }

    // --- Formular -----------------------------------------------------------------

    /**
     * Bringt die Formulardaten in das gespeicherte Format; leere Zeilen entfallen.
     *
     * @param array<string, mixed> $post
     * @return array{kopf: array<string, string>, daten: array{teilnehmer: array<string, array<string, mixed>>, teams: list<array<string, mixed>>}}
     */
    public function normalisieren(array $post): array
    {
        $text = static fn($w, int $max = 200) => mb_substr(trim((string) $w), 0, $max);
        $kopf = [
            'dojo' => $text($post['dojo'] ?? ''),
            'ansprechpartner' => $text($post['ansprechpartner'] ?? ''),
            'email' => $text($post['email'] ?? ''),
            'telefon' => $text($post['telefon'] ?? '', 50),
            'bemerkung' => $text($post['bemerkung'] ?? '', 2000),
        ];
        $teilnehmer = [];
        foreach ((array) ($post['teilnehmer'] ?? []) as $schluessel => $t) {
            $t = (array) $t;
            $zeile = [
                'vorname' => $text($t['vorname'] ?? '', 100),
                'nachname' => $text($t['nachname'] ?? '', 100),
                'geschlecht' => $text($t['geschlecht'] ?? '', 1),
                'geburtsdatum' => $text($t['geburtsdatum'] ?? '', 10),
                'graduierung' => (int) ($t['graduierung'] ?? 0),
                'kata' => !empty($t['kata']),
                'kumite' => in_array($t['kumite'] ?? '', self::KUMITE, true) ? (string) $t['kumite'] : '',
            ];
            if ($zeile['vorname'] === '' && $zeile['nachname'] === '' && $zeile['geburtsdatum'] === '') {
                continue;
            }
            $teilnehmer['t' . preg_replace('/\D/', '', (string) $schluessel)] = $zeile;
        }
        $teams = [];
        foreach ((array) ($post['teams'] ?? []) as $team) {
            $team = (array) $team;
            $mitglieder = array_values(array_map(static fn($m) => 't' . preg_replace('/\D/', '', (string) $m), array_filter(
                (array) ($team['mitglieder'] ?? []),
                static fn($m) => (string) $m !== '',
            )));
            $ersatz = (string) ($team['ersatz'] ?? '') !== '' ? 't' . preg_replace('/\D/', '', (string) $team['ersatz']) : null;
            $name = $text($team['name'] ?? '');
            if ($name === '' && $mitglieder === [] && $ersatz === null) {
                continue;
            }
            $teams[] = [
                'name' => $name,
                'disziplin' => in_array($team['disziplin'] ?? '', ['kata_team', 'kumite_team'], true) ? $team['disziplin'] : '',
                'mitglieder' => $mitglieder,
                'ersatz' => $ersatz,
            ];
        }
        return ['kopf' => $kopf, 'daten' => ['teilnehmer' => $teilnehmer, 'teams' => $teams]];
    }

    /**
     * Prüft eine Anmeldung vollständig und liefert alle Fehler (leer = in Ordnung).
     *
     * @param array<string, mixed> $turnier
     * @param array<string, string> $kopf
     * @param array{teilnehmer: array<string, array<string, mixed>>, teams: list<array<string, mixed>>} $daten
     * @return list<string>
     */
    public function pruefen(array $turnier, array $kopf, array $daten): array
    {
        $fehler = [];
        if ($kopf['dojo'] === '') {
            $fehler[] = 'Bitte den Namen des Dojos angeben.';
        }
        if ($kopf['ansprechpartner'] === '') {
            $fehler[] = 'Bitte eine Ansprechperson angeben.';
        }
        if (!filter_var($kopf['email'], FILTER_VALIDATE_EMAIL)) {
            $fehler[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        }
        if ($daten['teilnehmer'] === []) {
            $fehler[] = 'Bitte mindestens einen Teilnehmer eintragen.';
        }

        $inTeam = [];
        foreach ($daten['teams'] as $team) {
            foreach (array_merge($team['mitglieder'], array_filter([$team['ersatz']])) as $m) {
                $inTeam[$m] = true;
            }
        }
        $gesehen = [];
        $nr = 0;
        foreach ($daten['teilnehmer'] as $schluessel => $t) {
            $nr++;
            $wer = trim("{$t['vorname']} {$t['nachname']}") ?: "Teilnehmer $nr";
            if ($t['vorname'] === '' || $t['nachname'] === '') {
                $fehler[] = "$wer: Vor- und Nachname angeben.";
            }
            if (!in_array($t['geschlecht'], ['w', 'm', 'd'], true)) {
                $fehler[] = "$wer: Geschlecht wählen.";
            }
            $datum = \DateTimeImmutable::createFromFormat('!Y-m-d', $t['geburtsdatum']);
            if ($datum === false || $datum->format('Y-m-d') !== $t['geburtsdatum']) {
                $fehler[] = "$wer: gültiges Geburtsdatum angeben.";
            } else {
                $alter = Alter::amStichtag($t['geburtsdatum'], (string) $turnier['datum']);
                if ($alter < 3 || $alter > 99) {
                    $fehler[] = "$wer: Geburtsdatum prüfen (Alter am Turniertag: $alter).";
                }
            }
            if ($t['graduierung'] < Graduierung::MIN || $t['graduierung'] > Graduierung::MAX) {
                $fehler[] = "$wer: Graduierung wählen.";
            } elseif (!empty($turnier['min_graduierung']) && $t['graduierung'] < (int) $turnier['min_graduierung']) {
                $fehler[] = "$wer: Mindestgraduierung für dieses Turnier ist " . Graduierung::text((int) $turnier['min_graduierung']) . '.';
            }
            if (!$t['kata'] && $t['kumite'] === '' && !isset($inTeam[$schluessel])) {
                $fehler[] = "$wer: mindestens eine Disziplin wählen (oder einem Team zuordnen).";
            }
            $id = mb_strtolower("{$t['vorname']}|{$t['nachname']}|{$t['geburtsdatum']}");
            if (isset($gesehen[$id])) {
                $fehler[] = "$wer ist doppelt eingetragen.";
            }
            $gesehen[$id] = true;
        }

        $proDisziplin = ['kata_team' => [], 'kumite_team' => []];
        foreach ($daten['teams'] as $i => $team) {
            $wer = $team['name'] !== '' ? "Team „{$team['name']}“" : 'Team ' . ($i + 1);
            if ($team['name'] === '') {
                $fehler[] = "$wer: Teamnamen angeben.";
            }
            if ($team['disziplin'] === '') {
                $fehler[] = "$wer: Kata-Team oder Kumite-Team wählen.";
                continue;
            }
            $alle = array_merge($team['mitglieder'], array_filter([$team['ersatz']]));
            if (count($team['mitglieder']) !== 3) {
                $fehler[] = "$wer: genau 3 Mitglieder auswählen.";
            }
            if (count($alle) !== count(array_unique($alle))) {
                $fehler[] = "$wer: jedes Mitglied nur einmal auswählen.";
            }
            foreach ($alle as $m) {
                if (!isset($daten['teilnehmer'][$m])) {
                    $fehler[] = "$wer: ein ausgewähltes Mitglied steht nicht (mehr) in der Teilnehmerliste.";
                } elseif (isset($proDisziplin[$team['disziplin']][$m])) {
                    $t = $daten['teilnehmer'][$m];
                    $fehler[] = "{$t['vorname']} {$t['nachname']} ist in mehreren " . ($team['disziplin'] === 'kata_team' ? 'Kata' : 'Kumite') . '-Teams.';
                }
                $proDisziplin[$team['disziplin']][$m] = true;
            }
        }
        return array_values(array_unique($fehler));
    }

    /**
     * @param array<string, int|bool|string> $einstellungen
     * @param array{teilnehmer: array<string, array<string, mixed>>, teams: list<array<string, mixed>>} $daten
     * @return array{posten: list<array{text: string, anzahl: int, einzel: int, summe: int}>, summe: int}
     */
    public function preis(array $einstellungen, array $daten): array
    {
        $anzahl = [
            'preis_kata' => count(array_filter($daten['teilnehmer'], static fn($t) => $t['kata'])),
            'preis_kumite' => count(array_filter($daten['teilnehmer'], static fn($t) => $t['kumite'] !== '')),
            'preis_kata_team' => count(array_filter($daten['teams'], static fn($t) => $t['disziplin'] === 'kata_team')),
            'preis_kumite_team' => count(array_filter($daten['teams'], static fn($t) => $t['disziplin'] === 'kumite_team')),
        ];
        $posten = [];
        foreach (self::POSTEN as $schluessel => $text) {
            if ($anzahl[$schluessel] > 0) {
                $einzel = (int) $einstellungen[$schluessel];
                $posten[] = ['text' => $text, 'anzahl' => $anzahl[$schluessel], 'einzel' => $einzel, 'summe' => $einzel * $anzahl[$schluessel]];
            }
        }
        return ['posten' => $posten, 'summe' => array_sum(array_column($posten, 'summe'))];
    }

    // --- Einreichen und Ändern ------------------------------------------------------

    /**
     * Speichert eine neue Anmeldung oder ändert eine bestehende (per Code).
     *
     * @param array<string, mixed> $post Formulardaten
     * @param int|null $vereinId Dojo des meldenden Coach-Kontos (Meldung wird damit verknüpft)
     * @return string Bearbeitungscode
     * @throws AnmeldungUngueltig mit allen Fehlern
     */
    public function einreichen(int $turnierId, array $post, ?string $code = null, ?int $vereinId = null): string
    {
        $turnier = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$turnierId]) ?? throw new FachFehler('Turnier nicht gefunden.');
        if ($grund = $this->geschlossenGrund($turnier)) {
            throw new FachFehler($grund);
        }
        $alt = $code === null ? null : $this->perCode($turnierId, $code);
        if ($alt !== null && $alt['status'] !== 'eingereicht') {
            throw new FachFehler('Diese Anmeldung wurde bereits ' . $alt['status'] . ' und kann nicht mehr geändert werden. Bitte an die Wettkampfleitung wenden.');
        }
        // Meldung eines Coach-Kontos (neu oder schon verknüpft): fest für das Dojo, Name aus den Stammdaten
        $vereinId ??= $alt !== null && $alt['verein_id'] !== null ? (int) $alt['verein_id'] : null;
        if ($vereinId !== null) {
            $post['dojo'] = (string) ($this->db->wert('SELECT name FROM verein WHERE id = ?', [$vereinId]) ?? throw new FachFehler('Das Dojo dieser Meldung gibt es nicht mehr.'));
        }
        ['kopf' => $kopf, 'daten' => $daten] = $this->normalisieren($post);
        $fehler = $this->pruefen($turnier, $kopf, $daten);
        if ($fehler !== []) {
            throw new AnmeldungUngueltig($fehler);
        }
        $preis = $this->preis($this->einstellungen($turnier), $daten)['summe'];
        $jetzt = date('Y-m-d H:i:s');
        $felder = [
            'telefon' => $kopf['telefon'] ?: null,
            'bemerkung' => $kopf['bemerkung'] ?: null,
            'daten' => json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            'preis_cent' => $preis,
        ] + $kopf;

        // Zahlungsaufforderung: neu bzw. neue Version, wenn sich der Betrag ändert (AnmeldeStand)
        $felder += AnmeldeStand::aufforderung($alt, $preis, $jetzt);

        if ($alt !== null) {
            $this->db->update('anmeldung', $felder + ['geaendert_am' => $jetzt], ['id' => $alt['id']]);
            $this->protokoll->eintragen($turnierId, 'anmeldung', (int) $alt['id'], 'geaendert', ['dojo' => $kopf['dojo'], 'preis' => $preis]
                + (isset($felder['aufforderung_version']) ? ['zahlungsaufforderung' => $felder['aufforderung_version']] : []));
            return $alt['code'];
        }
        $code = bin2hex(random_bytes(12));
        $id = $this->db->insert('anmeldung', $felder + [
            'verein_id' => $vereinId,
            'uuid' => Uuid::v4(),
            'turnier_id' => $turnierId,
            'code' => $code,
            'status' => 'eingereicht',
            'eingereicht_am' => $jetzt,
        ]);
        $this->protokoll->eintragen($turnierId, 'anmeldung', $id, 'eingereicht', ['dojo' => $kopf['dojo'], 'preis' => $preis]);
        return $code;
    }

    /** @return array<string, mixed>|null */
    public function perCode(int $turnierId, string $code): ?array
    {
        if (!preg_match('/^[0-9a-f]{24}$/', $code)) {
            return null;
        }
        return $this->db->eine('SELECT * FROM anmeldung WHERE turnier_id = ? AND code = ?', [$turnierId, $code]);
    }

    /**
     * Anmeldung als Formularwerte (für die erneute Anzeige).
     *
     * @param array<string, mixed> $anmeldung
     * @return array<string, mixed>
     */
    public static function formularWerte(array $anmeldung): array
    {
        $daten = json_decode((string) $anmeldung['daten'], true) ?: ['teilnehmer' => [], 'teams' => []];
        $teilnehmer = [];
        foreach ($daten['teilnehmer'] as $schluessel => $t) {
            $teilnehmer[substr((string) $schluessel, 1)] = $t;
        }
        $teams = array_map(static fn($t) => [
            'mitglieder' => array_map(static fn($m) => substr((string) $m, 1), $t['mitglieder']),
            'ersatz' => $t['ersatz'] === null ? '' : substr((string) $t['ersatz'], 1),
        ] + $t, $daten['teams']);
        return array_intersect_key($anmeldung, array_flip(['dojo', 'ansprechpartner', 'email', 'telefon', 'bemerkung']))
            + ['teilnehmer' => $teilnehmer, 'teams' => $teams];
    }

    // --- Leitung ------------------------------------------------------------------------

    /**
     * Meldung des eigenen Vereins der angemeldeten Person (Coach-Konto, das zugleich Turnierleitung ist)?
     * Erlaubt, aber gekennzeichnet (Entscheidung 29.10.2026: Interessenkonflikt sichtbar machen statt sperren).
     *
     * @param array<string, mixed> $anmeldung
     */
    public function eigenerVerein(array $anmeldung, ?int $coachVerein): bool
    {
        if ($coachVerein === null) {
            return false;
        }
        if ($anmeldung['verein_id'] !== null) {
            return (int) $anmeldung['verein_id'] === $coachVerein;
        }
        $name = $this->db->wert('SELECT name FROM verein WHERE id = ?', [$coachVerein]);
        return is_string($name) && mb_strtolower(trim($name)) === mb_strtolower(trim((string) $anmeldung['dojo']));
    }

    /**
     * Gibt eine Anmeldung frei: legt Verein, Teilnehmer und Teams an (bzw.
     * aktualisiert bereits vorhandene Teilnehmer) und ordnet sie den Klassen zu.
     *
     * @param bool $kontoZuordnen Konten der Anmeldung (selbst angelegt, ohne Dojo) dem Dojo zuordnen – bestätigt die Leitung
     *                            ausdrücklich, weil das Konto dann Starter, Ergebnisse und Portal des Dojos sieht
     * @return list<string> Hinweise der Zuordnung
     */
    public function freigeben(int $turnierId, int $anmeldungId, bool $kontoZuordnen = false): array
    {
        return $this->db->transaktion(function () use ($turnierId, $anmeldungId, $kontoZuordnen) {
            $a = $this->anmeldung($turnierId, $anmeldungId);
            if ($a['status'] !== 'eingereicht') {
                throw new FachFehler('Nur eingereichte Anmeldungen können freigegeben werden.');
            }
            $turnier = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$turnierId]) ?? [];
            $stand = $this->stand($a, $turnier);
            if ($this->einstellungen($turnier)['zahlung_vor_freigabe'] && $stand['offen'] > 0) {
                throw new FachFehler('Die Zahlung ist noch offen (' . self::euro($stand['offen']) . '). Freigabe erst nach Zahlungseingang – '
                    . 'bitte zuerst die Zahlung erfassen oder unter Anmeldungen → Einstellungen „Freigabe erst nach Zahlungseingang“ ausschalten.');
            }
            $eigenerVerein = $this->eigenerVerein($a, Session::coachVerein());
            $daten = json_decode((string) $a['daten'], true);
            $stamm = new StammdatenService($this->db);
            $teams = new TeamService($this->db);
            $zuordnung = new ZuordnungService($this->db);
            // Meldung eines Coach-Kontos ist schon mit dem Dojo verknüpft; sonst über den Namen finden bzw. anlegen
            $vereinId = $a['verein_id'] !== null ? (int) $a['verein_id'] : $stamm->vereinFinden((string) $a['dojo']);

            $teilnahmen = [];
            $hinweise = [];
            foreach ($daten['teilnehmer'] as $schluessel => $t) {
                $vorhanden = $this->db->wert(
                    'SELECT t.id FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id
                      WHERE t.turnier_id = ? AND LOWER(a.vorname) = LOWER(?) AND LOWER(a.nachname) = LOWER(?) AND a.geburtsdatum = ?',
                    [$turnierId, $t['vorname'], $t['nachname'], $t['geburtsdatum']],
                );
                $disziplinen = array_values(array_filter([$t['kata'] ? 'kata' : null, $t['kumite'] ?: null]));
                $id = $stamm->teilnehmerSpeichern($turnierId, [
                    'vorname' => $t['vorname'],
                    'nachname' => $t['nachname'],
                    'geschlecht' => $t['geschlecht'],
                    'geburtsdatum' => $t['geburtsdatum'],
                    'graduierung' => $t['graduierung'],
                    'verein_id' => $vereinId,
                    'disziplinen' => $disziplinen,
                    'vermerk' => 'Online-Anmeldung ' . $a['dojo'],
                ], $vorhanden === null ? null : (int) $vorhanden);
                $teilnahmen[$schluessel] = $id;
                array_push($hinweise, ...$zuordnung->teilnehmerZuordnen($turnierId, $id));
            }
            $teamIds = [];
            foreach ($daten['teams'] as $team) {
                $teamId = $teams->teamSpeichern($turnierId, $team['name'], $team['disziplin'], $vereinId);
                foreach ($team['mitglieder'] as $m) {
                    $teams->mitgliedHinzufuegen($turnierId, $teamId, $teilnahmen[$m]);
                }
                if ($team['ersatz'] !== null) {
                    $teams->mitgliedHinzufuegen($turnierId, $teamId, $teilnahmen[$team['ersatz']], 'ersatz');
                }
                $teamIds[] = $teamId;
                array_push($hinweise, ...$zuordnung->teamZuordnen($turnierId, $teamId));
            }
            if ($kontoZuordnen) {
                $konten = new Konten($this->db);
                foreach ($konten->zurAnmeldung((string) $a['uuid']) as $konto) {
                    if ($konten->vereinZuordnen($konto['id'], $vereinId, $turnierId)) {
                        $hinweise[] = "Zugang {$konto['email']} dem Dojo zugeordnet";
                    }
                }
            }
            $this->db->update('anmeldung', [
                'status' => 'freigegeben',
                'verein_id' => $vereinId,
                'uebernahme' => json_encode(['teilnahme' => $teilnahmen, 'team' => $teamIds]),
                'freigegeben_am' => date('Y-m-d H:i:s'),
                'freigegeben_von' => mb_substr(Session::akteur() . ($eigenerVerein ? ' (eigener Verein)' : ''), 0, 100),
                'grund' => null,
            ], ['id' => $anmeldungId]);
            $this->protokoll->eintragen($turnierId, 'anmeldung', $anmeldungId, 'freigegeben', [
                'teilnehmer' => count($teilnahmen), 'teams' => count($teamIds), 'eigener_verein' => $eigenerVerein,
            ]);
            return $hinweise;
        });
    }

    public function ablehnen(int $turnierId, int $anmeldungId, string $grund): void
    {
        $a = $this->anmeldung($turnierId, $anmeldungId);
        if ($a['status'] !== 'eingereicht') {
            throw new FachFehler('Nur eingereichte Anmeldungen können abgelehnt werden.');
        }
        $this->db->update('anmeldung', ['status' => 'abgelehnt', 'grund' => trim($grund) ?: null], ['id' => $anmeldungId]);
        $this->protokoll->eintragen($turnierId, 'anmeldung', $anmeldungId, 'abgelehnt', ['grund' => $grund]);
    }

    /** Hebt eine Ablehnung auf – die Anmeldung ist wieder „eingereicht“ und für das Dojo änderbar. */
    public function wiederOeffnen(int $turnierId, int $anmeldungId): void
    {
        $a = $this->anmeldung($turnierId, $anmeldungId);
        if ($a['status'] !== 'abgelehnt') {
            throw new FachFehler('Nur abgelehnte Anmeldungen können wieder geöffnet werden.');
        }
        $this->db->update('anmeldung', ['status' => 'eingereicht', 'grund' => null], ['id' => $anmeldungId]);
        $this->protokoll->eintragen($turnierId, 'anmeldung', $anmeldungId, 'wieder_geoeffnet');
    }

    // --- Zahlung -------------------------------------------------------------------------

    /**
     * Bearbeitungsstand (Schritte, Kurztext, Beträge) einer Anmeldung.
     *
     * @param array<string, mixed> $anmeldung
     * @param array<string, mixed> $turnier
     * @return array<string, mixed>
     */
    public function stand(array $anmeldung, array $turnier): array
    {
        return AnmeldeStand::berechnen($anmeldung, $this->einstellungen($turnier)['zahlung_vor_freigabe']);
    }

    /**
     * Zahlungseingang erfassen (Gesamtbetrag, der bisher eingegangen ist) – auch nach der Freigabe,
     * z. B. Barzahlung am Turniertag.
     */
    public function zahlungErfassen(int $turnierId, int $anmeldungId, string $betrag, string $datum, string $notiz): void
    {
        $a = $this->anmeldung($turnierId, $anmeldungId);
        if ($a['status'] === 'abgelehnt') {
            throw new FachFehler('Für eine abgelehnte Anmeldung wird keine Zahlung erfasst.');
        }
        $cent = self::cent($betrag);
        if ($cent <= 0) {
            throw new FachFehler('Bitte den erhaltenen Betrag eingeben.');
        }
        $datum = trim($datum) === '' ? date('Y-m-d') : trim($datum);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) || strtotime($datum) === false || $datum > date('Y-m-d', strtotime('+1 day'))) {
            throw new FachFehler('Ungültiges Zahlungsdatum.');
        }
        $this->db->update('anmeldung', [
            'bezahlt_cent' => $cent,
            'bezahlt_am' => $datum,
            'zahlung_notiz' => mb_substr(trim($notiz), 0, 200) ?: null,
            'zahlung_von' => mb_substr(Session::akteur(), 0, 100),
        ], ['id' => $anmeldungId]);
        $this->protokoll->eintragen($turnierId, 'anmeldung', $anmeldungId, 'zahlung_erfasst', ['betrag' => $cent, 'datum' => $datum]);
    }

    public function zahlungZuruecknehmen(int $turnierId, int $anmeldungId): void
    {
        $this->anmeldung($turnierId, $anmeldungId);
        $this->db->update('anmeldung', ['bezahlt_cent' => 0, 'bezahlt_am' => null, 'zahlung_notiz' => null, 'zahlung_von' => null], ['id' => $anmeldungId]);
        $this->protokoll->eintragen($turnierId, 'anmeldung', $anmeldungId, 'zahlung_zurueckgenommen');
    }

    /** @return array<string, mixed> */
    public function anmeldung(int $turnierId, int $anmeldungId): array
    {
        return $this->db->eine('SELECT * FROM anmeldung WHERE id = ? AND turnier_id = ?', [$anmeldungId, $turnierId])
            ?? throw new FachFehler('Anmeldung nicht gefunden.');
    }
}
