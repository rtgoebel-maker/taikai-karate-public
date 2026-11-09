<?php

declare(strict_types=1);

namespace Wettkampf\Turnier;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Kern\Db\Database;
use Wettkampf\Kern\Protokoll;
use Wettkampf\Durchfuehrung\AuslosungService;
use Wettkampf\Durchfuehrung\KlassenService;
use Wettkampf\Durchfuehrung\PlanService;

/**
 * Schaltet die Turnierphase und prüft vorher, ob das Turnier bereit ist.
 * Ergebnisse (Tablet und PC) sind nur in der Phase „Turnier läuft“ erlaubt.
 */
final class TurnierPhaseService
{
    private Protokoll $protokoll;

    public function __construct(private readonly Database $db)
    {
        $this->protokoll = new Protokoll($db);
    }

    /** Offene Punkte, die „Turnier starten“ selbst erledigen kann (Auslosung, Tatami-Plan). */
    public const BEHEBBAR = ['auslosung', 'plan'];

    /**
     * Offene Punkte vor dem Wechsel in die Zielphase (leer = alles bereit).
     *
     * @return list<string>
     */
    public function pruefen(int $turnierId, string $ziel): array
    {
        return array_values($this->punkte($turnierId, $ziel));
    }

    /**
     * Offene Punkte vor dem Start, die nicht automatisch behoben werden können
     * (z. B. fehlende PINs, nicht freigegebene Anmeldungen).
     *
     * @return list<string>
     */
    public function nichtBehebbar(int $turnierId, string $ziel): array
    {
        return array_values(array_diff_key($this->punkte($turnierId, $ziel), array_flip(self::BEHEBBAR)));
    }

    /**
     * Startvorbereitung: lost alle offenen Klassen mit Startern aus (leere
     * Klassen bleiben unberührt) und verteilt nicht zugeordnete Klassen auf
     * die Tatamis – Kata und Kumite getrennt. Bei dynamischer Verteilung bekommt
     * jede freie Tatami nur ihre erste Gruppe, der Rest wartet in der Warteschlange.
     *
     * @return array{ausgelost: int, verteilt: int}
     */
    public function startVorbereiten(int $turnierId): array
    {
        return $this->db->transaktion(function () use ($turnierId) {
            // Bei dynamischer Verteilung teilt schon das Auslosen freien Tatamis Gruppen zu –
            // gezählt wird daher, wie viele Gruppen insgesamt neu auf eine Tatami kamen.
            $vorher = $this->zugeteilt($turnierId);
            $ausgelost = (new AuslosungService($this->db))->alleAuslosen($turnierId);
            $plan = new PlanService($this->db);
            $v = $plan->verteilung($turnierId);
            if ($v['modus'] === 'dynamisch') {
                $plan->nachziehen($turnierId);
                return ['ausgelost' => $ausgelost, 'verteilt' => $this->zugeteilt($turnierId) - $vorher];
            }
            return ['ausgelost' => $ausgelost, 'verteilt' => $plan->automatischVerteilen($turnierId, $v['getrennt'])];
        });
    }

    /** Anzahl Klassen mit unbeendeter Runde auf einer Tatami. */
    private function zugeteilt(int $turnierId): int
    {
        return (int) $this->db->wert(
            "SELECT COUNT(DISTINCT p.klasse_id) FROM phase p JOIN klasse k ON k.id = p.klasse_id
              WHERE k.turnier_id = ? AND p.tatami_id IS NOT NULL AND p.status <> 'beendet'",
            [$turnierId],
        );
    }

