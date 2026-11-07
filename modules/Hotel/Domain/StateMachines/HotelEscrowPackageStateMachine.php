<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\StateMachines;

use DomainException;

final class HotelEscrowPackageStateMachine
{
    public const STATE_INITIATED = 'INITIATED';

    public const STATE_ESCROW_LOCKED = 'ESCROW_LOCKED';

    public const STATE_PARTIAL_RELEASED = 'PARTIAL_RELEASED';

    public const STATE_FULLY_SETTLED = 'FULLY_SETTLED';

    public const STATE_DISPUTED = 'DISPUTED';

    public const STATE_REFUNDED = 'REFUNDED';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATE_INITIATED => [self::STATE_ESCROW_LOCKED, self::STATE_REFUNDED],
        self::STATE_ESCROW_LOCKED => [self::STATE_PARTIAL_RELEASED, self::STATE_FULLY_SETTLED, self::STATE_DISPUTED],
        self::STATE_PARTIAL_RELEASED => [self::STATE_FULLY_SETTLED, self::STATE_DISPUTED],
        self::STATE_DISPUTED => [self::STATE_PARTIAL_RELEASED, self::STATE_FULLY_SETTLED, self::STATE_REFUNDED],
        self::STATE_FULLY_SETTLED => [],
        self::STATE_REFUNDED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $allowed = self::TRANSITIONS[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    public static function transition(string $current, string $next): string
    {
        if (! self::canTransition($current, $next)) {
            throw new DomainException("Illegal escrow transition from [{$current}] to [{$next}].");
        }

        return $next;
    }
}
