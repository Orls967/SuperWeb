<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\StateMachines;

use DomainException;

final class ArtistEngagementStateMachine
{
    public const STATUS_OFFERED = 'offered';

    public const STATUS_CONTRACTED = 'contracted';

    public const STATUS_ADVANCE_PAID = 'advance_paid';

    public const STATUS_PERFORMED = 'performed';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_DISPUTED = 'disputed';

    public const STATUS_CANCELLED_FORCE_MAJEURE = 'cancelled_force_majeure';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_OFFERED => [
            self::STATUS_CONTRACTED,
            self::STATUS_CANCELLED_FORCE_MAJEURE,
        ],
        self::STATUS_CONTRACTED => [
            self::STATUS_ADVANCE_PAID,
            self::STATUS_CANCELLED_FORCE_MAJEURE,
        ],
        self::STATUS_ADVANCE_PAID => [
            self::STATUS_PERFORMED,
            self::STATUS_CANCELLED_FORCE_MAJEURE,
        ],
        self::STATUS_PERFORMED => [
            self::STATUS_SETTLED,
            self::STATUS_DISPUTED,
        ],
        self::STATUS_DISPUTED => [
            self::STATUS_SETTLED,
            self::STATUS_CANCELLED_FORCE_MAJEURE,
        ],
        self::STATUS_SETTLED => [],
        self::STATUS_CANCELLED_FORCE_MAJEURE => [],
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
            throw new DomainException("Transisi status engagement artis tidak valid dari '{$from}' ke '{$to}'.");
        }
    }
}