    /** @return array<string, string> Schlüssel → Text */
    private function punkte(int $turnierId, string $ziel): array
    {
        $punkte = [];
        if ($ziel === TurnierPhase::LAEUFT) {
            $offen = (int) $this->db->wert(
                "SELECT COUNT(*) FROM klasse k WHERE k.turnier_id = ? AND k.status = 'offen'
                    AND (SELECT COUNT(*) FROM start s WHERE s.klasse_id = k.id AND s.status <> 'zurueckgezogen') >= k.min_starter",
                [$turnierId],
            );
            if ($offen > 0) {
                $punkte['auslosung'] = "$offen Klassen mit Startern sind noch nicht ausgelost.";
            }
            // Klassen unter der Mindestanzahl finden nicht statt – deren Starter treten sonst gar nicht an
            $ausfall = $this->db->alle(
                "SELECT k.code, k.min_starter,
                        (SELECT COUNT(*) FROM start s WHERE s.klasse_id = k.id AND s.status <> 'zurueckgezogen') AS anzahl
                   FROM klasse k WHERE k.turnier_id = ? AND k.status = 'offen' AND k.zusammengelegt_in IS NULL ORDER BY k.sortierung, k.code",
                [$turnierId],
            );
            $ausfall = array_filter($ausfall, static fn(array $k) => (int) $k['anzahl'] > 0 && !KlassenService::findetStatt($k, (int) $k['anzahl']));
            if ($ausfall !== []) {
                $liste = implode(', ', array_map(static fn(array $k) => $k['code'] . ' (' . $k['anzahl'] . ' Starter)', $ausfall));
                $punkte['ausfall'] = sprintf(
                    '%d %s unter der Mindestanzahl und %s nicht statt: %s. Zusammenlegen oder Starter umsetzen – sonst treten diese Starter nicht an.',
                    count($ausfall),
                    count($ausfall) === 1 ? 'Klasse liegt' : 'Klassen liegen',
                    count($ausfall) === 1 ? 'findet' : 'finden',
                    $liste,
                );
            }
            $ohneTatami = (int) $this->db->wert(
                'SELECT COUNT(DISTINCT p.klasse_id) FROM phase p JOIN klasse k ON k.id = p.klasse_id WHERE k.turnier_id = ? AND p.tatami_id IS NULL',
                [$turnierId],
            );
            // dynamische Verteilung: die Warteschlange ist gewollt
            if ($ohneTatami > 0 && (new PlanService($this->db))->verteilung($turnierId)['modus'] === 'fest') {
                $punkte['plan'] = "$ohneTatami Klassen sind im Tatami-Plan noch keiner Tatami zugeordnet.";
            }
            $ohnePin = (int) $this->db->wert('SELECT COUNT(*) FROM tatami WHERE turnier_id = ? AND pin_hash IS NULL', [$turnierId]);
            if ($ohnePin > 0) {
                $punkte['pins'] = "$ohnePin Tatamis haben noch keine PIN – die Tablets können sich nicht anmelden.";
            }
            $anmeldungen = (int) $this->db->wert("SELECT COUNT(*) FROM anmeldung WHERE turnier_id = ? AND status = 'eingereicht'", [$turnierId]);
            if ($anmeldungen > 0) {
                $punkte['anmeldungen'] = "$anmeldungen Online-Anmeldungen sind noch nicht freigegeben.";
            }
        }
        if ($ziel === TurnierPhase::ABGESCHLOSSEN) {
            $unbestaetigt = (int) $this->db->wert(
                "SELECT COUNT(*) FROM klasse k WHERE k.turnier_id = ? AND k.status NOT IN ('bestaetigt', 'zusammengelegt')
                    AND k.status <> 'offen' AND EXISTS (SELECT 1 FROM start s WHERE s.klasse_id = k.id)",
                [$turnierId],
            );
            if ($unbestaetigt > 0) {
                $punkte['bestaetigung'] = "$unbestaetigt Klassen sind noch nicht beendet und bestätigt – sie fehlen in der Siegerliste.";
            }
        }
        return $punkte;
    }

