<?php

declare(strict_types=1);

namespace Modules\Vending\Application\Services;

use Modules\Vending\Domain\Models\VendingItem;

class VendingCatalogService
{
    /**
     * Register a new product into smart vending catalog.
     */
    public function registerItem(array $data): VendingItem
    {
        return VendingItem::create([
            'item_code' => $data['item_code'],
            'name' => $data['name'],
            'category' => $data['category'] ?? 'beverage',
            'base_price_idr' => $data['base_price_idr'],
            'cost_price_idr' => $data['cost_price_idr'],
            'shelf_life_hours' => $data['shelf_life_hours'] ?? 72,
        ]);
    }

    /**
     * Compute dynamic near-expiry markdown price based on remaining shelf life hours.
     *
     * @return array{discount_pct: int, final_price_idr: int, tier: string}
     */
    public function calculateDynamicPrice(string $itemCode, int $hoursUntilExpiry): array
    {
        $item = VendingItem::where('item_code', $itemCode)->firstOrFail();
        $basePrice = (int) $item->base_price_idr;

        [$discountPct, $tier] = match (true) {
            $hoursUntilExpiry <= 6 => [50, 'FLASH_CLEARANCE_50'],
            $hoursUntilExpiry <= 24 => [30, 'NEAR_EXPIRY_30'],
            $hoursUntilExpiry <= 48 => [15, 'EARLY_MARKDOWN_15'],
            default => [0, 'STANDARD_PRICE'],
        };

        $discountAmount = (int) round(($basePrice * $discountPct) / 100);
        $finalPrice = max(1000, $basePrice - $discountAmount);

        return [
            'discount_pct' => $discountPct,
            'final_price_idr' => $finalPrice,
            'tier' => $tier,
        ];
    }
}
