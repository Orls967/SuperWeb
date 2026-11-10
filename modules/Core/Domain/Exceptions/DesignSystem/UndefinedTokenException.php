<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions\DesignSystem;

use DomainException;

class UndefinedTokenException extends DomainException
{
    public function __construct(string $tokenName)
    {
        parent::__construct(
            "Token alias '{$tokenName}' tidak ditemukan pada tema peminta maupun rantai warisannya."
        );
    }
}

