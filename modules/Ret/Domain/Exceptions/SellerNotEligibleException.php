<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Exceptions;

use DomainException;

class SellerNotEligibleException extends DomainException
{
    public function __construct(string $sellerName, string $reason)
    {
        parent::__construct(
            "Seller [{$sellerName}] is not eligible for escrow settlement: {$reason}."
        );
    }
}
