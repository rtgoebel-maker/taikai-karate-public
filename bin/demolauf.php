<?php

declare(strict_types=1);

/*
 * Simuliert einen laufenden Turniertag (für Demo und Statusseite):
 * lost offene Klassen aus, verteilt sie auf die Tatamis, trägt einen Teil
 * der Ergebnisse ein, startet je Tatami den nächsten Kampf, bestätigt
 * abgeschlossene Klassen, ruft die nächsten Kämpfe aus (einige Starter
 * „nicht erschienen“) und vergibt Demo-PINs.
 *
 *   php bin/demolauf.php --turnier=ID [--anteil=45] [--neu] [--arten]
 *
 * --anteil: ungefährer Fortschritt in Prozent (je Tatami leicht unterschiedlich).
 * --neu:    setzt Auslosung, Tatami-Plan und alle Ergebnisse des Turniers zurück
 *           (Teilnehmer und Klassen bleiben) und spielt den Tag neu durch.
 * --arten:  (enthält --neu, ab 4 Tatamis) je Tatami eine Eingabeart:
 *           1 Kata Punkte, 2 Kata Flaggen, 3 Kata Team, 4 Kumite Team.
 *           Jede zweite Kata-Einzelklasse wird dafür auf Flaggen-K.-o.
 *           umgestellt, Team-Klassen werden auf 4 Teams aufgefüllt; Einzel-
 *           Kumite folgt danach auf Tatami 3/4. Gespielt werden nur die
 *           Schwerpunkt-Gruppen, sodass jedes Tablet „seine“ Art zeigt.
 *
 * Jede Gruppe bleibt bis zum Finale auf ihrer Tatami; Kata-Gruppen laufen auf
 * der ersten Hälfte der Tatamis, Kumite-Gruppen auf der zweiten. Die Tatamis
 * arbeiten ihre Gruppen nacheinander ab.
 */

use Wettkampf\App;
use Wettkampf\Durchfuehrung\AuslosungService;
use Wettkampf\Durchfuehrung\AusrufService;
use Wettkampf\Durchfuehrung\ErgebnisService;
use Wettkampf\Durchfuehrung\PlanService;
use Wettkampf\Turnier\StammdatenService;
use Wettkampf\Durchfuehrung\TatamiAnsicht;
use Wettkampf\Meldung\TeamService;
use Wettkampf\Turnier\TurnierPhaseService;
use Wettkampf\Meldung\ZuordnungService;
use Wettkampf\Domain\Auslosung\Zufall;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Domain\Graduierung;
use Wettkampf\Domain\TurnierPhase;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = App::erstellen(dirname(__DIR__));
$db = $app->db;
$turnier = null;
$anteil = 45;
$neu = false;
$arten = false;
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--turnier=(\d+)$/', $argument, $m)) {
        $turnier = (int) $m[1];
    } elseif ($argument === '--neu') {
        $neu = true;
    } elseif ($argument === '--arten') {
        $arten = $neu = true;
    } elseif (preg_match('/^--anteil=(\d+)$/', $argument, $m)) {
        $anteil = max(0, min(100, (int) $m[1]));
    }
}
if ($turnier === null || $db->wert('SELECT id FROM turnier WHERE id = ?', [$turnier]) === null) {
    fwrite(STDERR, "Bitte ein vorhandenes Turnier angeben: php bin/demolauf.php --turnier=ID\n");
    exit(1);
}

$z = new Zufall('demolauf' . $turnier);
$katas = ['Heian Shodan', 'Heian Nidan', 'Heian Sandan', 'Heian Yondan', 'Heian Godan', 'Tekki Shodan', 'Bassai Dai', 'Kanku Dai', 'Jion', 'Enpi', 'Hangetsu'];
$ergebnis = new ErgebnisService($db);
$ansicht = new TatamiAnsicht($db);

