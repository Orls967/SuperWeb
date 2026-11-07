<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\StateMachines;

use DomainException;

final class MembershipStateMachine
{
    public const STATUS_APPLIED = 'applied';

    public const STATUS_KYC_PENDING = 'kyc_pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_GRACE = 'grace';

    public const STATUS_DOWNGRADED = 'downgraded';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_APPLIED => [
            self::STATUS_KYC_PENDING,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_KYC_PENDING => [
            self::STATUS_ACTIVE,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_ACTIVE => [
            self::STATUS_GRACE,
            self::STATUS_DOWNGRADED,
            self::STATUS_SUSPENDED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_GRACE => [
            self::STATUS_ACTIVE,
            self::STATUS_DOWNGRADED,
            self::STATUS_SUSPENDED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_DOWNGRADED => [
            self::STATUS_ACTIVE,
            self::STATUS_SUSPENDED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_SUSPENDED => [
            self::STATUS_ACTIVE,
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
            throw new DomainException("Transisi status membership tidak valid dari '{$from}' ke '{$to}'.");
        }
    }
}
