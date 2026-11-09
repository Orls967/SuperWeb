<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\StateMachines;

use DomainException;

class CriticalValuePanicStateMachine
{
    public const PRELIMINARY = 'PRELIMINARY';

    public const AUTO_VERIFIED = 'AUTO_VERIFIED';

    public const CRITICAL_HOLD = 'CRITICAL_HOLD';

    public const NOTIFIED = 'NOTIFIED';

    public const READ_BACK_VERIFIED = 'READ_BACK_VERIFIED';

    public const PATHOLOGIST_VERIFIED = 'PATHOLOGIST_VERIFIED';

    public const AMENDED = 'AMENDED';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::PRELIMINARY => [
            self::AUTO_VERIFIED,
            self::CRITICAL_HOLD,
            self::PATHOLOGIST_VERIFIED,
        ],
        self::CRITICAL_HOLD => [
            self::NOTIFIED,
            self::PATHOLOGIST_VERIFIED,
        ],
        self::NOTIFIED => [
            self::READ_BACK_VERIFIED,
            self::PATHOLOGIST_VERIFIED,
        ],
        self::READ_BACK_VERIFIED => [
            self::PATHOLOGIST_VERIFIED,
        ],
        self::AUTO_VERIFIED => [
            self::AMENDED,
        ],
        self::PATHOLOGIST_VERIFIED => [
            self::AMENDED,
        ],
        self::AMENDED => [],
    ];

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::ALLOWED_TRANSITIONS[$fromStatus] ?? [], true);
    }

    public static function assertCanTransition(string $fromStatus, string $toStatus): void
    {
        if (! self::canTransition($fromStatus, $toStatus)) {
            throw new DomainException(
                "Transisi status hasil lab tidak sah dari [{$fromStatus}] ke [{$toStatus}]."
            );
        }
    }
}
