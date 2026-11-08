<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\StateMachines;

use DomainException;

final class HotelExhibitionBoothStateMachine
{
    public const STATUS_RESERVED = 'RESERVED';

    public const STATUS_CONFIRMED = 'CONFIRMED';

    public const STATUS_INSTALLED = 'INSTALLED';

    public const STATUS_OCCUPIED = 'OCCUPIED';

    public const STATUS_DISMANTLED = 'DISMANTLED';

    public const STATUS_CANCELLED = 'CANCELLED';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_RESERVED => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_INSTALLED, self::STATUS_CANCELLED],
        self::STATUS_INSTALLED => [self::STATUS_OCCUPIED],
        self::STATUS_OCCUPIED => [self::STATUS_DISMANTLED],
        self::STATUS_DISMANTLED => [],
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
            throw new DomainException("Illegal transition in Exhibition Booth lifecycle from [{$current}] to [{$next}].");
        }

        return $next;
    }
}
