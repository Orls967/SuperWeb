<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\StateMachines;

use DomainException;
use Modules\Venue\Domain\Models\TicketWaitlist;

final class WaitlistStateMachine
{
    public const STATUS_WAITING = 'WAITING';

    public const STATUS_OFFERED = 'OFFERED';

    public const STATUS_CLAIMED = 'CLAIMED';

    public const STATUS_EXPIRED = 'EXPIRED';

    public const STATUS_CANCELLED = 'CANCELLED';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_WAITING => [
            self::STATUS_OFFERED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_OFFERED => [
            self::STATUS_CLAIMED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_CLAIMED => [],
        self::STATUS_EXPIRED => [
            self::STATUS_WAITING, // Re-queue allowed once
            self::STATUS_CANCELLED,
        ],
        self::STATUS_CANCELLED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $fromUpper = strtoupper($from);
        $toUpper = strtoupper($to);

        $allowed = self::TRANSITIONS[$fromUpper] ?? [];

        return in_array($toUpper, $allowed, true);
    }

    public static function transition(TicketWaitlist $waitlist, string $to): TicketWaitlist
    {
        $current = strtoupper((string) $waitlist->status);
        $target = strtoupper($to);

        if (! self::canTransition($current, $target)) {
            throw new DomainException(sprintf('Transisi status antrean waitlist dari %s ke %s tidak diizinkan', $current, $target));
        }

        $waitlist->status = $target;

        return $waitlist;
    }
}