    /**
     * Wechselt in eine Phase. Offene Punkte (siehe pruefen) verhindern den
     * Wechsel, außer $trotzdem ist gesetzt. Mit $vorbereiten werden vor dem
     * Start offene Klassen ausgelost und verteilt (siehe startVorbereiten).
     */
    public function setzen(int $turnierId, string $ziel, bool $trotzdem = false, bool $vorbereiten = false): void
    {
        if ($vorbereiten && $ziel === TurnierPhase::LAEUFT) {
            $this->startVorbereiten($turnierId);
        }
        if (!isset(TurnierPhase::ALLE[$ziel])) {
            throw new FachFehler('Unbekannte Turnierphase.');
        }
        $turnier = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$turnierId]) ?? throw new FachFehler('Turnier nicht gefunden.');
        $alt = TurnierPhase::von($turnier);
        if ($alt === $ziel) {
            return;
        }
        // Wiederöffnen eines abgeschlossenen Turniers (für Korrekturen) ist kein neuer Start – keine Startprüfliste
        $punkte = $alt === TurnierPhase::ABGESCHLOSSEN && $ziel === TurnierPhase::LAEUFT ? [] : $this->pruefen($turnierId, $ziel);
        if ($punkte !== [] && !$trotzdem) {
            throw new FachFehler('Noch nicht bereit für „' . TurnierPhase::text($ziel) . '“: ' . implode(' ', $punkte));
        }
        $this->db->update('turnier', ['status' => $ziel], ['id' => $turnierId]);
        $this->protokoll->eintragen($turnierId, 'turnier', $turnierId, 'phase', ['alt' => $alt, 'neu' => $ziel, 'offene_punkte' => $punkte]);
        $this->protokoll->revisionErhoehen($turnierId);
    }

    /** Wirft einen Fachfehler, wenn das Turnier gerade keine Ergebnisse annimmt. */
    public function ergebnisseErlaubt(int $turnierId): void
    {
        $turnier = $this->db->eine('SELECT status FROM turnier WHERE id = ?', [$turnierId]) ?? throw new FachFehler('Turnier nicht gefunden.');
        match (TurnierPhase::von($turnier)) {
            TurnierPhase::LAEUFT => null,
            TurnierPhase::ABGESCHLOSSEN => throw new FachFehler('Das Turnier ist abgeschlossen – Ergebnisse sind gesperrt.'),
            default => throw new FachFehler('Das Turnier ist noch nicht gestartet – Ergebnisse können erst eingetragen werden, wenn die Wettkampfleitung das Turnier gestartet hat.'),
        };
    }

    /**
     * Meldung an die Leitung: alle Kata-Gruppen sind gelaufen, Kumite steht noch aus – meist
     * die Gelegenheit für eine allgemeine Pause. Null, solange das nicht zutrifft oder die
     * Leitung die Meldung schon quittiert hat.
     *
     * @return array{seit: string|null, kumite_offen: int}|null
     */
    public function kataBeendet(int $turnierId): ?array
    {
        $turnier = $this->db->eine('SELECT status, einstellungen FROM turnier WHERE id = ?', [$turnierId]);
        if ($turnier === null || TurnierPhase::von($turnier) !== TurnierPhase::LAEUFT
            || !empty((json_decode((string) $turnier['einstellungen'], true) ?: [])['kata_pause_quittiert'])) {
            return null;
        }
        $z = $this->db->eine(
            "SELECT SUM(CASE WHEN k.disziplin LIKE 'kata%' THEN 1 ELSE 0 END) AS kata,
                    SUM(CASE WHEN k.disziplin LIKE 'kata%' AND p.status <> 'beendet' THEN 1 ELSE 0 END) AS kata_offen,
                    COUNT(DISTINCT CASE WHEN k.disziplin NOT LIKE 'kata%' AND p.status <> 'beendet' THEN k.id END) AS kumite_offen
               FROM phase p JOIN klasse k ON k.id = p.klasse_id
              WHERE k.turnier_id = ? AND k.status <> 'zusammengelegt'",
            [$turnierId],
        );
        if ((int) $z['kata'] === 0 || (int) $z['kata_offen'] > 0 || (int) $z['kumite_offen'] === 0) {
            return null;
        }
        $seit = $this->db->wert(
            "SELECT MAX(a.beendet_am) FROM auftritt a JOIN phase p ON p.id = a.phase_id JOIN klasse k ON k.id = p.klasse_id
              WHERE k.turnier_id = ? AND k.disziplin LIKE 'kata%'",
            [$turnierId],
        );
        $seitKampf = $this->db->wert(
            "SELECT MAX(b.beendet_am) FROM begegnung b JOIN phase p ON p.id = b.phase_id JOIN klasse k ON k.id = p.klasse_id
              WHERE k.turnier_id = ? AND k.disziplin LIKE 'kata%'",
            [$turnierId],
        );
        return ['seit' => max((string) $seit, (string) $seitKampf) ?: null, 'kumite_offen' => (int) $z['kumite_offen']];
    }

    /** Leitung hat die Meldung „Kata beendet“ gesehen. */
    public function kataBeendetQuittieren(int $turnierId): void
    {
        $e = json_decode((string) $this->db->wert('SELECT einstellungen FROM turnier WHERE id = ?', [$turnierId]), true) ?: [];
        $e['kata_pause_quittiert'] = date('Y-m-d H:i:s');
        $this->db->update('turnier', ['einstellungen' => json_encode($e, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)], ['id' => $turnierId]);
        $this->protokoll->eintragen($turnierId, 'turnier', $turnierId, 'kata_beendet_quittiert');
    }
}