if ($neu) {
    // Auslosungen, Plan und Ergebnisse zurücksetzen – Teilnehmer und Klassen bleiben
    $db->transaktion(static function () use ($db, $turnier) {
        $klassen = "SELECT id FROM klasse WHERE turnier_id = $turnier";
        $db->query("DELETE FROM platzierung WHERE klasse_id IN ($klassen)");
        $db->query("DELETE FROM phase WHERE klasse_id IN ($klassen)");
        $db->query("DELETE FROM auslosung WHERE klasse_id IN ($klassen)");
        $db->query("UPDATE start SET stichentscheid = NULL WHERE klasse_id IN ($klassen)");
        $db->query("UPDATE klasse SET status = 'offen' WHERE turnier_id = ? AND status <> 'zusammengelegt'", [$turnier]);
        (new Wettkampf\Kern\Protokoll($db))->eintragen($turnier, 'turnier', $turnier, 'demo_zurueckgesetzt');
    });
}
/** @var array<int, list<int>> $schwerpunkt Tatami-ID → Klassen mit „ihrer“ Eingabeart (nur bei --arten) */
$schwerpunkt = [];
/** @var array<int, list<int>> $danach Tatami-ID → weitere Klassen (nur bei --arten) */
$danach = [];
if ($arten) {
    $tatamiIds = array_map('intval', $db->spalte('SELECT id FROM tatami WHERE turnier_id = ? ORDER BY nummer', [$turnier]));
    if (count($tatamiIds) < 4) {
        fwrite(STDERR, "--arten braucht mindestens 4 Tatamis.\n");
        exit(1);
    }
    $stamm = new StammdatenService($db);
    $teams = new TeamService($db);
    $zuordnung = new ZuordnungService($db);
    $vereine = $db->spalte('SELECT DISTINCT v.name FROM verein v JOIN athlet a ON a.verein_id = v.id ORDER BY v.name') ?: ['Demo-Dojo'];
    $vornamen = ['w' => ['Anna', 'Lea', 'Mia', 'Emma', 'Lina', 'Sophie', 'Clara', 'Ida'], 'm' => ['Ben', 'Paul', 'Leon', 'Finn', 'Noah', 'Jonas', 'Felix', 'Max']];
    $nachnamen = ['Huber', 'Bauer', 'Wagner', 'Maier', 'Gruber', 'Brandl', 'Seidl', 'Lehner', 'Hofer', 'Kerner'];

    /** Füllt eine Team-Klasse mit passenden Teams (3 Mitglieder aus einem Verein) auf $anzahl auf. */
    $teamsAuffuellen = static function (array $k, int $anzahl) use ($db, $turnier, $stamm, $teams, $zuordnung, $vereine, $vornamen, $nachnamen, $z): void {
        $vorhanden = (int) $db->wert('SELECT COUNT(*) FROM start WHERE klasse_id = ?', [$k['id']]);
        for ($i = $vorhanden; $i < $anzahl; $i++) {
            $verein = $vereine[($i + (int) $k['id']) % count($vereine)];
            $nr = 1 + (int) $db->wert('SELECT COUNT(*) FROM team WHERE turnier_id = ? AND disziplin = ? AND name LIKE ?', [$turnier, $k['disziplin'], "$verein %"]);
            $teamId = $teams->teamSpeichern($turnier, "$verein $nr", $k['disziplin'], $stamm->vereinFinden($verein));
            for ($m = 0; $m < 3; $m++) {
                $geschlecht = $k['geschlecht'] === 'mixed' ? ['w', 'm'][$z->zahl(2)] : $k['geschlecht'];
                $min = (int) ($k['alter_min'] ?? 18);
                $max = $k['alter_max'] === null ? $min + 15 : (int) $k['alter_max'];
                $gradMin = (int) ($k['grad_min'] ?? Graduierung::MIN);
                $gradMax = (int) ($k['grad_max'] ?? Graduierung::MAX);
                $teilnahme = $stamm->teilnehmerSpeichern($turnier, [
                    'vorname' => $vornamen[$geschlecht][$z->zahl(8)],
                    'nachname' => $nachnamen[$z->zahl(count($nachnamen))],
                    'geschlecht' => $geschlecht,
                    'alter_manuell' => $min + $z->zahl($max - $min + 1),
                    'graduierung' => min($gradMax, $gradMin + $z->zahl(4)),
                    'verein_name' => $verein,
                    'disziplinen' => [],
                ]);
                $teams->mitgliedHinzufuegen($turnier, $teamId, $teilnahme);
            }
            $zuordnung->teamZuordnen($turnier, $teamId);
        }
    };

    $klassen = $db->alle(
        "SELECT k.*, (SELECT COUNT(*) FROM start s WHERE s.klasse_id = k.id) AS starter
           FROM klasse k WHERE k.turnier_id = ? AND k.status <> 'zusammengelegt' ORDER BY k.sortierung, k.id",
        [$turnier],
    );
    $kataNr = 0;
    $kumiteNr = 0;
    foreach ($klassen as $k) {
        $id = (int) $k['id'];
        if ($k['disziplin'] === 'kata' && (int) $k['starter'] > 0) {
            // abwechselnd Punkte (Tatami 1) und Flaggen-K.-o. (Tatami 2)
            $flaggen = $kataNr++ % 2 === 1;
            if ($flaggen) {
                $db->update('klasse', ['modus' => 'auto', 'wertung' => 'flaggen', 'wertung_finale' => null], ['id' => $id]);
                $db->delete('klasse_regel', ['klasse_id' => $id]);
            } elseif ($k['wertung'] !== 'punkte') {
                $db->update('klasse', ['modus' => 'punkte', 'wertung' => 'punkte', 'wertung_finale' => 'punkte'], ['id' => $id]);
                $db->delete('klasse_regel', ['klasse_id' => $id]);
            }
            $schwerpunkt[$tatamiIds[$flaggen ? 1 : 0]][] = $id;
        } elseif ($k['disziplin'] === 'kata_team' || $k['disziplin'] === 'kumite_team') {
            $teamsAuffuellen($k, 4);
            $schwerpunkt[$tatamiIds[$k['disziplin'] === 'kata_team' ? 2 : 3]][] = $id;
        } elseif ((int) $k['starter'] > 0) {
            $danach[$tatamiIds[2 + $kumiteNr++ % 2]][] = $id;
        }
    }
}

