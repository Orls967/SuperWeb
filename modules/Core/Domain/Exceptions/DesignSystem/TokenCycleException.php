<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions\DesignSystem;

use DomainException;

class TokenCycleException extends DomainException
{
    public function __construct(string $cycleChain)
    {
        parent::__construct(
            "Terdeteksi siklus dependensi token sirkular pada rantai pewarisan: {$cycleChain}."
        );
    }
}
