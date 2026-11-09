<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http;

/**
 * Routentabelle der Anwendung (je Modul src/<Modul>/routen.php, geladen von App::routen). Das Zuordnen der Anfragen übernimmt
 * Slim (App::slim); hier stehen nur Muster, Ziel und Zugangsstufe. Muster wie
 * '/turnier/{id}/klassen' – Parameter in {…} kommen als Strings, Ziffern-Parameter mit
 * Namen *id als int.
 */
final class Router
{
    /**
     * Zugangsstufen: 'oeffentlich' (ohne Anmeldung), 'tisch' (Tablet-Anmeldung oder Leitung),
     * 'leitung' (Administration oder Turnierleitung irgendeines Turniers), 'turnier' (Administration
     * oder Turnierleitung des Turniers aus dem Routenparameter {id}), 'coach' (Konto mit Dojo), 'konto' (jedes angemeldete Konto,
     * z. B. ein selbst angelegtes Dojo-Konto vor der Zuordnung zum Dojo: nur die eigenen Meldungen), 'admin' (globale Administration),
     * 'maschine' (Aufruf von Server zu Server ohne Sitzung und ohne CSRF-Token: die Route prüft selbst, z. B. Login + Code
     * oder HMAC-Signatur; nur unter /api/v1/sync/, siehe RouterTest).
     * Standard ist 'admin': eine vergessene Einstufung sperrt die Route, statt sie zu öffnen.
     */
    public const ZUGANG = ['oeffentlich', 'tisch', 'coach', 'konto', 'leitung', 'turnier', 'admin', 'maschine'];

    /** @var list<array{methode: string, muster: string, ziel: array{0: class-string, 1: string}, zugang: string}> */
    private array $routen = [];

    /**
     * @param array{0: class-string, 1: string} $ziel
     * @param string $zugang siehe ZUGANG
     */
    public function add(string $methode, string $muster, array $ziel, string $zugang = 'admin'): void
    {
        if (!in_array($zugang, self::ZUGANG, true)) {
            throw new \InvalidArgumentException("Unbekannte Zugangsstufe: $zugang");
        }
        if ($zugang === 'turnier' && !str_contains($muster, '{id}')) {
            throw new \InvalidArgumentException("Zugang 'turnier' braucht den Routenparameter {id}: $muster");
        }
        $this->routen[] = ['methode' => $methode, 'muster' => $muster, 'ziel' => $ziel, 'zugang' => $zugang];
    }

    /** @param array{0: class-string, 1: string} $ziel */
    public function get(string $muster, array $ziel, string $zugang = 'admin'): void
    {
        $this->add('GET', $muster, $ziel, $zugang);
    }

    /** @param array{0: class-string, 1: string} $ziel */
    public function post(string $muster, array $ziel, string $zugang = 'admin'): void
    {
        $this->add('POST', $muster, $ziel, $zugang);
    }

    /** @return list<array{methode: string, muster: string, ziel: array{0: class-string, 1: string}, zugang: string}> */
    public function alle(): array
    {
        return $this->routen;
    }

    /**
     * Routenparameter für den Controller: Ziffern bei Namen auf *id als int.
     *
     * @param array<string, string> $werte
     * @return list<int|string>
     */
    public static function parameter(array $werte): array
    {
        $liste = [];
        foreach ($werte as $name => $wert) {
            $liste[] = (str_ends_with($name, 'id') && ctype_digit($wert)) ? (int) $wert : $wert;
        }
        return $liste;
    }
}
