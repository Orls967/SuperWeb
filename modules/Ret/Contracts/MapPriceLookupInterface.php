<?php

declare(strict_types=1);

namespace Modules\Ret\Contracts;

use Modules\Ret\Domain\Exceptions\MapPriceViolationException;

/**
 * Port to query Minimum Advertised Price (MAP) policy from Pricing / Catalog.
 * Decouples Ret domain from direct Pricing domain dependency (Rule A1 / Decision Z9).
 */
interface MapPriceLookupInterface
{
    /**
     * Get the configured MAP price for a given SKU in minor unit (integer Rupiah).
     */
    public function getMapPrice(string $sku): int;

    /**
     * Assert that the selling or listing price meets or exceeds the MAP floor.
     *
     * @throws MapPriceViolationException
     */
    public function assertPriceMeetsMap(string $sku, int $priceMinor): void;
}
