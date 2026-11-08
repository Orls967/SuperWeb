<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Algorithms\AwardNightPriceCertificate;
use Modules\Hotel\Domain\Algorithms\AwardNightPricer;
use Modules\Hotel\Domain\Models\HtlAwardNightPricingRule;

final class HotelLoyaltyDynamicAwardPricingService
{
    public function __construct(
        protected ?AwardNightPricer $pricer = null
    ) {
        $this->pricer = $pricer ?? new AwardNightPricer;
    }

    public function quoteDynamicAwardPoints(
        string $propertyReference,
        string $roomTypeReference,
        float $occupancyRate = 0.70,
        string $tier = 'SILVER'
    ): AwardNightPriceCertificate {
        $rule = HtlAwardNightPricingRule::where('property_reference', $propertyReference)
            ->where('room_type_reference', $roomTypeReference)
            ->where('is_active', true)
            ->first();

        $basePoints = $rule ? $rule->base_points_required : 15000;
        $marginalCost = $rule ? $rule->marginal_cost_idr : 300000;
        $seasonality = $rule ? (float) $rule->seasonality_multiplier : 1.0;

        return $this->pricer->calculate(
            basePointsRequired: $basePoints,
            occupancyRate: $occupancyRate,
            seasonalityMultiplier: $seasonality,
            marginalCostIdr: $marginalCost,
            tier: $tier,
            pointValuationIdr: 100
        );
    }
}
