<?php

declare(strict_types=1);

namespace Modules\Crypto\Domain\Enums;

enum AlertCondition: string
{
    case ABOVE = 'above';
    case BELOW = 'below';

    public function label(): string
    {
        return match ($this) {
            self::ABOVE => 'Naik di atas',
            self::BELOW => 'Turun di bawah',
        };
    }
}