// Jede Gruppe bleibt bis zum Finale auf ihrer Tatami
$db->query('UPDATE klasse SET gleiche_flaeche = 1 WHERE turnier_id = ?', [$turnier]);
$ausgelost = (new AuslosungService($db))->alleAuslosen($turnier);
if ($arten) {
    // Schwerpunkt-Gruppen zuerst, danach die übrigen – in fester Reihenfolge je Tatami
    $plan = new PlanService($db);
    $verteilt = 0;
    foreach ($tatamiIds as $tatamiId) {
        foreach (array_merge($schwerpunkt[$tatamiId] ?? [], $danach[$tatamiId] ?? []) as $klasseId) {
            $phase = $db->wert('SELECT id FROM phase WHERE klasse_id = ? ORDER BY id', [$klasseId]);
            if ($phase !== null) {
                $plan->verschieben($turnier, (int) $phase, $tatamiId);
                $verteilt++;
            }
        }
    }
} else {
    $verteilt = (new PlanService($db))->automatischVerteilen($turnier, true);
}
// Der simulierte Turniertag läuft (offene Punkte wie fehlende PINs werden übergangen)
(new TurnierPhaseService($db))->setzen($turnier, TurnierPhase::LAEUFT, true);
$db->update('turnier', ['oeffentlich' => 1], ['id' => $turnier]);

/** Zufälliger Punktwert 6,5–8,0 in Zehnteln. */
$wert = static fn() => number_format(6.5 + $z->zahl(16) / 10, 1, '.', '');
$kata = static fn(array $liste) => $liste ? $liste[$z->zahl(count($liste))] : $katas[$z->zahl(count($katas))];

