<?php

declare(strict_types=1);

namespace Wettkampf\Betrieb\Http;

use Wettkampf\Betrieb\VerlaufAnsicht;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Session;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Protokoll;

/** Änderungsverlauf (Audit-Log), nur lesend: je Turnier für dessen Leitung, global für die Administration. */
final class VerlaufController extends Controller
{
    public function turnierVerlauf(Request $request, int $id): Response
    {
        $turnier = $this->turnier($id);
        return $this->seite('verlauf/index', $this->ansicht($request, $id) + ['turnier' => $turnier, 'aktiv' => 'verlauf']);
    }

    public function globalerVerlauf(Request $request): Response
    {
        (new Protokoll($this->db))->aufraeumen();
        return $this->seite('verlauf/index', $this->ansicht($request, null, Session::istDemo() ? array_map('intval', (array) (Session::benutzer()['turniere'] ?? [])) : null) + ['turnier' => null, 'aktiv' => 'verlauf']);
    }

    /**
     * @param list<int>|null $nurTurniere
     * @return array<string, mixed>
     */
    private function ansicht(Request $request, ?int $turnierId, ?array $nurTurniere = null): array
    {
        $filter = ['objekt' => $request->text('objekt'), 'aktion' => $request->text('aktion'), 'wer' => mb_substr($request->text('wer'), 0, 100)];
        return (new VerlaufAnsicht($this->db))->laden($turnierId, $filter, $request->zahl('seite', 1) ?? 1, $nurTurniere) + ['filter' => $filter];
    }
}
