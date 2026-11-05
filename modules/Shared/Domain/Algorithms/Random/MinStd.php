<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Random;

use InvalidArgumentException;

/**
 * PRNG deterministik Park–Miller "MINSTD" (keputusan G7-D2):
 * x_{n+1} = 48271 · x_n mod (2^31 − 1). Seed 0 dilarang (titik tetap).
 *
 * Kompleksitas: O(1) per keluaran, O(1) memori. Bukan generator kriptografis.
 */
final class MinStd
{
    public const int MODULUS = 2147483647;

    public const int MULTIPLIER = 48271;

    private int $state;

    public function __construct(int $seed)
    {
        $reduced = $seed % self::MODULUS;
        if ($reduced <= 0) {
            throw new InvalidArgumentException('Seed MinStd harus positif dan bukan kelipatan 2^31−1.');
        }

        $this->state = $reduced;
    }

    public function next(): int
    {
        $this->state = ($this->state * self::MULTIPLIER) % self::MODULUS;

        return $this->state;
    }

    /**
     * Bilangan bulat dalam [0, $bound). Bias modulo < bound/2^31, dapat diabaikan
     * untuk bound simulasi (≤ 10^6) dan diterima agar urutan tetap identik lintas platform.
     */
    public function nextBelow(int $bound): int
    {
        if ($bound <= 0) {
            throw new InvalidArgumentException('Batas harus positif.');
        }

        return $this->next() % $bound;
    }

    /** Bilangan bulat dalam [$min, $max] inklusif. */
    public function nextBetween(int $min, int $max): int
    {
        if ($max < $min) {
            throw new InvalidArgumentException('Rentang tidak valid.');
        }

        return $min + $this->nextBelow($max - $min + 1);
    }

    public function state(): int
    {
        return $this->state;
    }
}
