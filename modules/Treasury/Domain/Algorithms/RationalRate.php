<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Algorithms;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Representasi kurs rasional (pembilang / penyebut bulat positif) tanpa float.
 * Mendukung pembulatan setengah menjauhi nol (round half away from zero).
 */
final class RationalRate implements JsonSerializable
{
    public int $numerator;

    public int $denominator;

    public function __construct(int $numerator, int $denominator = 1)
    {
        if ($numerator <= 0 || $denominator <= 0) {
            throw new InvalidArgumentException('INVALID_RATE: Kurs wajib memiliki pembilang dan penyebut bilangan bulat positif.');
        }

        $gcd = self::gcd($numerator, $denominator);
        $this->numerator = intdiv($numerator, $gcd);
        $this->denominator = intdiv($denominator, $gcd);
    }

    public static function fromScaled(int $scaledValue, int $scale = 1_000_000): self
    {
        return new self($scaledValue, $scale);
    }

    public function toScaled(int $scale = 1_000_000): int
    {
        // scaled = (numerator * scale) / denominator with round half away from zero
        $scaledExactNumerator = $this->numerator * $scale;

        return self::roundHalfAwayFromZero($scaledExactNumerator, $this->denominator);
    }

    public function invert(): self
    {
        return new self($this->denominator, $this->numerator);
    }

    public function multiply(self $other): self
    {
        return new self(
            $this->numerator * $other->numerator,
            $this->denominator * $other->denominator
        );
    }

    public function multiplyAndRound(int $amount, int $scale = 1): int
    {
        return self::roundHalfAwayFromZero($amount * $this->numerator, $this->denominator * $scale);
    }

    public function isGreaterThan(self $other): bool
    {
        return ($this->numerator * $other->denominator) > ($other->numerator * $this->denominator);
    }

    public function isLessThan(self $other): bool
    {
        return ($this->numerator * $other->denominator) < ($other->numerator * $this->denominator);
    }

    public function isEqualTo(self $other): bool
    {
        return ($this->numerator * $other->denominator) === ($other->numerator * $this->denominator);
    }

    public function jsonSerialize(): array
    {
        return [
            'numerator' => $this->numerator,
            'denominator' => $this->denominator,
            'display' => $this->toString(),
        ];
    }

    public function toString(): string
    {
        if ($this->denominator === 1) {
            return (string) $this->numerator;
        }

        return "{$this->numerator}/{$this->denominator}";
    }

    public static function gcd(int $a, int $b): int
    {
        $a = abs($a);
        $b = abs($b);
        while ($b !== 0) {
            $t = $b;
            $b = $a % $b;
            $a = $t;
        }

        return $a === 0 ? 1 : $a;
    }

    /**
     * Pembulatan setengah menjauhi nol untuk pembagian integer:
     * round_half_away_from_zero(num, den) = sign(num/den) * floor(|num/den| + 0.5)
     */
    public static function roundHalfAwayFromZero(int $numerator, int $denominator): int
    {
        if ($denominator === 0) {
            throw new InvalidArgumentException('Division by zero');
        }

        $sign = (($numerator > 0) === ($denominator > 0)) ? 1 : -1;
        $num = abs($numerator);
        $den = abs($denominator);

        // floor((2 * num + den) / (2 * den))
        $rounded = intdiv((2 * $num) + $den, 2 * $den);

        return $sign * $rounded;
    }
}
