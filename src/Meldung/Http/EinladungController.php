<?php

declare(strict_types=1);

namespace Wettkampf\Meldung\Http;

use Wettkampf\Domain\FachFehler;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\NichtGefunden;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Meldung\AnmeldungService;

/**
 * Freiwillige Einladungen zu künftigen Turnieren: Liste, Export und Versand (Administration), Abmeldung über den Link
 * in der Einladung (öffentlich, zweistufig: GET zeigt nur, POST löscht – Link-Vorschauen in Mailprogrammen melden nicht ab).
 */
final class EinladungController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->seite('einladung/index', [
            'liste' => $this->app->einladungen()->liste(),
            'turniere' => $this->db->alle('SELECT * FROM turnier WHERE datum >= ? ORDER BY datum, id', [date('Y-m-d')]),
            'mail' => $this->app->postausgang() !== null,
        ]);
    }

    /** CSV (z. B. für einen Serienbrief) mit persönlichem Abmeldelink je Adresse. */
    public function export(Request $request): Response
    {
        $einladungen = $this->app->einladungen();
        $basis = rtrim((string) ($this->app->mailKonfiguration()['oeffentliche_url'] ?? ''), '/');
        $basis = preg_match('#^https?://#', $basis) ? $basis : '';
        $csv = "\xEF\xBB\xBF" . '"E-Mail";"Name";"Dojo";"Eingewilligt am";"Herkunft";"Abmeldelink"' . "\r\n";
        foreach ($einladungen->liste() as $z) {
            $link = $basis . url('einladung/abmelden/' . $z['id'] . '/' . $einladungen->token((int) $z['id'], (string) $z['email']));
            $csv .= implode(';', array_map(static function (mixed $w): string {
                $t = (string) $w;
                if ($t !== '' && in_array($t[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                    $t = "'" . $t; // CSV-Injection (OWASP)
                }
                return '"' . str_replace('"', '""', $t) . '"';
            }, [$z['email'], $z['name'], $z['dojo'], $z['eingewilligt_am'], $z['herkunft'], $link])) . "\r\n";
        }
        return Response::download($csv, 'einladungen-' . date('Ymd') . '.csv', 'text/csv; charset=utf-8');
    }

    public function loeschen(Request $request, int $einladungid): Response
    {
        $this->app->einladungen()->widerrufen($einladungid, 'Administration');
        $this->erfolg('Eintrag gelöscht – diese Adresse erhält keine Einladungen mehr.');
        return $this->weiter('einladungen');
    }

    public function senden(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        $post = $this->app->postausgang() ?? throw new FachFehler('Für Einladungen bitte zuerst den E-Mail-Versand einrichten (System → E-Mail-Versand).');
        $schluessel = (new AnmeldungService($this->db))->anmeldeSchluessel($id);
        $ds = (json_decode((string) $turnier['einstellungen'], true) ?: [])['datenschutz'] ?? [];
        $veranstalter = trim(explode("\n", is_array($ds) ? (string) ($ds['verantwortlicher'] ?? '') : '')[0]);
        $anzahl = $this->app->einladungen()->senden($turnier, "melden/$schluessel", "melden/$schluessel/datenschutz", $veranstalter, $post);
        $this->erfolg("Einladung zu „{$turnier['name']}“ an $anzahl " . ($anzahl === 1 ? 'Adresse' : 'Adressen') . ' in den Versand gegeben.');
        return $this->weiter('einladungen');
    }

    public function abmeldenFrage(Request $request, int $einladungid, string $token): Response
    {
        $eintrag = $this->app->einladungen()->perToken($einladungid, $token);
        return Response::html($this->app->view->render('einladung/abmelden', ['eintrag' => $eintrag, 'erledigt' => false, 'ziel' => url("einladung/abmelden/$einladungid/$token")]));
    }

    public function abmelden(Request $request, int $einladungid, string $token): Response
    {
        $einladungen = $this->app->einladungen();
        if ($einladungen->perToken($einladungid, $token) === null) {
            throw new NichtGefunden('Dieser Abmeldelink ist ungültig oder die Adresse ist bereits abgemeldet.');
        }
        $einladungen->widerrufen($einladungid, 'Abmeldelink');
        return Response::html($this->app->view->render('einladung/abmelden', ['eintrag' => null, 'erledigt' => true, 'ziel' => '']));
    }
}
