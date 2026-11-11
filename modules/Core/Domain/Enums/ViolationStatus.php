<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Enums;

enum ViolationStatus: string
{
    case DETECTED = 'detected';
    case TRIAGED = 'triaged';
    case CONTRACT_DESIGNED = 'contract_designed';
    case MIGRATING = 'migrating';
    case VERIFIED = 'verified';
    case CLOSED = 'closed';
    case WAIVED = 'waived';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    public function isTerminal(): bool
    {
        return $this === self::CLOSED;
    }
}
