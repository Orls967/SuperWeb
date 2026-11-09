<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Enums;

enum CanaryRecommendation: string
{
    case CONTINUE = 'continue';
    case PROMOTE = 'promote';
    case ROLLBACK = 'rollback';

    public function isRollback(): bool
    {
        return $this === self::ROLLBACK;
    }
}
