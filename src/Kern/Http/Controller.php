<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http;

use Wettkampf\App;
use Wettkampf\Kern\Db\Database;

abstract class Controller
{
    protected readonly Database $db;

    public function __construct(protected readonly App $app)
    {
        $this->db = $app->db;
    }

    /** @param array<string, mixed> $daten */
    protected function seite(string $template, array $daten = []): Response
    {
        if (isset($daten['turnier']['id']) && Session::leitet((int) $daten['turnier']['id'])) {
            $daten += $this->app->turnierKopf((int) $daten['turnier']['id']);
        }
        return Response::html($this->app->view->render($template, $daten));
    }

    /** @param array<string, scalar|null> $query */
    protected function weiter(string $pfad, array $query = []): Response
    {
        return Response::weiterleiten(Url::zu($pfad, $query));
    }

    /**
     * Nach dem Speichern: zurück in den Einrichtungs-Assistenten, wenn das Formular von dort kam (Feld 'assistent'),
     * sonst zur üblichen Seite. Nur Assistenten-Pfade sind erlaubt – keine beliebige Weiterleitung (OWASP A01).
     */
    protected function weiterNach(Request $request, string $standard): Response
    {
        $ziel = (string) ($request->post['assistent'] ?? '');
        return $this->weiter(preg_match('#^turnier/\d+/assistent/[a-z]+$#', $ziel) ? $ziel : $standard);
    }

    protected function erfolg(string $text): void
    {
        Session::flash('erfolg', $text);
    }

    /** @return array<string, mixed> */
    protected function turnier(int $id): array
    {
        $turnier = $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$id]);
        if ($turnier === null) {
            throw new NichtGefunden('Turnier nicht gefunden.');
        }
        return $turnier;
    }

    /**
     * Lädt einen Datensatz und prüft, dass er zum Turnier gehört.
     *
     * @return array<string, mixed>
     */
    protected function datensatz(string $tabelle, int $id, ?int $turnierId = null): array
    {
        $zeile = $this->db->eine("SELECT * FROM $tabelle WHERE id = ?", [$id]);
        if ($zeile === null || ($turnierId !== null && (int) $zeile['turnier_id'] !== $turnierId)) {
            throw new NichtGefunden('Datensatz nicht gefunden.');
        }
        return $zeile;
    }
}
