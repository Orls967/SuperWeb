<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Ret\Domain\Models\RetInventoryItem;
use Modules\Shared\Application\BaseAction;

class ExecuteDynamicExpiryMarkdownPricingAction extends BaseAction
{
    /**
     * Compute dynamic markdown based on days remaining until expiry.
     *
     * @return array{sku: string, original_price: int, marked_down_price: int, discount_pct: int}
     */
    public function execute(string $sku, int $daysRemaining): array
    {
        $item = RetInventoryItem::where('sku', $sku)->firstOrFail();

        return DB::transaction(function () use ($item, $daysRemaining) {
            $originalPrice = (int) $item->map_price_minor;

            $discountPct = 0;
            if ($daysRemaining <= 1) {
                $discountPct = 70; // 70% off for 1 day left
            } elseif ($daysRemaining <= 3) {
                $discountPct = 50; // 50% off
            } elseif ($daysRemaining <= 7) {
                $discountPct = 25; // 25% off
            }

            $discountMinor = (int) round(($originalPrice * $discountPct) / 100);
            $newPrice = max(1000, $originalPrice - $discountMinor);

            $item->update(['map_price_minor' => $newPrice]);

            $this->audit(
                action: 'ret_dynamic_markdown_applied',
                auditable: $item,
                newValues: ['original_price' => $originalPrice, 'new_price' => $newPrice, 'discount_pct' => $discountPct],
                impactType: 'pricing_optimization',
            );

            return [
                'sku' => $item->sku,
                'original_price' => $originalPrice,
                'marked_down_price' => $newPrice,
                'discount_pct' => $discountPct,
            ];
        });
    }
}
