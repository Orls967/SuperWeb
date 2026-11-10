<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Enums;

enum HedgeStatus: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case BOOKED = 'booked';
    case ACTIVE = 'active';
    case MATURED = 'matured';
    case SETTLED = 'settled';
    case ROLLED = 'rolled';
    case CANCELLED = 'cancelled';

    public function isFinal(): bool
    {
        return in_array($this, [self::SETTLED, self::ROLLED, self::CANCELLED], true);
    }
}
