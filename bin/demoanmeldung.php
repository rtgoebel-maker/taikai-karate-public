<?php

declare(strict_types=1);

/*
 * Legt ein Demo-Turnier in der Meldephase an: Klassen aus der Vorlage
 * „Gruppenschema A–P“, Online-Anmeldung geöffnet (Startgeld je Start und je
 * Team) und einige Anmeldungen frei erfundener Dojos – eine davon bereits
 * freigegeben und übernommen.
 *
 *   php bin/demoanmeldung.php [--datum=2027-02-27] [--meldeschluss-stunden=72]
 *                             [--name="Demo-Turnier 2027"] [--ort=Musterstadt]
 *                             [--preis-start=12] [--preis-team=24]
 *
 * Der Meldeschluss ist ein Datum: Turnierbeginn minus Stunden, abgerundet auf den Tag.
 */

use Wettkampf\App;
use Wettkampf\Meldung\AnmeldungService;
use Wettkampf\Durchfuehrung\KlassenService;
use Wettkampf\Turnier\StammdatenService;
use Wettkampf\Turnier\TurnierPhaseService;
use Wettkampf\Domain\Auslosung\Zufall;
use Wettkampf\Domain\Graduierung;
use Wettkampf\Domain\TurnierPhase;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = App::erstellen(dirname(__DIR__));
$db = $app->db;
$optionen = ['datum' => '2027-02-27', 'meldeschluss-stunden' => '72', 'name' => null, 'ort' => 'Musterstadt', 'preis-start' => '12', 'preis-team' => '24'];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z-]+)=(.*)$/', $argument, $m) && array_key_exists($m[1], $optionen)) {
        $optionen[$m[1]] = $m[2];
    }
}
$datum = DateTimeImmutable::createFromFormat('!Y-m-d', $optionen['datum']);
if ($datum === false) {
    fwrite(STDERR, "Ungültiges Datum: {$optionen['datum']} (erwartet JJJJ-MM-TT)\n");
    exit(1);
}
$meldeschluss = $datum->modify('-' . (int) $optionen['meldeschluss-stunden'] . ' hours')->format('Y-m-d');

$stamm = new StammdatenService($db);
$turnier = $stamm->turnierAnlegen([
    'name' => $optionen['name'] ?? 'Demo-Turnier ' . $datum->format('Y'),
    'datum' => $datum->format('Y-m-d'),
    'ort' => $optionen['ort'],
    'halle' => 'Sporthalle',
    'anzahl_tatamis' => 4,
    'min_graduierung' => Graduierung::kyu(7),
    'meldeschluss' => $meldeschluss,
    'oeffentlich' => 1,
]);
(new KlassenService($db))->vorlageLaden($turnier, 'gruppen_ap');

(new TurnierPhaseService($db))->setzen($turnier, TurnierPhase::MELDEPHASE);
$anmeldung = new AnmeldungService($db);
$anmeldung->einstellungenSpeichern($turnier, [
    'preis_kata' => $optionen['preis-start'],
    'preis_kumite' => $optionen['preis-start'],
    'preis_kata_team' => $optionen['preis-team'],
    'preis_kumite_team' => $optionen['preis-team'],
    'hinweis' => "Startgebühr bitte bis zum Meldeschluss überweisen – Barzahlung vor Ort ist nicht möglich.\n"
        . "Empfänger: Demo-Karateverein e. V. · IBAN DE00 0000 0000 0000 0000 00 · Verwendungszweck: Dojo-Name\n"
        . 'Bei Nichterscheinen keine Erstattung.',
]);

// --- Anmeldungen erfundener Dojos --------------------------------------------------

$z = new Zufall('demoanmeldung' . $turnier);
$vornamen = ['w' => ['Anna', 'Lena', 'Mia', 'Emma', 'Lea', 'Sophie', 'Hannah', 'Marie', 'Clara', 'Ida', 'Nele', 'Paula'], 'm' => ['Ben', 'Paul', 'Leon', 'Finn', 'Noah', 'Elias', 'Jonas', 'Luis', 'Felix', 'Max', 'Emil', 'Theo']];
$nachnamen = ['Berger', 'Huber', 'Wagner', 'Bauer', 'Lehner', 'Gruber', 'Brandl', 'Moser', 'Hofer', 'Kerner', 'Lindner', 'Seidl', 'Winkler', 'Raab'];
$dojos = [
    ['Karate Dojo Bergheim', 'Sabine Lehner', 9],
    ['TSV Seefeld Karate', 'Thomas Gruber', 12],
    ['Budokan Isartal', 'Petra Hofer', 7],
    ['Shotokan Club Moosach', 'Michael Raab', 10],
    ['JKA Dojo Lindenau', 'Andrea Seidl', 6],
];

