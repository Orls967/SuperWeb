<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\StateMachines;

use DomainException;

final class HotelPointBatchStateMachine
{
    public const STATE_ACTIVE = 'ACTIVE';

    public const STATE_PARTIALLY_REDEEMED = 'PARTIALLY_REDEEMED';

    public const STATE_FULLY_REDEEMED = 'FULLY_REDEEMED';

    public const STATE_EXPIRED = 'EXPIRED';

    public const STATE_BREAKAGE_WRITTEN_OFF = 'BREAKAGE_WRITTEN_OFF';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATE_ACTIVE => [
            self::STATE_PARTIALLY_REDEEMED,
            self::STATE_FULLY_REDEEMED,
            self::STATE_EXPIRED,
        ],
        self::STATE_PARTIALLY_REDEEMED => [
            self::STATE_PARTIALLY_REDEEMED,
            self::STATE_FULLY_REDEEMED,
            self::STATE_EXPIRED,
        ],
        self::STATE_FULLY_REDEEMED => [],
        self::STATE_EXPIRED => [
            self::STATE_BREAKAGE_WRITTEN_OFF,
        ],
        self::STATE_BREAKAGE_WRITTEN_OFF => [],
    ];

    public static function canTransition(string $fromState, string $toState): bool
    {
        return in_array($toState, self::ALLOWED_TRANSITIONS[$fromState] ?? [], true);
    }

    public static function transition(string $fromState, string $toState): string
    {
        if (! self::canTransition($fromState, $toState)) {
            throw new DomainException("Transisi batch state tidak sah: dari {$fromState} ke {$toState}");
        }

        return $toState;
    }
}
