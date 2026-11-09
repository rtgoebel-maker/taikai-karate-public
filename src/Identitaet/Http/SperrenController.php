<?php

declare(strict_types=1);

namespace Wettkampf\Identitaet\Http;

use Wettkampf\Identitaet\AnmeldeSchutz;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;

/**
 * System → Gesperrte Anmeldungen (nur Administration): laufende Sperren und Fehlversuche aus dem AnmeldeSchutz
 * (Login, Passwortbestätigung, Tatami- und Leitungs-PIN, Übergabe, Einrichtung) mit IP, dazu der Verlauf der letzten
 * 30 Tage. Sperren lassen sich vorzeitig aufheben (z. B. wenn sich jemand nur vertippt hat).
 */
final class SperrenController extends Controller
{
    public function index(Request $request): Response
    {
        $eintraege = [];
        foreach ((new AnmeldeSchutz($this->db))->liste() as $e) {
            $eintraege[] = $e + $this->beschreibung($e['ziel']);
        }
        $verlauf = [];
        $seit = date('Y-m-d H:i:s', time() - 30 * 86400);
        foreach ($this->db->alle(
            "SELECT zeit, benutzer, aktion, daten FROM audit_log WHERE entitaet = 'anmeldung' AND aktion IN ('gesperrt', 'pausiert', 'entsperrt') AND zeit >= ?
              ORDER BY zeit DESC, id DESC LIMIT 200",
            [$seit],
        ) as $z) {
            $daten = json_decode((string) $z['daten'], true);
            $daten = is_array($daten) ? $daten : [];
            $ziel = isset($daten['ziel']) && is_string($daten['ziel']) ? $daten['ziel'] : null;
            $verlauf[] = [
                'zeit' => (string) $z['zeit'],
                'aktion' => (string) $z['aktion'],
                'von' => (string) ($z['benutzer'] ?? ''),
                'ip' => isset($daten['ip']) && is_string($daten['ip']) && $daten['ip'] !== '*' ? $daten['ip'] : null,
                'sekunden' => isset($daten['sekunden']) ? (int) $daten['sekunden'] : null,
            ] + $this->beschreibung($ziel);
        }
        return $this->seite('system/sperren', ['eintraege' => $eintraege, 'verlauf' => $verlauf]);
    }

    public function aufheben(Request $request): Response
    {
        $schluessel = $request->text('schluessel');
        if (preg_match('/^[0-9a-f]{64}$/', $schluessel) === 1) {
            (new AnmeldeSchutz($this->db))->aufheben($schluessel);
            $this->erfolg('Sperre aufgehoben – die Anmeldung ist von dort wieder möglich.');
        }
        return $this->weiter('system/sperren');
    }

    /**
     * Lesbare Beschreibung eines Ziels aus dem AnmeldeSchutz.
     *
     * @return array{art: string, wer: string}
     */
    private function beschreibung(?string $ziel): array
    {
        if ($ziel === null) {
            return ['art' => 'unbekannt', 'wer' => 'vor dem Update gesperrt – Ziel nicht gespeichert'];
        }
        [$art, $wert] = array_pad(explode(':', $ziel, 2), 2, '');
        $id = (int) $wert;
        return match ($art) {
            'login' => ['art' => 'Anmeldung', 'wer' => $wert],
            'uebergabe' => ['art' => 'Übergabe an den Hallen-Laptop', 'wer' => $wert],
            'passwort-bestaetigung' => ['art' => 'Passwort bestätigen', 'wer' => $this->konto($id)],
            'system-passwort' => ['art' => 'Passwort bestätigen (E-Mail-Versand)', 'wer' => $this->konto($id)],
            'tisch' => ['art' => 'Tatami-PIN', 'wer' => $this->tatami($id)],
            'leitung-pin' => ['art' => 'Leitungs-PIN', 'wer' => $this->turnierName($id)],
            'setup' => ['art' => 'Ersteinrichtung', 'wer' => 'Einrichtungscode'],
            default => ['art' => $art, 'wer' => $wert],
        };
    }

    private function konto(int $id): string
    {
        $k = $this->db->eine('SELECT name, email FROM benutzer WHERE id = ?', [$id]);
        return $k === null ? "Konto #$id (gelöscht)" : trim((string) $k['name'] . ($k['email'] ? ' (' . $k['email'] . ')' : ''));
    }

    private function tatami(int $id): string
    {
        $t = $this->db->eine('SELECT ta.bezeichnung, tu.name FROM tatami ta JOIN turnier tu ON tu.id = ta.turnier_id WHERE ta.id = ?', [$id]);
        return $t === null ? "Tatami #$id (gelöscht)" : $t['bezeichnung'] . ' – ' . $t['name'];
    }

    private function turnierName(int $id): string
    {
        $name = $this->db->wert('SELECT name FROM turnier WHERE id = ?', [$id]);
        return $name === null ? "Turnier #$id (gelöscht)" : (string) $name;
    }
}
