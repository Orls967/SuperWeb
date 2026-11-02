<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Exceptions;

use DomainException;

class CouponAlreadyRedeemedException extends DomainException
{
    public function __construct(string $couponCode, string $customerId)
    {
        parent::__construct(
            "Coupon [{$couponCode}] has already been redeemed by customer [{$customerId}]."
        );
    }
}
