<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

final class LayeredProductionHedgeResult
{
    /**
     * @param  array<string, array<string, mixed>>  $tenorRecommendations
     * @param  array<string, int>  $stressTestDownsideLossIdr
     */
    public function __construct(
        public readonly float $aggregatePlannedProductionTons,
        public readonly float $aggregateExistingHedgedTons,
        public readonly float $aggregateNewHedgeRecommendedTons,
        public readonly float $portfolioCoveragePct,
        public readonly int $securedNotionalValueIdr,
        public readonly array $tenorRecommendations,
        public readonly array $stressTestDownsideLossIdr,
        public readonly bool $isHedgePolicyCompliant,
        public readonly string $verificationHash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'aggregate_planned_production_tons' => $this->aggregatePlannedProductionTons,
            'aggregate_existing_hedged_tons' => $this->aggregateExistingHedgedTons,
            'aggregate_new_hedge_recommended_tons' => $this->aggregateNewHedgeRecommendedTons,
            'portfolio_coverage_pct' => $this->portfolioCoveragePct,
            'secured_notional_value_idr' => $this->securedNotionalValueIdr,
            'tenor_recommendations' => $this->tenorRecommendations,
            'stress_test_downside_loss_idr' => $this->stressTestDownsideLossIdr,
            'is_hedge_policy_compliant' => $this->isHedgePolicyCompliant,
            'verification_hash' => $this->verificationHash,
        ];
    }
}
