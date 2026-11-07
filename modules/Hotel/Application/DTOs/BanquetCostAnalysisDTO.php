<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final class BanquetCostAnalysisDTO
{
    /**
     * @param  list<array<string, mixed>>  $bomBreakdown
     */
    public function __construct(
        public readonly string $contractId,
        public readonly int $paxCount,
        public readonly int $contractRevenueIdr,
        public readonly int $totalFoodCostIdr,
        public readonly int $totalLaborCostIdr,
        public readonly float $grossMarginPct,
        public readonly bool $isMarginGuardSatisfied,
        public readonly array $bomBreakdown
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'contract_id' => $this->contractId,
            'pax_count' => $this->paxCount,
            'contract_revenue_idr' => $this->contractRevenueIdr,
            'total_food_cost_idr' => $this->totalFoodCostIdr,
            'total_labor_cost_idr' => $this->totalLaborCostIdr,
            'gross_margin_pct' => $this->grossMarginPct,
            'is_margin_guard_satisfied' => $this->isMarginGuardSatisfied,
            'bom_breakdown' => $this->bomBreakdown,
        ];
    }
}
