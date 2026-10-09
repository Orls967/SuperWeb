<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Enums;

enum PartyStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Suspended = 'suspended';
    case Blacklisted = 'blacklisted';

    public function canTransitionTo(self $new): bool
    {
        return match ($this) {
            self::Pending => in_array($new, [self::Verified, self::Suspended, self::Blacklisted]),
            self::Verified => in_array($new, [self::Suspended, self::Blacklisted]),
            self::Suspended => in_array($new, [self::Pending, self::Verified, self::Blacklisted]),
            self::Blacklisted => false,
        };
    }
}
