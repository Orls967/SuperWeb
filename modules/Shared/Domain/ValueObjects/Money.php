<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\ValueObjects;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Money
{
    public function __construct(
        public string $assetCode,
        public BigDecimal $amount,
    ) {}

    public static function of(string $assetCode, string|int|float|BigDecimal $amount): self
    {
        $bd = $amount instanceof BigDecimal
            ? $amount
            : BigDecimal::of((string) $amount);

        return new self($assetCode, $bd);
    }

    public static function IDR(string|int|float|BigDecimal $amount): self
    {
        return self::of('IDR', $amount);
    }

    public static function fromIdr(string|int|float|BigDecimal $amount): self
    {
        return self::IDR($amount);
    }

    public static function zero(string $assetCode = 'IDR'): self
    {
        return new self($assetCode, BigDecimal::zero());
    }

    public function add(self $other): self
    {
        $this->assertSameAsset($other);

        return new self($this->assetCode, $this->amount->plus($other->amount));
    }

    public function sub(self $other): self
    {
        $this->assertSameAsset($other);

        return new self($this->assetCode, $this->amount->minus($other->amount));
    }

    public function multiply(string|int|float|BigDecimal $factor): self
    {
        $f = $factor instanceof BigDecimal ? $factor : BigDecimal::of((string) $factor);

        return new self($this->assetCode, $this->amount->multipliedBy($f));
    }

    public function isNegative(): bool
    {
        return $this->amount->isNegative();
    }

    public function isPositive(): bool
    {
        return $this->amount->isPositive();
    }

    public function isZero(): bool
    {
        return $this->amount->isZero();
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameAsset($other);

        return $this->amount->isGreaterThan($other->amount);
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameAsset($other);

        return $this->amount->isLessThan($other->amount);
    }

    public function negate(): self
    {
        return new self($this->assetCode, $this->amount->negated());
    }

    public function abs(): self
    {
        return new self($this->assetCode, $this->amount->abs());
    }

    /**
     * Format for display.
     * IDR → "Rp 1.250.000"
     * Crypto → "0.00153400 BTC"
     */
    public function format(): string
    {
        if ($this->assetCode === 'IDR') {
            $rounded = $this->amount->toScale(0, RoundingMode::HalfUp);
            $formatted = number_format((float) $rounded->__toString(), 0, ',', '.');

            return 'Rp '.$formatted;
        }

        // Crypto: show up to 8 decimal places, trim trailing zeros
        $scaled = $this->amount->toScale(8, RoundingMode::HalfUp);

        return $scaled->__toString().' '.$this->assetCode;
    }

    public function toDecimalString(int $scale = 18): string
    {
        return $this->amount->toScale($scale, RoundingMode::HalfUp)->__toString();
    }

    public function equals(self $other): bool
    {
        return $this->assetCode === $other->assetCode
            && $this->amount->compareTo($other->amount) === 0;
    }

    private function assertSameAsset(self $other): void
    {
        if ($this->assetCode !== $other->assetCode) {
            throw new \InvalidArgumentException(
                "Cannot operate on different assets: {$this->assetCode} vs {$other->assetCode}"
            );
        }
    }
}