/** @param array<string, mixed> $e Eintrag aus TatamiAnsicht */
$eingabe = static function (array $e) use ($z, $wert, $kata): array {
    $kr = (int) $e['kampfrichter'];
    if ($z->zahl(50) === 0) {
        return $e['typ'] === 'auftritt'
            ? ['entscheidung' => 'kiken']
            : ['entscheidung' => 'kiken', 'gegen' => $z->zahl(2) === 0 ? 'aka' : 'shiro', 'grund' => 'nicht angetreten'];
    }
    if ($e['typ'] === 'auftritt') {
        return ['werte' => array_map(static fn() => $wert(), range(1, $kr)), 'kata' => $kata($e['kata_liste'])];
    }
    $daten = [];
    if (str_starts_with($e['disziplin'], 'kata')) {
        $daten['kata_aka'] = $kata($e['kata_liste']);
        $daten['kata_shiro'] = $kata($e['kata_liste']);
    }
    $sieger = $z->zahl(2) === 0 ? 'aka' : 'shiro';
    $verlierer = $sieger === 'aka' ? 'shiro' : 'aka';
    if ($e['disziplin'] === 'kumite_team' && $e['system'] === 'kumite') {
        $kaempfe = [];
        foreach ([$sieger, $sieger, $z->zahl(2) === 0 ? $sieger : $verlierer] as $s) {
            $kaempfe[] = ['sieger' => $s, "{$s}_ippon" => 1];
        }
        return $daten + ['kaempfe' => $kaempfe];
    }
    return $daten + match ($e['system']) {
        'flaggen' => (static function () use ($z, $kr, $sieger) {
            $mehrheit = intdiv($kr, 2) + 1 + $z->zahl(intdiv($kr, 2) + 1);
            return $sieger === 'aka'
                ? ['flaggen_aka' => $mehrheit, 'flaggen_shiro' => $kr - $mehrheit]
                : ['flaggen_aka' => $kr - $mehrheit, 'flaggen_shiro' => $mehrheit];
        })(),
        'punkte' => (static function () use ($kr, $wert, $sieger) {
            $a = array_map(static fn() => $wert(), range(1, $kr));
            $s = array_map(static fn() => $wert(), range(1, $kr));
            $mehr = static fn(array $x) => array_map(static fn($v) => number_format(min(10, (float) $v + 0.3), 1, '.', ''), $x);
            return $sieger === 'aka' ? ['aka_werte' => $mehr($a), 'shiro_werte' => $s] : ['aka_werte' => $a, 'shiro_werte' => $mehr($s)];
        })(),
        default => (static function () use ($z, $kr, $sieger, $verlierer) {
            // Kumite: Ippon, zwei Wazaari, knapper Sieg oder Hantei
            $art = $z->zahl(6);
            $verwarnung = $z->zahl(5) === 0 ? [$verlierer . '_strafen' => ['regel' => 1]] : []; // Keikoku
            if ($art === 0) {
                $mehrheit = intdiv($kr, 2) + 1;
                return [$sieger . '_wazaari' => 1, $verlierer . '_wazaari' => 1,
                    'hantei_aka' => $sieger === 'aka' ? $mehrheit : $kr - $mehrheit,
                    'hantei_shiro' => $sieger === 'shiro' ? $mehrheit : $kr - $mehrheit] + $verwarnung;
            }
            return match ($art) {
                1, 2 => [$sieger . '_ippon' => 1],
                3 => [$sieger . '_wazaari' => 2, $verlierer . '_wazaari' => $z->zahl(2)],
                default => [$sieger . '_wazaari' => 1, 'zeit_abgelaufen' => 1], // Sieg nach Zeit
            } + $verwarnung;
        })(),
    };
};