/** Ein Starter passend zu den Gruppen der Ausschreibung (Alter am Turniertag). */
$starter = static function (?string $geschlecht = null, ?int $alter = null) use ($z, $vornamen, $nachnamen, $datum): array {
    $geschlecht ??= $z->zahl(2) === 0 ? 'w' : 'm';
    $alter ??= 8 + $z->zahl(14);
    $grad = match (true) {
        $alter < 12 => Graduierung::kyu(7 - $z->zahl(3)),
        $alter < 18 => $z->zahl(2) === 0 ? Graduierung::kyu(6 - $z->zahl(3)) : Graduierung::kyu(3 - $z->zahl(3)),
        default => $z->zahl(4) === 0 ? Graduierung::dan(1 + $z->zahl(2)) : Graduierung::kyu(3 - $z->zahl(3)),
    };
    $kumite = match (true) {
        $alter >= 12 && $alter <= 14 && $grad >= Graduierung::kyu(3) => $z->zahl(2) === 0 ? 'kumite:jiyu_ippon' : 'kumite:jiyu',
        default => 'kumite',
    };
    $disziplinen = $z->zahl(4);
    return [
        'vorname' => $vornamen[$geschlecht][$z->zahl(count($vornamen[$geschlecht]))],
        'nachname' => $nachnamen[$z->zahl(count($nachnamen))],
        'geschlecht' => $geschlecht,
        'geburtsdatum' => $datum->modify("-$alter years")->modify('-' . (1 + $z->zahl(360)) . ' days')->format('Y-m-d'),
        'graduierung' => $grad,
        'kata' => $disziplinen !== 0 ? '1' : '',
        'kumite' => $disziplinen !== 1 ? $kumite : '',
    ];
};

$links = [];
foreach ($dojos as $nr => [$dojo, $kontakt, $anzahl]) {
    $teilnehmer = [];
    for ($i = 1; $i <= $anzahl; $i++) {
        $teilnehmer[$i] = $starter();
    }
    if ($nr === 1 || $nr === 3) {
        // drei Erwachsene für ein Kumite-Team (Männer bzw. Frauen)
        for ($i = 0; $i < 3; $i++) {
            $teilnehmer[] = $starter($nr === 1 ? 'm' : 'w', 19 + $z->zahl(10));
        }
    }
    // Kata-Team (gemischt) aus den drei Jüngsten; Kumite-Team aus drei Erwachsenen
    // ab 3. Kyu desselben Geschlechts (die Kumite-Team-Gruppen sind getrennt)
    $nachAlter = array_keys($teilnehmer);
    usort($nachAlter, static fn($a, $b) => strcmp($teilnehmer[$b]['geburtsdatum'], $teilnehmer[$a]['geburtsdatum']));
    $teams = [['name' => "$dojo 1", 'disziplin' => 'kata_team', 'mitglieder' => array_map('strval', array_slice($nachAlter, 0, 3)), 'ersatz' => '']];
    foreach (['w', 'm'] as $geschlecht) {
        $erwachsene = array_values(array_filter(array_keys($teilnehmer), static fn($k) => $teilnehmer[$k]['geschlecht'] === $geschlecht
            && $teilnehmer[$k]['geburtsdatum'] <= $datum->modify('-18 years')->format('Y-m-d')
            && $teilnehmer[$k]['graduierung'] >= Graduierung::kyu(3)));
        if (count($erwachsene) >= 3) {
            $teams[] = ['name' => "$dojo " . (count($teams) + 1), 'disziplin' => 'kumite_team',
                'mitglieder' => array_map('strval', array_slice($erwachsene, 0, 3)), 'ersatz' => (string) ($erwachsene[3] ?? '')];
        }
    }
    $code = $anmeldung->einreichen($turnier, [
        'dojo' => $dojo,
        'ansprechpartner' => $kontakt,
        'email' => strtolower(str_replace(' ', '.', $kontakt)) . '@example.org',
        'telefon' => '',
        'bemerkung' => $nr === 2 ? 'Zwei Starter reisen erst um 11 Uhr an.' : '',
        'teilnehmer' => $teilnehmer,
        'teams' => $teams,
    ]);
    if ($nr === 0) {
        $anmeldung->freigeben($turnier, (int) $anmeldung->perCode($turnier, $code)['id']);
    }
    $links[$dojo] = "/anmeldung/$turnier/$code";
}

printf("Turnier #%d angelegt: %s, Meldeschluss %s.\n", $turnier, $datum->format('d.m.Y'), date('d.m.Y', strtotime($meldeschluss)));
printf("Anmeldeseite für neue Dojos: /anmeldung/%d\n", $turnier);
echo "Bearbeitungslinks der Demo-Dojos (das erste ist bereits freigegeben):\n";
foreach ($links as $dojo => $link) {
    echo "  $dojo: $link\n";
}
echo "Leitung: /turnier/$turnier/anmeldungen\n";
