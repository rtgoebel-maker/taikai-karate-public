<?php
/**
 * Startgeld-Rechnungen je Dojo (A4, je Dojo ein Blatt; Drucken bzw. als PDF speichern).
 * @var array<string, mixed> $turnier
 * @var array{absender: string, bank: string, steuerhinweis: string, zahlungsziel: int, praefix: string, datum: string, text: string} $einstellungen
 * @var list<array<string, mixed>> $rechnungen
 */
$this->layout('druck/layout', [
    'titel' => count($rechnungen) === 1 ? 'Rechnung ' . $rechnungen[0]['verein'] : 'Rechnungen',
    'turnier' => $turnier,
    'zusatz' => '<a href="' . e(url("turnier/{$turnier['id']}/rechnungen")) . '">Zu den Rechnungen</a>',
]);
foreach ($rechnungen as $r) {
    echo $this->teil('rechnung/blatt', [
        'turnier' => $turnier,
        'einstellungen' => $einstellungen,
        'empfaenger' => ['verein' => $r['verein'], 'ansprechpartner' => $r['ansprechpartner'], 'email' => $r['email']],
        'nummer' => $r['nummer'],
        'datum' => $r['datum'],
        'faellig' => $r['faellig'],
        'bezahlt' => null,
        'preis' => $r['startgeld'],
        'teilnehmer' => $r['startgeld']['teilnehmer'],
        'teams' => $r['startgeld']['teams'],
    ]);
}
