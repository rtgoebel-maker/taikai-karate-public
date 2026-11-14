<?php

declare(strict_types=1);

namespace Wettkampf\Datenschutz\Http;

use Wettkampf\Datenschutz\Auskunft;
use Wettkampf\Datenschutz\Pseudonymisierung;
use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;

/**
 * Betroffenenrechte (Administration): Auskunft nach Art. 15 als Bericht zum Drucken bzw. JSON, Löschung nach Art. 17
 * als Pseudonymisierung der Person über alle Turniere. Berichtigung erfolgt wie gewohnt beim Teilnehmer im Turnier.
 */
final class AuskunftController extends Controller
{
    public function index(Request $request): Response
    {
        $name = mb_substr($request->text('name'), 0, 100);
        $geburtsdatum = $request->text('geburtsdatum');
        return $this->seite('datenschutz/auskunft', [
            'name' => $name,
            'geburtsdatum' => $geburtsdatum,
            'email' => mb_substr($request->text('email'), 0, 190),
            'treffer' => $name !== '' ? (new Auskunft($this->db))->suchen($name, $geburtsdatum) : null,
        ]);
    }

    public function starter(Request $request): Response
    {
        $ids = $this->ids($request->get('ids'));
        $bericht = (new Auskunft($this->db))->starter($ids);
        if ($bericht['personen'] === []) {
            throw new FachFehler('Bitte mindestens einen Treffer auswählen.');
        }
        if ($request->get('format') === 'json') {
            return $this->json($bericht, 'auskunft-starter-' . date('Ymd') . '.json');
        }
        return Response::html($this->app->view->render('datenschutz/bericht_starter', ['bericht' => $bericht, 'ids' => $ids]));
    }

    public function kontakt(Request $request): Response
    {
        $email = mb_substr($request->text('email'), 0, 190);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new FachFehler('Bitte eine gültige E-Mail-Adresse eingeben.');
        }
        $bericht = (new Auskunft($this->db))->kontakt($email);
        if ($request->get('format') === 'json') {
            return $this->json($bericht, 'auskunft-kontakt-' . date('Ymd') . '.json');
        }
        return Response::html($this->app->view->render('datenschutz/bericht_kontakt', ['bericht' => $bericht]));
    }

    /** Löschantrag: Person über alle Turniere pseudonymisieren (Ergebnisse bleiben als „Gelöscht N“). */
    public function loeschen(Request $request): Response
    {
        $ids = $this->ids($request->post('ids'));
        if ($ids === [] || $request->text('bestaetigung') !== 'LÖSCHEN') {
            throw new FachFehler('Zur Bestätigung bitte LÖSCHEN eingeben.');
        }
        $anzahl = (new Pseudonymisierung($this->db))->person($ids);
        $this->erfolg("Die Person wurde gelöscht (pseudonymisiert) – {$anzahl} " . ($anzahl === 1 ? 'Teilnahme' : 'Teilnahmen') . ' betroffen. Abrechnungsunterlagen im Archiv bleiben davon unberührt.');
        return $this->weiter('datenschutz/auskunft');
    }

    /** @return list<int> */
    private function ids(mixed $wert): array
    {
        $ids = [];
        foreach (is_array($wert) ? $wert : [$wert] as $id) {
            if (is_scalar($id) && ctype_digit((string) $id)) {
                $ids[] = (int) $id;
            }
        }
        return array_slice(array_values(array_unique($ids)), 0, 20);
    }

    /** @param array<string, mixed> $daten */
    private function json(array $daten, string $name): Response
    {
        return Response::download(
            (string) json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            $name,
            'application/json; charset=utf-8',
        );
    }
}
