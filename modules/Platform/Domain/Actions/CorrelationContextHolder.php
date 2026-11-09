<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\ValueObjects\CorrelationContext;

final class CorrelationContextHolder
{
    private static ?string $currentCorrelationId = null;

    public static function set(?string $id): void
    {
        self::$currentCorrelationId = $id;
    }

    public static function get(): ?string
    {
        return self::$currentCorrelationId;
    }

    public static function clear(): void
    {
        self::$currentCorrelationId = null;
    }
}

