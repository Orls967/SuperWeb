<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation;

/**
 * Aliran acak deterministik Lehmer LCG (Park-Miller MINSTD).
 * Modulus m = 2^31 - 1 (2147483647), Multiplier a = 48271.
 */
class SimulationStream
{
    public const MODULUS = 2147483647;

    public const MULTIPLIER = 48271;

    protected int $state;

    public function __construct(int $seed)
    {
        $modSeed = $seed % self::MODULUS;
        $this->state = ($modSeed <= 0) ? 1 : $modSeed;
    }

    /**
     * Menghasilkan bilangan bulat acak deterministik berikutnya dalam rentang [1, 2147483646].
     */
    public function next(): int
    {
        $this->state = ($this->state * self::MULTIPLIER) % self::MODULUS;

        return $this->state;
    }

    /**
     * Menghasilkan bilangan bulat acak dalam rentang tertutup [min, max].
     */
    public function nextInt(int $min, int $max): int
    {
        if ($min >= $max) {
            return $min;
        }

        $range = ($max - $min) + 1;
        $raw = $this->next();

        return $min + ($raw % $range);
    }

    public function currentState(): int
    {
        return $this->state;
    }
}
