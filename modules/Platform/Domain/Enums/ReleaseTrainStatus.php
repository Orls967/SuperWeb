<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Enums;

enum ReleaseTrainStatus: string
{
    case OPEN = 'open';
    case FROZEN = 'frozen';
    case STAGING = 'staging';
    case DEPLOYING = 'deploying';
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case ARCHIVED = 'archived';

    public function allowsHotfix(): bool
    {
        return in_array($this, [self::FROZEN, self::STAGING, self::DEPLOYING], true);
    }
}
