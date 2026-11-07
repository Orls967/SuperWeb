<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\StateMachines;

use DomainException;

final class FestivalBundleStateMachine
{
    public const STATUS_QUOTED = 'quoted';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_PAID = 'paid';

    public const STATUS_FULFILLED_PARTIAL = 'fulfilled_partial';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_REFUNDED_PARTIAL = 'refunded_partial';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_QUOTED => [
            self::STATUS_RESERVED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_RESERVED => [
            self::STATUS_PAID,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_PAID => [
            self::STATUS_FULFILLED_PARTIAL,
            self::STATUS_FULFILLED,
            self::STATUS_REFUNDED_PARTIAL,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_FULFILLED_PARTIAL => [
            self::STATUS_FULFILLED,
            self::STATUS_SETTLED,
            self::STATUS_REFUNDED_PARTIAL,
        ],
        self::STATUS_FULFILLED => [
            self::STATUS_SETTLED,
            self::STATUS_REFUNDED_PARTIAL,
        ],
        self::STATUS_SETTLED => [],
        self::STATUS_REFUNDED_PARTIAL => [
            self::STATUS_SETTLED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_CANCELLED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $normalizedFrom = strtolower($from);
        $normalizedTo = strtolower($to);

        $allowed = self::TRANSITIONS[$normalizedFrom] ?? [];

        return in_array($normalizedTo, $allowed, true);
    }

    public static function assertCanTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw new DomainException("Transisi status paket festival tidak valid dari '{$from}' ke '{$to}'.");
        }
    }
}
