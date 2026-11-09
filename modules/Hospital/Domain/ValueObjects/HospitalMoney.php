<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * HospitalMoney Value Object
 *
 * Merepresentasikan nilai moneter dalam satuan sen / minor unit (misal IDR sen).
 */
final readonly class HospitalMoney
{
    public function __construct(
        public int $amountInMinor,
        public string $currency = 'IDR'
    ) {
        if (empty($currency)) {
            throw new InvalidArgumentException('Currency code tidak boleh kosong');
        }
    }

    public static function ofIdr(int $amountInMinor): self
    {
        return new self($amountInMinor, 'IDR');
    }

    public static function zero(string $currency = 'IDR'): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountInMinor + $other->amountInMinor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountInMinor - $other->amountInMinor, $this->currency);
    }

    public function multiply(float $multiplier): self
    {
        return new self((int) round($this->amountInMinor * $multiplier), $this->currency);
    }

    public function toIdrStandard(): float
    {
        return round($this->amountInMinor / 100, 2);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Currency mismatch: {$this->currency} vs {$other->currency}");
        }
    }
}
