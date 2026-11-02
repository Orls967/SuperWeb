<?php

declare(strict_types=1);

namespace Modules\Ret\Infrastructure\Adapters;

use Modules\Ret\Contracts\MapPriceLookupInterface;
use Modules\Ret\Domain\Exceptions\MapPriceViolationException;
use Modules\Ret\Domain\Models\RetListing;

class DefaultMapPriceLookupAdapter implements MapPriceLookupInterface
{
    /**
     * @var array<string, int>
     */
    protected array $catalogMap = [
        'SKU-PHONE-01' => 500_000,
        'SKU-LAPTOP-01' => 15_000_000,
        'SKU-ELEC-01' => 1_000_000,
        'SKU-FRESH-MILK' => 25_000,
        'SKU-DAIRY-YOGURT' => 35_000,
    ];

    public function getMapPrice(string $sku): int
    {
        if (isset($this->catalogMap[$sku])) {
            return $this->catalogMap[$sku];
        }

        $existingListing = RetListing::where('sku', $sku)->first();
        if ($existingListing && $existingListing->map_price_minor > 0) {
            return (int) $existingListing->map_price_minor;
        }

        return 10_000; // Default floor
    }

    public function assertPriceMeetsMap(string $sku, int $priceMinor): void
    {
        $map = $this->getMapPrice($sku);
        if ($priceMinor < $map) {
            throw new MapPriceViolationException($sku, $priceMinor, $map);
        }
    }
}
