<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Enums;

enum SloTier: string
{
    case TIER_1_CRITICAL = 'tier_1_critical';
    case TIER_2_HIGH = 'tier_2_high';
    case TIER_3_STANDARD = 'tier_3_standard';

    public function defaultTargetBps(): int
    {
        return match ($this) {
            self::TIER_1_CRITICAL => 9999, // 99.99%
            self::TIER_2_HIGH => 9990,     // 99.90%
            self::TIER_3_STANDARD => 9900, // 99.00%
        };
    }
}
