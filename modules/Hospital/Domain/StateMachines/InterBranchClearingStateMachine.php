<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\StateMachines;

use DomainException;

class InterBranchClearingStateMachine
{
    public const PENDING = 'PENDING';

    public const VERIFIED_AUDIT = 'VERIFIED_AUDIT';

    public const SETTLED = 'SETTLED';

    public const DISPUTED = 'DISPUTED';

    public const CANCELLED = 'CANCELLED';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::PENDING => [
            self::VERIFIED_AUDIT,
            self::SETTLED,
            self::DISPUTED,
            self::CANCELLED,
        ],
        self::VERIFIED_AUDIT => [
            self::SETTLED,
            self::DISPUTED,
        ],
        self::DISPUTED => [
            self::VERIFIED_AUDIT,
            self::SETTLED,
            self::CANCELLED,
        ],
        self::SETTLED => [],
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
                "Transisi status kliring antar-cabang tidak sah dari [{$fromStatus}] ke [{$toStatus}]."
            );
        }
    }
}
