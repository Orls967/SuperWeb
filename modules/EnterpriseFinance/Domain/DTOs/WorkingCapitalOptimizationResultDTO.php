<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final readonly class WorkingCapitalOptimizationResultDTO
{
    public function __construct(
        public string $entityCode,
        public string $period,
        public int $revenueMinor,
        public int $cogsMinor,
        public int $netWorkingCapitalMinor,
        public int $dsoDays,
        public int $dioDays,
        public int $dpoDays,
        public int $cccDays,
        public string $efficiencyTier,
        public int $potentialCashReleaseMinor
    ) {}

    public function toArray(): array
    {
        return [
            'entity_code' => $this->entityCode,
            'period' => $this->period,
            'revenue_minor' => $this->revenueMinor,
            'cogs_minor' => $this->cogsMinor,
            'net_working_capital_minor' => $this->netWorkingCapitalMinor,
            'dso_days' => $this->dsoDays,
            'dio_days' => $this->dioDays,
            'dpo_days' => $this->dpoDays,
            'ccc_days' => $this->cccDays,
            'efficiency_tier' => $this->efficiencyTier,
            'potential_cash_release_minor' => $this->potentialCashReleaseMinor,
        ];
    }
}
