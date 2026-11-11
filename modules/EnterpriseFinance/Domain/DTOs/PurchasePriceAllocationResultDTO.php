<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final readonly class PurchasePriceAllocationResultDTO
{
    public function __construct(
        public string $acquisitionCode,
        public string $targetEntityName,
        public int $purchasePriceMinor,
        public int $totalTangibleAssetsMinor,
        public int $totalIntangibleAssetsMinor,
        public int $totalAssumedLiabilitiesMinor,
        public int $fairValueNetIdentifiableAssetsMinor,
        public int $goodwillRecognizedMinor,
        public int $bargainPurchaseGainMinor,
        public bool $isGoodwill
    ) {}

    public function toArray(): array
    {
        return [
            'acquisition_code' => $this->acquisitionCode,
            'target_entity_name' => $this->targetEntityName,
            'purchase_price_minor' => $this->purchasePriceMinor,
            'total_tangible_assets_minor' => $this->totalTangibleAssetsMinor,
            'total_intangible_assets_minor' => $this->totalIntangibleAssetsMinor,
            'total_assumed_liabilities_minor' => $this->totalAssumedLiabilitiesMinor,
            'fair_value_net_identifiable_assets_minor' => $this->fairValueNetIdentifiableAssetsMinor,
            'goodwill_recognized_minor' => $this->goodwillRecognizedMinor,
            'bargain_purchase_gain_minor' => $this->bargainPurchaseGainMinor,
            'is_goodwill' => $this->isGoodwill,
        ];
    }
}
