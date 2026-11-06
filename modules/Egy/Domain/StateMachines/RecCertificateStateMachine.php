<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\StateMachines;

use InvalidArgumentException;

/**
 * State machine untuk siklus hidup Renewable Energy Certificate (REC).
 * Alur: minted -> listed -> transferred -> retired.
 */
final class RecCertificateStateMachine
{
    public const STATUS_MINTED = 'minted';

    public const STATUS_LISTED = 'listed';

    public const STATUS_TRANSFERRED = 'transferred';

    public const STATUS_RETIRED = 'retired';

    private const TRANSITIONS = [
        self::STATUS_MINTED => [
            self::STATUS_LISTED,
            self::STATUS_RETIRED,
        ],
        self::STATUS_LISTED => [
            self::STATUS_MINTED,
            self::STATUS_TRANSFERRED,
            self::STATUS_RETIRED,
        ],
        self::STATUS_TRANSFERRED => [
            self::STATUS_LISTED,
            self::STATUS_RETIRED,
        ],
        self::STATUS_RETIRED => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function transition(string $from, string $to): string
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidArgumentException("Transisi status REC ilegal: {$from} -> {$to}");
        }

        return $to;
    }
}
