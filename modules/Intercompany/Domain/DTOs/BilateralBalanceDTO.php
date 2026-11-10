<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\DTOs;

use InvalidArgumentException;

final class BilateralBalanceDTO
{
    public function __construct(
        public readonly string $sellerEntity,
        public readonly string $buyerEntity,
        public readonly int $receivableAmountIdr,
        public readonly int $payableAmountIdr,
        public readonly int $salesRevenueIdr = 0,
        public readonly int $cogsExpenseIdr = 0,
        public readonly int $inTransitAmountIdr = 0,
        public readonly string $currency = 'IDR'
    ) {
        if ($this->sellerEntity === '' || $this->buyerEntity === '') {
            throw new InvalidArgumentException('Nama entitas penjual dan pembeli tidak boleh kosong.');
        }

        if ($this->sellerEntity === $this->buyerEntity) {
            throw new InvalidArgumentException('IDENTICAL_ENTITIES_NOT_ALLOWED: Transaksi intercompany tidak boleh dengan entitas yang sama.');
        }
    }

    public function discrepancyIdr(): int
    {
        return abs($this->receivableAmountIdr - $this->payableAmountIdr);
    }

    public function isExactMatch(): bool
    {
        return $this->discrepancyIdr() === 0;
    }
}
