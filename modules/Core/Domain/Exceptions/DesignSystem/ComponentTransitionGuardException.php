<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions\DesignSystem;

use DomainException;

class ComponentTransitionGuardException extends DomainException
{
    public function __construct(string $componentName, string $fromStatus, string $toStatus, string $reason)
    {
        parent::__construct(
            "Transisi status komponen '{$componentName}' dari '{$fromStatus}' ke '{$toStatus}' ditolak oleh guard: {$reason}"
        );
    }
}
