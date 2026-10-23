<?php

declare(strict_types=1);

namespace Modules\Vending\Application\Actions;

use Modules\Shared\Application\BaseAction;

class ApplyDynamicNearExpiryMarkdownAction extends BaseAction
{
    /**
     * Compute dynamic markdown price for perishable vending items based on hours until expiry.
     *
     * @return array{item_code: string, original_price_idr: int, discount_pct: int, marked_down_price_idr: int, tier: string}
     */
    public function handle(
        string $itemCode,
        int $originalPriceIdr,
        int $hoursUntilExpiry
    ): array {
        [$discountPct, $tier] = match (true) {
            $hoursUntilExpiry <= 6 => [50, 'FLASH_CLEARANCE_50'],
            $hoursUntilExpiry <= 24 => [30, 'NEAR_EXPIRY_30'],
            $hoursUntilExpiry <= 48 => [15, 'EARLY_MARKDOWN_15'],
            default => [0, 'STANDARD_PRICE'],
        };

        $discountAmountIdr = (int) round(($originalPriceIdr * $discountPct) / 100);
        $markedDownPriceIdr = max(1000, $originalPriceIdr - $discountAmountIdr);

        $this->audit(
            action: 'vending.price.marked_down',
            auditable: null,
            oldValues: ['price_idr' => $originalPriceIdr],
            newValues: [
                'item_code' => $itemCode,
                'discount_pct' => $discountPct,
                'marked_down_price_idr' => $markedDownPriceIdr,
                'tier' => $tier,
            ],
            correlationId: "ven_md_{$itemCode}_".date('YmdH'),
            impactType: 'dynamic_pricing'
        );

        return [
            'item_code' => $itemCode,
            'original_price_idr' => $originalPriceIdr,
            'discount_pct' => $discountPct,
            'marked_down_price_idr' => $markedDownPriceIdr,
            'tier' => $tier,
        ];
    }
}
