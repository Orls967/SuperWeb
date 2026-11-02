<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Exceptions;

use DomainException;

class InsufficientStockException extends DomainException
{
    public function __construct(string $sku, int $requested, int $available)
    {
        parent::__construct(
            "Insufficient inventory stock for SKU [{$sku}]. Requested: {$requested}, Available: {$available}."
        );
    }
}
