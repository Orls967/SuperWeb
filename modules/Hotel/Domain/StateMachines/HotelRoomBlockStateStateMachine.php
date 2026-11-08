<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\StateMachines;

use DomainException;

final class HotelRoomBlockStateStateMachine
{
    public const STATUS_TENTATIVE = 'TENTATIVE';

    public const STATUS_CONTRACTED = 'CONTRACTED';

    public const STATUS_H30_RELEASED = 'H30_RELEASED';

    public const STATUS_H14_RELEASED = 'H14_RELEASED';

    public const STATUS_H7_FINALIZED = 'H7_FINALIZED';

    public const STATUS_EXPIRED = 'EXPIRED';

    public const STATUS_CANCELLED = 'CANCELLED';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_TENTATIVE => [self::STATUS_CONTRACTED, self::STATUS_CANCELLED],
        self::STATUS_CONTRACTED => [self::STATUS_H30_RELEASED, self::STATUS_H14_RELEASED, self::STATUS_H7_FINALIZED, self::STATUS_CANCELLED],
        self::STATUS_H30_RELEASED => [self::STATUS_H14_RELEASED, self::STATUS_H7_FINALIZED, self::STATUS_CANCELLED],
        self::STATUS_H14_RELEASED => [self::STATUS_H7_FINALIZED, self::STATUS_CANCELLED],
        self::STATUS_H7_FINALIZED => [self::STATUS_EXPIRED],
        self::STATUS_EXPIRED => [],
        self::STATUS_CANCELLED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $allowed = self::TRANSITIONS[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    public static function transition(string $current, string $next): string
    {
        if (! self::canTransition($current, $next)) {
            throw new DomainException("Illegal transition in Room Block Allotment lifecycle from [{$current}] to [{$next}].");
        }

        return $next;
    }
}
