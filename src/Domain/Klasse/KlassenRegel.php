<?php

declare(strict_types=1);

namespace Wettkampf\Domain\Klasse;

/**
 * Zulassungsregel einer Starterklasse (Disziplin, Geschlecht, Alter, Grad).
 */
final class KlassenRegel
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $disziplin,
        public readonly string $geschlecht,
        public readonly ?int $alterMin,
        public readonly ?int $alterMax,
        public readonly ?int $gradMin,
        public readonly ?int $gradMax,
        public readonly ?string $kumiteForm = null,
    ) {}

    /** @param array<string, mixed> $zeile Datensatz aus Tabelle klasse */
    public static function ausZeile(array $zeile): self
    {
        $int = static fn($w) => $w === null || $w === '' ? null : (int) $w;
        return new self(
            (int) $zeile['id'],
            (string) $zeile['code'],
            (string) $zeile['disziplin'],
            (string) $zeile['geschlecht'],
            $int($zeile['alter_min']),
            $int($zeile['alter_max']),
            $int($zeile['grad_min']),
            $int($zeile['grad_max']),
            $zeile['kumite_form'] ?: null,
        );
    }

    public function passt(Kandidat $k): bool
    {
        return $this->gruende($k) === [];
    }

    /**
     * Gründe, warum der Kandidat nicht passt (leer = passt).
     *
     * @return list<string>
     */
    public function gruende(Kandidat $k): array
    {
        $gruende = [];
        if ($k->disziplin !== $this->disziplin) {
            $gruende[] = 'Disziplin';
        }
        if ($this->geschlecht !== 'mixed' && $k->geschlecht !== $this->geschlecht) {
            $gruende[] = 'Geschlecht';
        }
        if ($k->alter === null) {
            $gruende[] = 'Alter unbekannt';
        } elseif (($this->alterMin !== null && $k->alter < $this->alterMin) || ($this->alterMax !== null && $k->alter > $this->alterMax)) {
            $gruende[] = 'Alter';
        }
        if (($this->gradMin !== null && $k->graduierung < $this->gradMin) || ($this->gradMax !== null && $k->graduierung > $this->gradMax)) {
            $gruende[] = 'Graduierung';
        }
        if ($k->kumiteForm !== null && $this->kumiteForm !== null && $k->kumiteForm !== $this->kumiteForm) {
            $gruende[] = 'Kumite-Form';
        }
        return $gruende;
    }
}
