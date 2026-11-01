<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Exceptions;

use DomainException;

class PackagingAlreadyReturnedException extends DomainException
{
    public function __construct(string $packageCode)
    {
        parent::__construct(
            "Packaging container [{$packageCode}] has already been returned and refunded."
        );
    }
}
