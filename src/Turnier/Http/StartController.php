<?php

declare(strict_types=1);

namespace Wettkampf\Turnier\Http;

use Wettkampf\Domain\TurnierPhase;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Turnier\DemoBesitz;

final class StartController extends Controller
{
    /** Filter „Art“ der Turnierliste (Administration) */
    public const ARTEN = ['echt' => 'Echte Turniere', 'demo' => 'Demo-Turniere', 'vorgemerkt' => 'Zum Löschen vorgemerkt'];

    public function index(Request $request): Response
    {
        // Administration: alle Turniere; Turnierleitung: nur die eigenen
        $turniere = array_values(array_filter(
            $this->db->alle('SELECT * FROM turnier ORDER BY datum DESC, id DESC'),
            static fn(array $t) => Session::leitet((int) $t['id']),
        ));
        $besitzer = array_column($this->db->alle('SELECT id, name FROM benutzer'), 'name', 'id');
        // Freigaben an Demo-Zugänge (Turnierleitung eines Demo-Kontos)
        $freigaben = [];
        foreach ($this->db->alle("SELECT r.turnier_id, r.benutzer_id, b.name FROM turnier_rolle r JOIN benutzer b ON b.id = r.benutzer_id WHERE b.rolle = 'demo' ORDER BY b.name") as $r) {
            $freigaben[(int) $r['turnier_id']][(int) $r['benutzer_id']] = (string) $r['name'];
        }
        foreach ($turniere as &$t) {
            $e = json_decode((string) ($t['einstellungen'] ?? ''), true);
            $id = DemoBesitz::besitzer($t);
            $t['demo'] = is_array($e) && (is_array($e['demo'] ?? null) || $id !== null) ? [
                'besitzer' => $id === null ? null : (string) ($besitzer[$id] ?? t('gelöschtes Konto')),
                'loeschen_ab' => DemoBesitz::loeschenAb($t),
                'freigegeben' => array_values(array_diff_key($freigaben[(int) $t['id']] ?? [], $id === null ? [] : [$id => true])),
            ] : null;
        }
        unset($t);

        $filter = ['q' => mb_substr($request->text('q'), 0, 100), 'status' => $request->text('status'), 'art' => $request->text('art')];
        $alle = count($turniere);
        if (Session::istAdmin()) {
            $turniere = array_values(array_filter($turniere, static function (array $t) use ($filter): bool {
                $text = mb_strtolower($t['name'] . ' ' . $t['ort']);
                return ($filter['q'] === '' || str_contains($text, mb_strtolower($filter['q'])))
                    && ($filter['status'] === '' || TurnierPhase::von($t) === $filter['status'])
                    && match ($filter['art']) {
                        'echt' => $t['demo'] === null,
                        'demo' => $t['demo'] !== null,
                        'vorgemerkt' => ($t['demo']['loeschen_ab'] ?? null) !== null,
                        default => true,
                    };
            }));
        }
        return $this->seite('start', ['turniere' => $turniere, 'istAdmin' => Session::istAdmin(), 'filter' => $filter, 'alle' => $alle]);
    }
}
