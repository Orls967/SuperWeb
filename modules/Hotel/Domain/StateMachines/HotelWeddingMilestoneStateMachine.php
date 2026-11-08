<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\StateMachines;

use DomainException;

final class HotelWeddingMilestoneStateMachine
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_INVOICED = 'INVOICED';

    public const STATUS_PAID = 'PAID';

    public const STATUS_REFUNDED = 'REFUNDED';

    public const STATUS_WAIVED = 'WAIVED';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_INVOICED, self::STATUS_PAID, self::STATUS_WAIVED],
        self::STATUS_INVOICED => [self::STATUS_PAID, self::STATUS_WAIVED],
        self::STATUS_PAID => [self::STATUS_REFUNDED],
        self::STATUS_REFUNDED => [],
        self::STATUS_WAIVED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $allowed = self::TRANSITIONS[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    public static function transition(string $current, string $next): string
    {
        if (! self::canTransition($current, $next)) {
            throw new DomainException("Illegal transition in Wedding Milestone lifecycle from [{$current}] to [{$next}].");
        }

        return $next;
    }
}
