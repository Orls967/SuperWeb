<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\StateMachines;

use DomainException;

final class HotelMiceContractStateMachine
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PROPOSED = 'PROPOSED';

    public const STATUS_NEGOTIATION = 'NEGOTIATION';

    public const STATUS_CONFIRMED = 'CONFIRMED';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_RECONCILED = 'RECONCILED';

    public const STATUS_CANCELLED = 'CANCELLED';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_PROPOSED, self::STATUS_CANCELLED],
        self::STATUS_PROPOSED => [self::STATUS_NEGOTIATION, self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_NEGOTIATION => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED],
        self::STATUS_COMPLETED => [self::STATUS_RECONCILED],
        self::STATUS_RECONCILED => [],
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
            throw new DomainException("Illegal transition in MICE Contract lifecycle from [{$current}] to [{$next}].");
        }

        return $next;
    }
}
