<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\StateMachines;

use InvalidArgumentException;

/**
 * State machine untuk siklus hidup pesanan perdagangan kredit karbon.
 * Alur: open -> partially_filled -> filled / cancelled / expired.
 */
final class CarbonOrderStateMachine
{
    public const STATUS_OPEN = 'open';

    public const STATUS_PARTIALLY_FILLED = 'partially_filled';

    public const STATUS_FILLED = 'filled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    private const TRANSITIONS = [
        self::STATUS_OPEN => [
            self::STATUS_PARTIALLY_FILLED,
            self::STATUS_FILLED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ],
        self::STATUS_PARTIALLY_FILLED => [
            self::STATUS_FILLED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ],
        self::STATUS_FILLED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_EXPIRED => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function transition(string $from, string $to): string
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidArgumentException("Transisi status Carbon Order ilegal: {$from} -> {$to}");
        }

        return $to;
    }
}
