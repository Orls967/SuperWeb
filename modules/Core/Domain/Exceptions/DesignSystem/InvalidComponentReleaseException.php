<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions\DesignSystem;

use DomainException;

class InvalidComponentReleaseException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}

