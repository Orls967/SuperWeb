<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use DomainException;

class CreditLimitExceededException extends DomainException
{
    public static function forShipper(int $shipperId, int $outstanding, int $newAmount, int $creditLimit): self
    {
        $total = $outstanding + $newAmount;

        return new self("Batas kredit B2B shipper #{$shipperId} terlampaui. Total pemakaian: Rp ".number_format($total, 0, ',', '.').' melebihi limit: Rp '.number_format($creditLimit, 0, ',', '.').'.');
    }
}
