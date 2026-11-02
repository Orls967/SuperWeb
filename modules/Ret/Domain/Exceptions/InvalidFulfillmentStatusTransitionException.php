<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Exceptions;

use DomainException;

class InvalidFulfillmentStatusTransitionException extends DomainException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(
            "Invalid fulfillment split status transition from [{$from}] to [{$to}]."
        );
    }
}
