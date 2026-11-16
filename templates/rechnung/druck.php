<?php
/**
 * Startgeld-Rechnungen je Dojo (A4, je Dojo ein Blatt; Drucken bzw. als PDF speichern).
 * @var array<string, mixed> $turnier
 * @var array{absender: string, bank: string, steuerhinweis: string, zahlungsziel: int, praefix: string, datum: string, text: string} $einstellungen
 * @var list<array<string, mixed>> $rechnungen
 */
$quittung = !empty($einstellungen['quittung']);
$this->layout('druck/layout', [
    'titel' => count($rechnungen) === 1
        ? ($quittung ? t('Quittung {verein}', ['verein' => $rechnungen[0]['verein']]) : t('Rechnung {verein}', ['verein' => $rechnungen[0]['verein']]))
        : ($quittung ? t('Quittungen') : t('Rechnungen')),
    'turnier' => $turnier,
    'zusatz' => '<a href="' . e(url("turnier/{$turnier['id']}/rechnungen")) . '">' . ($quittung ? te('Zu den Quittungen') : te('Zu den Rechnungen')) . '</a>',
]);
foreach ($rechnungen as $r) {
    echo $this->teil('rechnung/blatt', [
        'turnier' => $turnier,
        'einstellungen' => $einstellungen,
        'empfaenger' => ['verein' => $r['verein'], 'ansprechpartner' => $r['ansprechpartner'], 'email' => $r['email']],
        'nummer' => $r['nummer'],
        'datum' => $r['datum'],
        'faellig' => $r['bezahlt'] !== null ? null : $r['faellig'],
        'bezahlt' => $r['bezahlt'],
        'preis' => $r['startgeld'],
        'teilnehmer' => $r['startgeld']['teilnehmer'],
        'teams' => $r['startgeld']['teams'],
    ]);
}
