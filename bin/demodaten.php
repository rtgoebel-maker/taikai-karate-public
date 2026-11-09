<?php

declare(strict_types=1);

/*
 * Füllt ein Turnier mit frei erfundenen Teilnehmern.
 *   php bin/demodaten.php [Anzahl, Standard 150]                 legt ein neues Demo-Turnier an
 *   php bin/demodaten.php [Anzahl] --turnier=ID                  füllt ein vorhandenes Turnier
 *   php bin/demodaten.php [Anzahl] --datum=JJJJ-MM-TT --name="…"  neues Turnier mit Datum/Name
 * Hat das Turnier noch keine Klassen, wird die Vorlage „Gruppenschema A–P“ geladen.
 */

use Wettkampf\App;
use Wettkampf\Durchfuehrung\KlassenService;
use Wettkampf\Turnier\StammdatenService;
use Wettkampf\Meldung\TeamService;
use Wettkampf\Meldung\ZuordnungService;
use Wettkampf\Domain\Auslosung\Zufall;
use Wettkampf\Domain\Graduierung;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = App::erstellen(dirname(__DIR__));
$db = $app->db;
$anzahl = 150;
$turnier = null;
$datum = date('Y') . '-02-15';
$name = null;
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--turnier=(\d+)$/', $argument, $m)) {
        $turnier = (int) $m[1];
    } elseif (preg_match('/^--datum=(\d{4}-\d{2}-\d{2})$/', $argument, $m)) {
        $datum = $m[1];
    } elseif (preg_match('/^--name=(.+)$/', $argument, $m)) {
        $name = $m[1];
    } elseif (ctype_digit($argument)) {
        $anzahl = (int) $argument;
    }
}
$z = new Zufall('demo' . ($turnier ?? ''));

$vornamenW = ['Anna', 'Lena', 'Mia', 'Emma', 'Lea', 'Sophie', 'Hannah', 'Marie', 'Clara', 'Ida', 'Frieda', 'Jana', 'Nele', 'Paula', 'Romy'];
$vornamenM = ['Ben', 'Paul', 'Leon', 'Finn', 'Noah', 'Elias', 'Jonas', 'Luis', 'Felix', 'Max', 'Emil', 'Anton', 'Theo', 'Jakob', 'Moritz'];
$nachnamen = ['Berger', 'Huber', 'Wagner', 'Bauer', 'Lehner', 'Gruber', 'Brandl', 'Moser', 'Hofer', 'Kerner', 'Lindner', 'Seidl', 'Winkler', 'Raab', 'Stadler', 'Ostler', 'Kain', 'Pichler'];
$vereine = ['Karate Dojo Bergheim', 'TSV Seefeld Karate', 'Budokan Isartal', 'SV Waldkirch', 'Shotokan Club Moosach', 'JKA Dojo Lindenau'];

$stamm = new StammdatenService($db);
if ($turnier === null) {
    $turnier = $stamm->turnierAnlegen([
        'name' => $name ?? 'Demo-Turnier ' . substr($datum, 0, 4),
        'datum' => $datum,
        'ort' => 'Musterstadt',
        'halle' => 'Sporthalle Süd',
        'anzahl_tatamis' => 4,
        'min_graduierung' => Graduierung::kyu(7),
        'oeffentlich' => 1,
    ]);
} elseif ($db->wert('SELECT id FROM turnier WHERE id = ?', [$turnier]) === null) {
    fwrite(STDERR, "Turnier #$turnier gibt es nicht.\n");
    exit(1);
}
if ((int) $db->wert('SELECT COUNT(*) FROM klasse WHERE turnier_id = ?', [$turnier]) === 0) {
    (new KlassenService($db))->vorlageLaden($turnier, 'gruppen_ap');
}

// Geburtsdaten so wählen, dass das Alter am Turniertag genau stimmt
$turniertag = new DateTimeImmutable((string) $db->wert('SELECT datum FROM turnier WHERE id = ?', [$turnier]));
$teilnehmer = [];
for ($i = 0; $i < $anzahl; $i++) {
    $geschlecht = $z->zahl(2) === 0 ? 'w' : 'm';
    $alter = 8 + $z->zahl(14);
    // Graduierungen passend zu den Gruppen der Vorlage (bis 11: ab 7. Kyu,
    // 12–17: 6.–4. Kyu oder ab 3. Kyu, ab 18: ab 3. Kyu)
    $grad = match (true) {
        $alter < 12 => Graduierung::kyu(7 - $z->zahl(3)),
        $alter < 18 => $z->zahl(2) === 0 ? Graduierung::kyu(6 - $z->zahl(3)) : Graduierung::kyu(3 - $z->zahl(3)),
        default => $z->zahl(4) === 0 ? Graduierung::dan(1 + $z->zahl(2)) : Graduierung::kyu(3 - $z->zahl(3)),
    };
    $kumite = match (true) {
        $alter >= 12 && $alter <= 14 && $grad >= Graduierung::kyu(3) => $z->zahl(2) === 0 ? 'kumite:jiyu_ippon' : 'kumite:jiyu',
        default => 'kumite',
    };
    $disziplinen = $z->zahl(4) === 0 ? [$kumite] : ($z->zahl(3) === 0 ? ['kata'] : ['kata', $kumite]);
    $vorname = ($geschlecht === 'w' ? $vornamenW : $vornamenM)[$z->zahl(15)];
    $teilnehmer[] = $stamm->teilnehmerSpeichern($turnier, [
        'vorname' => $vorname,
        'nachname' => $nachnamen[$z->zahl(count($nachnamen))],
        'geschlecht' => $geschlecht,
        'geburtsdatum' => $turniertag->modify("-$alter years")->modify('-' . (1 + $z->zahl(360)) . ' days')->format('Y-m-d'),
        'graduierung' => $grad,
        'verein_name' => $vereine[$z->zahl(count($vereine))],
        'disziplinen' => $disziplinen,
    ]);
}

// ein paar Kata-Teams aus Kindern desselben Vereins
$teams = new TeamService($db);
foreach ($vereine as $nr => $verein) {
    $mitglieder = $db->spalte(
        "SELECT t.id FROM teilnahme t JOIN athlet a ON a.id = t.athlet_id JOIN verein v ON v.id = a.verein_id
          WHERE t.turnier_id = ? AND v.name = ? ORDER BY a.geburtsdatum DESC LIMIT 3",
        [$turnier, $verein],
    );
    if (count($mitglieder) === 3) {
        $teamId = $teams->teamSpeichern($turnier, "$verein 1", 'kata_team', (int) $db->wert('SELECT id FROM verein WHERE name = ?', [$verein]));
        foreach ($mitglieder as $m) {
            $teams->mitgliedHinzufuegen($turnier, $teamId, (int) $m);
        }
    }
}

$ergebnis = (new ZuordnungService($db))->alleZuordnen($turnier);
echo "Turnier #$turnier befüllt: $anzahl Teilnehmer, {$ergebnis['starts']} Starts, " . count($ergebnis['hinweise']) . " Hinweise.\n";
