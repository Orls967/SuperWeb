<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Enums;

enum HotfixSeverity: string
{
    case P1_BLOCKER = 'p1_blocker';
    case P2_CRITICAL = 'p2_critical';

    public function priorityScore(): int
    {
        return match ($this) {
            self::P1_BLOCKER => 100,
            self::P2_CRITICAL => 80,
        };
    }
}
