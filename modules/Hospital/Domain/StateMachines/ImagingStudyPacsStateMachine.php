<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\StateMachines;

use DomainException;

class ImagingStudyPacsStateMachine
{
    public const SCHEDULED = 'SCHEDULED';

    public const IN_PROGRESS = 'IN_PROGRESS';

    public const ACQUIRED = 'ACQUIRED';

    public const PRE_READ = 'PRE_READ';

    public const REPORTED = 'REPORTED';

    public const VERIFIED = 'VERIFIED';

    public const COMPLETED = 'COMPLETED';

    public const CANCELLED = 'CANCELLED';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::SCHEDULED => [
            self::IN_PROGRESS,
            self::COMPLETED,
            self::CANCELLED,
        ],
        self::IN_PROGRESS => [
            self::ACQUIRED,
            self::COMPLETED,
            self::CANCELLED,
        ],
        self::ACQUIRED => [
            self::PRE_READ,
            self::COMPLETED,
        ],
        self::PRE_READ => [
            self::REPORTED,
            self::COMPLETED,
        ],
        self::REPORTED => [
            self::VERIFIED,
            self::COMPLETED,
        ],
        self::VERIFIED => [
            self::COMPLETED,
        ],
        self::COMPLETED => [],
        self::CANCELLED => [],
    ];

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::ALLOWED_TRANSITIONS[$fromStatus] ?? [], true);
    }

    public static function assertCanTransition(string $fromStatus, string $toStatus): void
    {
        if (! self::canTransition($fromStatus, $toStatus)) {
            throw new DomainException(
                "Transisi status studi PACS tidak sah dari [{$fromStatus}] ke [{$toStatus}]."
            );
        }
    }
}
