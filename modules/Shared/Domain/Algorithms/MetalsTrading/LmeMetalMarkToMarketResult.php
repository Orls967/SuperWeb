<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

final class LmeMetalMarkToMarketResult
{
    /**
     * @param  array<string, mixed>  $valuationBreakdown
     */
    public function __construct(
        public readonly string $positionType, // LONG or SHORT
        public readonly float $metricTons,
        public readonly int $entryPriceUsdCentsPerTon,
        public readonly int $currentMarketPriceUsdCentsPerTon,
        public readonly int $priceDeltaUsdCentsPerTon,
        public readonly int $unrealizedPnlUsdCents,
        public readonly int $unrealizedPnlIdr,
        public readonly int $currentNotionalValueIdr,
        public readonly int $initialMarginRequirementIdr,
        public readonly int $maintenanceMarginThresholdIdr,
        public readonly int $variationMarginRequiredIdr,
        public readonly bool $isMarginCallTriggered,
        public readonly float $pnlPercentage,
        public readonly array $valuationBreakdown,
        public readonly string $verificationHash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'position_type' => $this->positionType,
            'metric_tons' => $this->metricTons,
            'entry_price_usd_cents' => $this->entryPriceUsdCentsPerTon,
            'current_price_usd_cents' => $this->currentMarketPriceUsdCentsPerTon,
            'price_delta_usd_cents' => $this->priceDeltaUsdCentsPerTon,
            'unrealized_pnl_usd_cents' => $this->unrealizedPnlUsdCents,
            'unrealized_pnl_idr' => $this->unrealizedPnlIdr,
            'current_notional_value_idr' => $this->currentNotionalValueIdr,
            'initial_margin_requirement_idr' => $this->initialMarginRequirementIdr,
            'maintenance_margin_threshold_idr' => $this->maintenanceMarginThresholdIdr,
            'variation_margin_required_idr' => $this->variationMarginRequiredIdr,
            'is_margin_call_triggered' => $this->isMarginCallTriggered,
            'pnl_percentage' => $this->pnlPercentage,
            'valuation_breakdown' => $this->valuationBreakdown,
            'verification_hash' => $this->verificationHash,
        ];
    }
}
