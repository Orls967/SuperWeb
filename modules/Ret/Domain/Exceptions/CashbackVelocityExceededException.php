<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Exceptions;

use DomainException;

class CashbackVelocityExceededException extends DomainException
{
    public function __construct(string $customerId, int $attempted, int $limit)
    {
        parent::__construct(
            "Cashback daily velocity limit exceeded for customer [{$customerId}]. Attempted total: {$attempted}, Daily limit: {$limit}."
        );
    }
}
