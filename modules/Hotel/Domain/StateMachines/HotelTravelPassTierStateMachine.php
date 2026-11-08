<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\StateMachines;

use DomainException;

final class HotelTravelPassTierStateMachine
{
    public const STATE_ACTIVE = 'ACTIVE';

    public const STATE_GRACE_PERIOD = 'GRACE_PERIOD';

    public const STATE_DOWNGRADED = 'DOWNGRADED';

    public const STATE_SUSPENDED = 'SUSPENDED';

    public const STATE_REQUALIFIED = 'REQUALIFIED';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATE_ACTIVE => [
            self::STATE_GRACE_PERIOD,
            self::STATE_REQUALIFIED,
            self::STATE_SUSPENDED,
        ],
        self::STATE_GRACE_PERIOD => [
            self::STATE_REQUALIFIED,
            self::STATE_DOWNGRADED,
            self::STATE_SUSPENDED,
        ],
        self::STATE_DOWNGRADED => [
            self::STATE_ACTIVE,
            self::STATE_GRACE_PERIOD,
            self::STATE_SUSPENDED,
        ],
        self::STATE_REQUALIFIED => [
            self::STATE_ACTIVE,
            self::STATE_GRACE_PERIOD,
            self::STATE_SUSPENDED,
        ],
        self::STATE_SUSPENDED => [
            self::STATE_ACTIVE,
        ],
    ];

    public static function canTransition(string $fromState, string $toState): bool
    {
        return in_array($toState, self::ALLOWED_TRANSITIONS[$fromState] ?? [], true);
    }

    public static function transition(string $fromState, string $toState): string
    {
        if (! self::canTransition($fromState, $toState)) {
            throw new DomainException("Transisi state tier tidak sah: dari {$fromState} ke {$toState}");
        }

        return $toState;
    }
}
