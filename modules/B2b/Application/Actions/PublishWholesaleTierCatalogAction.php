<?php

declare(strict_types=1);

namespace Modules\B2b\Application\Actions;

use InvalidArgumentException;
use Modules\B2b\Domain\Models\WholesaleCatalog;

class PublishWholesaleTierCatalogAction
{
    /**
     * @param array{
     *     vendor_id: string,
     *     sku: string,
     *     product_name: string,
     *     category: string,
     *     base_price_idr: int,
     *     min_order_qty?: int,
     *     tiered_pricing_matrix?: list<array{min_qty: int, price_idr: int}>,
     *     available_stock?: int,
     *     status?: string
     * } $data
     */
    public function execute(array $data): WholesaleCatalog
    {
        if (empty($data['vendor_id']) || empty($data['sku']) || empty($data['product_name'])) {
            throw new InvalidArgumentException('Vendor ID, SKU, and Product Name are required.');
        }

        $basePrice = (int) ($data['base_price_idr'] ?? 0);
        if ($basePrice <= 0) {
            throw new InvalidArgumentException('Base price must be a positive integer in IDR minor units.');
        }

        $minOrderQty = (int) ($data['min_order_qty'] ?? 1);
        if ($minOrderQty < 1) {
            throw new InvalidArgumentException('Minimum order quantity must be at least 1.');
        }

        $tiers = $data['tiered_pricing_matrix'] ?? [];
        foreach ($tiers as $tier) {
            if (! isset($tier['min_qty'], $tier['price_idr']) || $tier['min_qty'] < 1 || $tier['price_idr'] <= 0) {
                throw new InvalidArgumentException('Invalid tier specification: min_qty and price_idr must be positive integers.');
            }
            if ($tier['price_idr'] > $basePrice) {
                throw new InvalidArgumentException("Tier price {$tier['price_idr']} cannot exceed base price {$basePrice}.");
            }
        }

        // Sort tiers ascending by min_qty
        usort($tiers, fn ($a, $b) => $a['min_qty'] <=> $b['min_qty']);

        return WholesaleCatalog::updateOrCreate(
            ['sku' => $data['sku']],
            [
                'vendor_id' => $data['vendor_id'],
                'product_name' => $data['product_name'],
                'category' => $data['category'] ?? 'General',
                'base_price_idr' => $basePrice,
                'min_order_qty' => $minOrderQty,
                'tiered_pricing_matrix' => $tiers,
                'available_stock' => (int) ($data['available_stock'] ?? 0),
                'status' => $data['status'] ?? 'active',
            ]
        );
    }

    /**
     * Calculate effective unit price for given quantity based on tiered matrix.
     */
    public function calculateUnitPrice(WholesaleCatalog $catalog, int $quantity): int
    {
        if ($quantity < $catalog->min_order_qty) {
            throw new InvalidArgumentException("Order quantity {$quantity} is below minimum requirement of {$catalog->min_order_qty}.");
        }

        $effectivePrice = $catalog->base_price_idr;
        $tiers = $catalog->tiered_pricing_matrix ?? [];

        foreach ($tiers as $tier) {
            if ($quantity >= $tier['min_qty']) {
                $effectivePrice = (int) $tier['price_idr'];
            }
        }

        return $effectivePrice;
    }
}
