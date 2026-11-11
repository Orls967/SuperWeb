<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Contracts;

use Modules\EnterpriseFinance\Domain\DTOs\BudgetCommitmentRequest;
use Modules\EnterpriseFinance\Domain\DTOs\BudgetCommitmentResult;

interface BudgetCommitmentPort
{
    /**
     * Mencadangkan pagu anggaran (encumbrance) untuk Purchase Order / Kontrak baru.
     */
    public function reserve(BudgetCommitmentRequest $request): BudgetCommitmentResult;

    /**
     * Melikuidasi komitmen yang telah terealisasi menjadi beban aktual / faktur vendor.
     */
    public function liquidate(string $commitmentCode, int $actualAmountMinor): BudgetCommitmentResult;

    /**
     * Melepaskan komitmen terbuka yang dibatalkan atau ditutup sebelum realisasi penuh.
     */
    public function release(string $commitmentCode, string $reason): BudgetCommitmentResult;
}
