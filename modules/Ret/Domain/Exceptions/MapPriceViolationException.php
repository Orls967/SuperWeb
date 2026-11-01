<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Exceptions;

use DomainException;

class MapPriceViolationException extends DomainException
{
    public function __construct(string $sku, int $offeredPriceMinor, int $mapPriceMinor)
    {
        parent::__construct(
            "Listing price [{$offeredPriceMinor}] for SKU [{$sku}] violates Minimum Advertised Price (MAP) policy floor of [{$mapPriceMinor}]."
        );
    }
}
