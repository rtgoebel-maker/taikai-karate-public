<?php

declare(strict_types=1);

namespace Wettkampf\Betrieb\Http;

use Wettkampf\Kern\Http\Request;
use Wettkampf\Kern\Http\Response;
use Wettkampf\Kern\Http\Controller;
use Wettkampf\Kern\Http\NichtGefunden;

/** Hilfeseite für die Wettkampfleitung (aus einem Turnier heraus mit Turniermenü) und druckbare Handouts. */
final class HilfeController extends Controller
{
    /** Druckbare Handouts mit Screenshots: Schlüssel = Vorlage unter templates/handout/ */
    public const HANDOUTS = [
        'flyer' => 'Überblick – Flyer',
        'tisch' => 'Tischbesatzung – Tablet',
        'ausruf' => 'Ausruf – Handy',
        'leitung' => 'Wettkampfleitung',
    ];

    public function index(Request $request): Response
    {
        $turnierId = $request->zahl('turnier');
        $turnier = $turnierId !== null ? $this->db->eine('SELECT * FROM turnier WHERE id = ?', [$turnierId]) : null;
        return $this->seite('hilfe/index', ['turnier' => $turnier, 'aktiv' => 'hilfe']);
    }

    public function handout(Request $request, string $name): Response
    {
        if (!isset(self::HANDOUTS[$name])) {
            throw new NichtGefunden('Handout nicht gefunden.');
        }
        $turnierId = $request->zahl('turnier');
        return $this->seite('handout/seite', [
            'name' => $name,
            'titel' => self::HANDOUTS[$name],
            'zurueck' => $turnierId !== null ? url("turnier/$turnierId/druck") : url('hilfe'),
        ]);
    }
}
