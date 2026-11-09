<?php

declare(strict_types=1);

namespace Wettkampf\Domain\Auslosung;

/**
 * Deterministischer Zufallsgenerator (xoshiro128**) in reinem PHP.
 * Gleicher Seed ⇒ gleiche Folge – unabhängig von PHP-Version und Server.
 * Damit ist jede Auslosung über ihren gespeicherten Seed reproduzierbar.
 */
final class Zufall
{
    private const MASKE = 0xFFFFFFFF;

    /** @var array{0: int, 1: int, 2: int, 3: int} */
    private array $s;

    public function __construct(public readonly string $seed)
    {
        $hash = hash('sha256', 'wettkampf:' . $seed, true);
        $w = array_values(unpack('N4', substr($hash, 0, 16)));
        if (($w[0] | $w[1] | $w[2] | $w[3]) === 0) {
            $w[0] = 1; // Zustand darf nicht komplett 0 sein
        }
        $this->s = [$w[0], $w[1], $w[2], $w[3]];
    }

    public static function neuerSeed(): string
    {
        return bin2hex(random_bytes(8));
    }

    /** Nächste 32-Bit-Zahl (0 … 2^32-1). */
    public function naechste(): int
    {
        [$s0, $s1, $s2, $s3] = $this->s;
        $ergebnis = (self::rotl(($s1 * 5) & self::MASKE, 7) * 9) & self::MASKE;
        $t = ($s1 << 9) & self::MASKE;

        $s2 ^= $s0;
        $s3 ^= $s1;
        $s1 ^= $s2;
        $s0 ^= $s3;
        $s2 ^= $t;
        $s3 = self::rotl($s3, 11);

        $this->s = [$s0, $s1, $s2, $s3];
        return $ergebnis;
    }

    /** Gleichverteilte Zahl im Bereich 0 … $grenze-1 (ohne Modulo-Verzerrung). */
    public function zahl(int $grenze): int
    {
        if ($grenze < 1) {
            throw new \InvalidArgumentException('Grenze muss mindestens 1 sein.');
        }
        $limit = intdiv(0x100000000, $grenze) * $grenze;
        do {
            $x = $this->naechste();
        } while ($x >= $limit);
        return $x % $grenze;
    }

    /**
     * Fisher-Yates-Mischung.
     *
     * @template T
     * @param list<T> $liste
     * @return list<T>
     */
    public function mischen(array $liste): array
    {
        for ($i = count($liste) - 1; $i > 0; $i--) {
            $j = $this->zahl($i + 1);
            [$liste[$i], $liste[$j]] = [$liste[$j], $liste[$i]];
        }
        return $liste;
    }

    private static function rotl(int $x, int $k): int
    {
        return (($x << $k) | ($x >> (32 - $k))) & self::MASKE;
    }
}
