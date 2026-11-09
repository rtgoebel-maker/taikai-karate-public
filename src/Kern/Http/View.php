<?php

declare(strict_types=1);

namespace Wettkampf\Kern\Http;

/**
 * Rendert PHP-Templates aus templates/. Ein Template kann über
 * $this->layout('name', [...]) ein umgebendes Layout wählen.
 */
final class View
{
    private ?string $layout = null;
    /** @var array<string, mixed> */
    private array $layoutDaten = [];
    /** @var array<string, mixed> in allen Templates verfügbar (z. B. Feedback-Knopf an/aus) */
    private array $geteilt = [];

    public function __construct(private readonly string $verzeichnis) {}

    /** @param array<string, mixed> $daten */
    public function render(string $template, array $daten = []): string
    {
        $this->layout = null;
        $inhalt = $this->datei($template, $daten);
        while ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            $inhalt = $this->datei($layout, $this->layoutDaten + $daten + ['inhalt' => $inhalt]);
        }
        return $inhalt;
    }

    /** Teil-Template einbinden. @param array<string, mixed> $daten */
    public function teil(string $template, array $daten = []): string
    {
        $layout = $this->layout;
        $layoutDaten = $this->layoutDaten;
        $html = $this->datei($template, $daten);
        $this->layout = $layout;
        $this->layoutDaten = $layoutDaten;
        return $html;
    }

    public function teilen(string $name, mixed $wert): void
    {
        $this->geteilt[$name] = $wert;
    }

    /** @param array<string, mixed> $daten */
    public function layout(string $name, array $daten = []): void
    {
        $this->layout = $name;
        $this->layoutDaten = $daten;
    }

    /** @param array<string, mixed> $daten */
    private function datei(string $template, array $daten): string
    {
        $pfad = $this->verzeichnis . '/' . $template . '.php';
        if (!is_file($pfad)) {
            throw new \RuntimeException("Template nicht gefunden: $template");
        }
        extract($daten + $this->geteilt, EXTR_SKIP);
        ob_start();
        try {
            include $pfad;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
