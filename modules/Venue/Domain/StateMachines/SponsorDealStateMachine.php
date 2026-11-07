<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\StateMachines;

use DomainException;

final class SponsorDealStateMachine
{
    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_ACTIVATED = 'activated';

    public const STATUS_MILESTONE_DUE = 'milestone_due';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_PAID = 'paid';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_DISPUTED = 'disputed';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_PROPOSED => [
            self::STATUS_SIGNED,
            self::STATUS_CLOSED,
        ],
        self::STATUS_SIGNED => [
            self::STATUS_ACTIVATED,
            self::STATUS_DISPUTED,
            self::STATUS_CLOSED,
        ],
        self::STATUS_ACTIVATED => [
            self::STATUS_MILESTONE_DUE,
            self::STATUS_DISPUTED,
            self::STATUS_CLOSED,
        ],
        self::STATUS_MILESTONE_DUE => [
            self::STATUS_INVOICED,
            self::STATUS_DISPUTED,
        ],
        self::STATUS_INVOICED => [
            self::STATUS_PAID,
            self::STATUS_DISPUTED,
        ],
        self::STATUS_PAID => [
            self::STATUS_MILESTONE_DUE,
            self::STATUS_CLOSED,
        ],
        self::STATUS_DISPUTED => [
            self::STATUS_ACTIVATED,
            self::STATUS_INVOICED,
            self::STATUS_CLOSED,
        ],
        self::STATUS_CLOSED => [],
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
            throw new DomainException("Transisi status kesepakatan sponsor tidak valid dari '{$from}' ke '{$to}'.");
        }
    }
}
