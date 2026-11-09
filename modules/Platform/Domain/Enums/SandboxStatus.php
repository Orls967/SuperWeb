<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Enums;

enum SandboxStatus: string
{
    case REQUESTED = 'requested';
    case PROVISIONING = 'provisioning';
    case SEEDED = 'seeded';
    case READY = 'ready';
    case EXPIRING = 'expiring';
    case EXPIRED = 'expired';
    case DESTROYED = 'destroyed';

    public function isTerminal(): bool
    {
        return $this === self::DESTROYED;
    }

    public function isActive(): bool
    {
        return in_array($this, [self::PROVISIONING, self::SEEDED, self::READY, self::EXPIRING], true);
    }
}
