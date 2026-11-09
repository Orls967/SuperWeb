<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Enums;

enum SandboxTier: string
{
    case STANDARD = 'standard';
    case EPHEMERAL = 'ephemeral';
    case ISOLATED = 'isolated';

    public function defaultTtlHours(): int
    {
        return match ($this) {
            self::EPHEMERAL => 4,
            self::STANDARD => 24,
            self::ISOLATED => 72,
        };
    }
}
