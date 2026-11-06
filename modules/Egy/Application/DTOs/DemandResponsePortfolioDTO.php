<?php

declare(strict_types=1);

namespace Modules\Egy\Application\DTOs;

/**
 * Portofolio Agregasi Kapasitas Demand-Response Terdistribusi Kawasan.
 */
final class DemandResponsePortfolioDTO
{
    /**
     * @param  array<string, int>  $flexibleLoadCategoriesKw
     * @param  list<array<string, mixed>>  $participatingSites
     */
    public function __construct(
        public readonly string $portfolioCode,
        public readonly int $totalAggregatedFlexibleCapacityKw,
        public readonly int $totalCustomerBaselineLoadKw,
        public readonly int $targetFastCurtailmentKw,
        public readonly int $potentialHourlyIncentiveRevenueIdr,
        public readonly array $flexibleLoadCategoriesKw,
        public readonly array $participatingSites,
        public readonly string $portfolioHash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'portfolio_code' => $this->portfolioCode,
            'total_aggregated_flexible_kw' => $this->totalAggregatedFlexibleCapacityKw,
            'customer_baseline_load_kw' => $this->totalCustomerBaselineLoadKw,
            'target_fast_curtailment_kw' => $this->targetFastCurtailmentKw,
            'hourly_incentive_revenue_idr' => $this->potentialHourlyIncentiveRevenueIdr,
            'category_breakdown' => $this->flexibleLoadCategoriesKw,
            'participating_sites' => $this->participatingSites,
            'portfolio_hash' => $this->portfolioHash,
        ];
    }
}
