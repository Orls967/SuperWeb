<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

final class WarehouseReceiptDigitalCollateralHaircutResult
{
    /**
     * @param  array<string, mixed>  $riskFactorBreakdown
     */
    public function __construct(
        public readonly float $certifiedWeightMetricTons,
        public readonly int $unitMarketPriceIdrPerTon,
        public readonly int $grossCollateralValueIdr,
        public readonly float $baseHaircutPct,
        public readonly float $volatilityHaircutPct,
        public readonly float $storageDurationHaircutPct,
        public readonly float $assayRiskHaircutPct,
        public readonly float $totalHaircutPct,
        public readonly float $effectiveLtvPct,
        public readonly int $haircutDeductionIdr,
        public readonly int $netCollateralValueIdr,
        public readonly int $maxFinancingCreditLimitIdr,
        public readonly int $liquidationThresholdIdr,
        public readonly bool $isCollateralApproved,
        public readonly array $riskFactorBreakdown,
        public readonly string $digitalSealSignature,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'certified_weight_tons' => $this->certifiedWeightMetricTons,
            'unit_market_price_idr_ton' => $this->unitMarketPriceIdrPerTon,
            'gross_collateral_value_idr' => $this->grossCollateralValueIdr,
            'base_haircut_pct' => $this->baseHaircutPct,
            'volatility_haircut_pct' => $this->volatilityHaircutPct,
            'storage_duration_haircut_pct' => $this->storageDurationHaircutPct,
            'assay_risk_haircut_pct' => $this->assayRiskHaircutPct,
            'total_haircut_pct' => $this->totalHaircutPct,
            'effective_ltv_pct' => $this->effectiveLtvPct,
            'haircut_deduction_idr' => $this->haircutDeductionIdr,
            'net_collateral_value_idr' => $this->netCollateralValueIdr,
            'max_financing_credit_limit_idr' => $this->maxFinancingCreditLimitIdr,
            'liquidation_threshold_idr' => $this->liquidationThresholdIdr,
            'is_collateral_approved' => $this->isCollateralApproved,
            'risk_factor_breakdown' => $this->riskFactorBreakdown,
            'digital_seal_signature' => $this->digitalSealSignature,
        ];
    }
}
