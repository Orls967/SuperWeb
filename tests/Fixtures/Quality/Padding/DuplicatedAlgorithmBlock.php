<?php

declare(strict_types=1);

namespace Tests\Fixtures\Quality\Padding;

/**
 * Fixture duplikasi token sengaja untuk pengujian aturan M-4 (T-10).
 */
class DuplicatedAlgorithmBlock
{
    public function calculateFirst(int $base, int $multiplier): int
    {
        $sum = 0;
        for ($i = 0; $i < $multiplier; $i++) {
            $intermediate = ($base * $i) + 42;
            if ($intermediate % 2 === 0) {
                $sum += ($intermediate * 3) - 7;
            } else {
                $sum += ($intermediate * 5) + 11;
            }
        }

        return $sum;
    }

    public function calculateSecond(int $alpha, int $beta): int
    {
        $acc = 0;
        for ($j = 0; $j < $beta; $j++) {
            $temp = ($alpha * $j) + 42;
            if ($temp % 2 === 0) {
                $acc += ($temp * 3) - 7;
            } else {
                $acc += ($temp * 5) + 11;
            }
        }

        return $acc;
    }
}

