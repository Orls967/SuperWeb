<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\StateMachines;

use DomainException;

final class HotelTimeshareYieldStateMachine
{
    public const STAGE_POOL_ACCRUING = 'POOL_ACCRUING';

    public const STAGE_RECONCILED = 'RECONCILED';

    public const STAGE_FEE_DEDUCTED = 'FEE_DEDUCTED';

    public const STAGE_DISTRIBUTED = 'DISTRIBUTED';

    public const STAGE_AUDIT_LOCKED = 'AUDIT_LOCKED';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STAGE_POOL_ACCRUING => [self::STAGE_RECONCILED],
        self::STAGE_RECONCILED => [self::STAGE_FEE_DEDUCTED, self::STAGE_POOL_ACCRUING],
        self::STAGE_FEE_DEDUCTED => [self::STAGE_DISTRIBUTED],
        self::STAGE_DISTRIBUTED => [self::STAGE_AUDIT_LOCKED],
        self::STAGE_AUDIT_LOCKED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $allowed = self::TRANSITIONS[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    public static function transition(string $current, string $next): string
    {
        if (! self::canTransition($current, $next)) {
            throw new DomainException("Illegal transition in Timeshare Yield lifecycle from [{$current}] to [{$next}].");
        }

        return $next;
    }
}
