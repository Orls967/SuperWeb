<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final readonly class GoodwillImpairmentResultDTO
{
    public function __construct(
        public string $cguCode,
        public int $carryingAmountTotalMinor,
        public int $carryingGoodwillMinor,
        public int $valueInUseMinor,
        public int $impairmentLossMinor,
        public int $adjustedGoodwillMinor,
        public bool $isImpaired,
        public string $accountingTreatment
    ) {}

    public function toArray(): array
    {
        return [
            'cgu_code' => $this->cguCode,
            'carrying_amount_total_minor' => $this->carryingAmountTotalMinor,
            'carrying_goodwill_minor' => $this->carryingGoodwillMinor,
            'value_in_use_minor' => $this->valueInUseMinor,
            'impairment_loss_minor' => $this->impairmentLossMinor,
            'adjusted_goodwill_minor' => $this->adjustedGoodwillMinor,
            'is_impaired' => $this->isImpaired,
            'accounting_treatment' => $this->accountingTreatment,
        ];
    }
}
