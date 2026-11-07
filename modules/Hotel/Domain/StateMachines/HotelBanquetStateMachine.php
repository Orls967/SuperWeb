<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\StateMachines;

use DomainException;

final class HotelBanquetStateMachine
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_CHEF_REVIEWED = 'CHEF_REVIEWED';

    public const STATUS_SALES_CONFIRMED = 'SALES_CONFIRMED';

    public const STATUS_IN_PREPARATION = 'IN_PREPARATION';

    public const STATUS_SERVICE_COMPLETED = 'SERVICE_COMPLETED';

    public const STATUS_BILLED = 'BILLED';

    public const STATUS_CANCELLED = 'CANCELLED';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_CHEF_REVIEWED, self::STATUS_CANCELLED],
        self::STATUS_CHEF_REVIEWED => [self::STATUS_SALES_CONFIRMED, self::STATUS_DRAFT, self::STATUS_CANCELLED],
        self::STATUS_SALES_CONFIRMED => [self::STATUS_IN_PREPARATION, self::STATUS_CANCELLED],
        self::STATUS_IN_PREPARATION => [self::STATUS_SERVICE_COMPLETED],
        self::STATUS_SERVICE_COMPLETED => [self::STATUS_BILLED],
        self::STATUS_BILLED => [],
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
            throw new DomainException("Illegal transition in Banquet BEO lifecycle from [{$current}] to [{$next}].");
        }

        return $next;
    }
}
