<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

final class SecondaryEscrowAccount
{
    public function __construct(
        public readonly string $accountNumber,
        public readonly string $bankCode,
        public readonly string $accountHolderName,
        public readonly int $heldBalanceIdr
    ) {
        if (trim($this->accountNumber) === '') {
            throw new \InvalidArgumentException('Nomor rekening escrow tidak boleh kosong.');
        }
        if ($this->heldBalanceIdr < 0) {
            throw new \InvalidArgumentException('Saldo escrow held tidak boleh negatif.');
        }
    }

    public function canDisburse(int $amountIdr): bool
    {
        return $amountIdr > 0 && $this->heldBalanceIdr >= $amountIdr;
    }
}
