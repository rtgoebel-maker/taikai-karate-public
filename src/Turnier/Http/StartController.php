<?php

declare(strict_types=1);

namespace Wettkampf\Turnier\Http;

use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\Session;

final class StartController extends Controller
{
    public function index(Request $request): Response
    {
        // Administration: alle Turniere; Turnierleitung: nur die eigenen
        $turniere = array_values(array_filter(
            $this->db->alle('SELECT * FROM turnier ORDER BY datum DESC, id DESC'),
            static fn(array $t) => Session::leitet((int) $t['id']),
        ));
        return $this->seite('start', ['turniere' => $turniere, 'istAdmin' => Session::istAdmin()]);
    }
}