// Ergebnisse je Tatami in Plan-Reihenfolge eintragen, bis der Ziel-Fortschritt erreicht ist
$tatamis = $db->alle('SELECT * FROM tatami WHERE turnier_id = ? ORDER BY nummer', [$turnier]);
$ziel = [];
foreach ($tatamis as $i => $t) {
    $ziel[(int) $t['id']] = max(0, min(95, $anteil + [-12, 6, -3, 10, -8, 3][$i % 6])) / 100;
}
// --arten: nur Schwerpunkt-Gruppen spielen, bis ihr Anteil erreicht ist
$schwerpunktCodes = [];
$schwerpunktZiel = [];
foreach ($schwerpunkt as $tatamiId => $klassenIds) {
    $schwerpunktCodes[$tatamiId] = $db->spalte('SELECT code FROM klasse WHERE id IN (' . implode(',', $klassenIds) . ')');
    $einheiten = (int) $db->wert('SELECT COUNT(*) FROM begegnung b JOIN phase p ON p.id = b.phase_id WHERE p.klasse_id IN (' . implode(',', $klassenIds) . ')')
        + (int) $db->wert('SELECT COUNT(*) FROM auftritt a JOIN phase p ON p.id = a.phase_id WHERE p.klasse_id IN (' . implode(',', $klassenIds) . ')');
    $schwerpunktZiel[$tatamiId] = (int) floor($einheiten * $ziel[$tatamiId]);
}
$gespielt = [];
$eingetragen = 0;
do {
    $fortschritt = false;
    foreach ($tatamis as $t) {
        $id = (int) $t['id'];
        $zustand = $ansicht->fuerTatami($id, 1);
        $gesamt = $zustand['offen'] + $zustand['beendet'];
        $naechster = $zustand['laufend'] ?? ($zustand['naechste'][0] ?? null);
        if ($naechster === null || $gesamt === 0) {
            continue;
        }
        if ($arten) {
            if (!in_array($naechster['klasse'], $schwerpunktCodes[$id] ?? [], true) || ($gespielt[$id] ?? 0) >= ($schwerpunktZiel[$id] ?? 0)) {
                continue;
            }
            $gespielt[$id] = ($gespielt[$id] ?? 0) + 1;
        } elseif ($zustand['beendet'] / $gesamt >= $ziel[$id]) {
            continue;
        }
        $_SESSION['tisch'] = ['turnier_id' => $turnier, 'tatami_id' => $id, 'name' => $t['bezeichnung'] . ' (Tisch)'];
        try {
            if ($naechster['typ'] === 'auftritt') {
                $ergebnis->auftrittEintragen($turnier, $naechster['id'], $eingabe($naechster), false);
            } else {
                $ergebnis->begegnungEintragen($turnier, $naechster['id'], $eingabe($naechster), false);
            }
            $eingetragen++;
            $fortschritt = true;
        } catch (FachFehler $f) {
            fwrite(STDERR, "Hinweis {$naechster['klasse']} #{$naechster['nr']}: {$f->getMessage()}\n");
        }
    }
} while ($fortschritt);

// Gleichstände per Stichentscheid auflösen (Reihenfolge der Tabelle)
foreach ($db->alle("SELECT id FROM klasse WHERE turnier_id = ? AND status = 'laeuft'", [$turnier]) as $k) {
    $offen = (int) $db->wert("SELECT COUNT(*) FROM phase WHERE klasse_id = ? AND status <> 'beendet'", [$k['id']]);
    if ($offen === 0) {
        $starts = $db->spalte('SELECT id FROM start WHERE klasse_id = ? ORDER BY id', [$k['id']]);
        $ergebnis->stichentscheid($turnier, (int) $k['id'], 'start', array_combine($starts, range(1, count($starts))));
    }
}

// Nächsten Kampf je Tatami starten
unset($_SESSION['tisch']);
$laufend = 0;
foreach ($tatamis as $t) {
    $zustand = $ansicht->fuerTatami((int) $t['id'], 5);
    foreach ($zustand['naechste'] as $n) {
        if ($zustand['laufend'] === null && $n['typ'] === 'begegnung') {
            $ergebnis->begegnungStarten($turnier, $n['id']);
            $laufend++;
            break;
        }
    }
}

