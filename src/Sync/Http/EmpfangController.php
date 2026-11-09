<?php

declare(strict_types=1);

namespace Wettkampf\Sync\Http;

use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Sync\EmpfangService;
use Wettkampf\Sync\SyncAbgewiesen;

/** Online: Sendungen des Hallen-Laptops (Zugang 'maschine' – geschützt durch die Signatur der Übergabe). */
final class EmpfangController extends Controller
{
    public function stand(Request $request): Response
    {
        return $this->antwort(fn(EmpfangService $s) => $s->annehmen($request->body, $request->headers));
    }

    public function antraege(Request $request): Response
    {
        return $this->antwort(fn(EmpfangService $s) => $s->antraege($request->body, $request->headers));
    }

    public function rueckgabe(Request $request): Response
    {
        return $this->antwort(function (EmpfangService $s) use ($request) {
            $s->rueckgabe($request->body, $request->headers);
            return ['ok' => true];
        });
    }

    /** @param callable(EmpfangService): array<string, mixed> $tun */
    private function antwort(callable $tun): Response
    {
        try {
            return Response::json($tun(new EmpfangService($this->db)));
        } catch (SyncAbgewiesen $e) {
            return Response::json(['fehler' => $e->getMessage(), 'grund' => $e->grund], $e->status);
        }
    }
}
