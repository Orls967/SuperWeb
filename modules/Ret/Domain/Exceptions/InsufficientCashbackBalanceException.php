<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Exceptions;

use DomainException;

class InsufficientCashbackBalanceException extends DomainException
{
    public function __construct(string $customerId, int $requested, int $available)
    {
        parent::__construct(
            "Insufficient cashback balance for customer [{$customerId}]. Requested: {$requested}, Available: {$available}."
        );
    }
}