// Ausruf: je Tatami die nächsten zwei Kämpfe aufgerufen; auf jeder zweiten Tatami fehlt
// beim zweiten Kampf ein Starter (Rückmeldung „nicht erschienen“ an die Leitung)
$ausruf = new AusrufService($db);
$aufgerufen = 0;
$fehlen = 0;
foreach ($tatamis as $i => $t) {
    $zustand = $ansicht->fuerTatami((int) $t['id'], 3);
    foreach (array_slice($zustand['naechste'], 0, 2) as $j => $n) {
        $seiten = $n['typ'] === 'auftritt' ? ['starter'] : ['aka', 'shiro'];
        foreach ($seiten as $k => $seite) {
            $fehlt = $j === 1 && $i % 2 === 0 && $k === count($seiten) - 1;
            $ausruf->markieren($turnier, $n['typ'], $n['id'], $seite, $fehlt ? 'fehlt' : 'da');
            $fehlen += $fehlt ? 1 : 0;
        }
        $ausruf->abschliessen($turnier, $n['typ'], $n['id']);
        $aufgerufen++;
    }
}

// Tisch-Abschluss: fertige Gruppen hat die Tischbesatzung abgeschlossen – bis auf die
// zuletzt fertig gewordene, damit ein Tablet die Karte „Gruppe … ist fertig“ zeigt
$offenLassen = null;
foreach ($tatamis as $t) {
    // letzte fertige Gruppe im Plan dieser Tatami (die Gruppe direkt vor der aktuellen)
    $offenLassen = $db->wert(
        "SELECT k.id FROM klasse k JOIN phase p ON p.klasse_id = k.id
          WHERE p.tatami_id = ? AND k.status IN ('beendet', 'bestaetigt') AND k.tisch_abschluss_am IS NULL
          ORDER BY p.reihenfolge DESC LIMIT 1",
        [$t['id']],
    );
    if ($offenLassen !== null) {
        // ein dort schon gestarteter Kampf der Folgegruppe wartet, bis die Gruppe abgeschlossen ist
        $db->query("UPDATE begegnung SET status = 'bereit' WHERE status = 'laeuft' AND phase_id IN (SELECT id FROM phase WHERE tatami_id = ?)", [$t['id']]);
        break;
    }
}
$db->query(
    "UPDATE klasse SET tisch_abschluss_am = ?, tisch_abschluss_von = 'Demo'
      WHERE turnier_id = ? AND status IN ('beendet', 'bestaetigt') AND tisch_abschluss_am IS NULL AND id <> ?",
    [date('Y-m-d H:i:s', time() - 600), $turnier, (int) $offenLassen],
);

// Demo-PINs, falls noch keine vergeben sind: Tatami n → n×1111, Leitung 9999
foreach ($tatamis as $t) {
    if ($t['pin_hash'] === null) {
        $db->update('tatami', ['pin_hash' => password_hash(str_repeat((string) $t['nummer'], 4), PASSWORD_DEFAULT)], ['id' => $t['id']]);
    }
}
if ($db->wert('SELECT leitung_pin_hash FROM turnier WHERE id = ?', [$turnier]) === null) {
    $db->update('turnier', ['leitung_pin_hash' => password_hash('9999', PASSWORD_DEFAULT)], ['id' => $turnier]);
}

// Abgeschlossene Klassen bestätigen (bis auf eine, damit „zu bestätigen“ sichtbar bleibt)
$fertig = array_map('intval', $db->spalte("SELECT id FROM klasse WHERE turnier_id = ? AND status = 'beendet' ORDER BY sortierung", [$turnier]));
foreach (array_slice($fertig, 0, max(0, count($fertig) - 1)) as $k) {
    $ergebnis->bestaetigen($turnier, $k);
}

printf(
    "Turnier #%d: %d Klassen ausgelost, %d Phasen verteilt, %d Ergebnisse eingetragen, %d Kämpfe laufen, %d Klassen bestätigt,\n"
    . "%d Kämpfe aufgerufen (%d Starter nicht erschienen). PINs (sofern nicht schon vergeben): Tatami n = n×1111, Leitung 9999.\n",
    $turnier,
    $ausgelost,
    $verteilt,
    $eingetragen,
    $laufend,
    max(0, count($fertig) - 1),
    $aufgerufen,
    $fehlen,
);
