<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\StateMachines;

use InvalidArgumentException;

/**
 * State Machine Versi Rate Plan & Tarif Dinamis (7 status)
 * Status: draft, simulated, rejected, approved, published, superseded, retired
 */
final class RatePlanVersionStateMachine
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SIMULATED = 'simulated';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_RETIRED = 'retired';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SIMULATED, self::STATUS_REJECTED],
        self::STATUS_SIMULATED => [self::STATUS_APPROVED, self::STATUS_REJECTED],
        self::STATUS_APPROVED => [self::STATUS_PUBLISHED, self::STATUS_REJECTED],
        self::STATUS_REJECTED => [self::STATUS_DRAFT],
        self::STATUS_PUBLISHED => [self::STATUS_SUPERSEDED, self::STATUS_RETIRED],
        self::STATUS_SUPERSEDED => [self::STATUS_RETIRED],
        self::STATUS_RETIRED => [],
    ];

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::ALLOWED_TRANSITIONS[$fromStatus] ?? [], true);
    }

    public static function assertCanTransition(string $fromStatus, string $toStatus): void
    {
        if (! self::canTransition($fromStatus, $toStatus)) {
            throw new InvalidArgumentException("Transisi versi rate plan ilegal dari {$fromStatus} ke {$toStatus}.");
        }
    }
}
