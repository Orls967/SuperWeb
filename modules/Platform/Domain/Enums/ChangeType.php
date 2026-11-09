<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Enums;

enum ChangeType: string
{
    case STANDARD = 'standard';
    case NORMAL = 'normal';
    case EMERGENCY = 'emergency';
    case HOTFIX = 'hotfix';

    public function defaultRequiresCab(): bool
    {
        return match ($this) {
            self::NORMAL, self::EMERGENCY => true,
            self::STANDARD, self::HOTFIX => false,
        };
    }
}
