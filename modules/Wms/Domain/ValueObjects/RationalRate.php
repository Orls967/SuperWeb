<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object bilangan rasional eksak num/den memakai bcmath (tanpa float, IZIN-2).
 */
final readonly class RationalRate
{
    public function __construct(
        public int $numerator,
        public int $denominator,
    ) {
        if ($this->denominator <= 0) {
            throw new InvalidArgumentException('Penyebut RationalRate harus > 0.');
        }
    }

    public function isPositive(): bool
    {
        return $this->numerator > 0;
    }

    public function toBasisPoints(): int
    {
        return (int) bcdiv(bcmul((string) $this->numerator, '10000', 0), (string) $this->denominator, 0);
    }

    public function shiftByBasisPoints(int $deltaBps, int $minNumerator = 1): self
    {
        $newBps = max($minNumerator * 100, $this->toBasisPoints() + $deltaBps);

        return new self($newBps, 10000);
    }

    public function add(self $other): self
    {
        $num = ($this->numerator * $other->denominator) + ($other->numerator * $this->denominator);
        $den = $this->denominator * $other->denominator;

        return new self($num, $den);
    }
}
