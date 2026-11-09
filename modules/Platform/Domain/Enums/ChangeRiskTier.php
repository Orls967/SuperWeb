<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Enums;

enum ChangeRiskTier: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public function maxScore(): float
    {
        return match ($this) {
            self::LOW => 30.0,
            self::MEDIUM => 65.0,
            self::HIGH => 85.0,
            self::CRITICAL => 100.0,
        };
    }

    public static function fromScore(float $score): self
    {
        if ($score <= 30.0) {
            return self::LOW;
        }
        if ($score <= 65.0) {
            return self::MEDIUM;
        }
        if ($score <= 85.0) {
            return self::HIGH;
        }

        return self::CRITICAL;
    }
}
