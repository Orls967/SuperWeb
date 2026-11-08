<?php

declare(strict_types=1);

namespace Modules\Marine\Application\DTOs;

use DomainException;
use Modules\Shared\Domain\Attributes\NotAControl;

/**
 * DTO evaluasi syarat pelepasan escrow petani tambak (Fase 171).
 */
class EscrowSettlementConditionDTO
{
    public function __construct(
        public readonly string $settlementCode,
        public readonly int $farmerId,
        public readonly int $grossHarvestValueMinorCents,
        public readonly int $feedDeductionMinorCents,
        public readonly int $hatcherySeedDeductionMinorCents,
        public readonly int $qualityGradePremiumMinorCents,
        #[NotAControl('Parameter status transfer objek kelulusan uji lab SPS')]
        public readonly bool $spsAssayPassed,
        #[NotAControl('Parameter status transfer objek verifikasi neraca massa ASC')]
        public readonly bool $massBalanceVerified,
        public readonly bool $vesselBillOfLadingIssued
    ) {
        $this->validate();
    }

    protected function validate(): void
    {
        if ($this->farmerId <= 0) {
            throw new DomainException('Farmer ID harus valid.');
        }

        if ($this->grossHarvestValueMinorCents <= 0) {
            throw new DomainException('Nilai kotor panen harus positif.');
        }
    }

    public function calculateNetFarmerPayoutMinorCents(): int
    {
        $net = ($this->grossHarvestValueMinorCents + $this->qualityGradePremiumMinorCents)
            - ($this->feedDeductionMinorCents + $this->hatcherySeedDeductionMinorCents);

        return max(0, $net);
    }

    public function isFullyEligibleForRelease(): bool
    {
        return $this->spsAssayPassed && $this->massBalanceVerified;
    }
}
