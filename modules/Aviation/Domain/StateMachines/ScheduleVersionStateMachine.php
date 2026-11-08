<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\StateMachines;

use DomainException;

/**
 * State Machine untuk siklus hidup versi jadwal penerbangan musiman (IATA Season).
 * 7 Status: draft, review, published, active, superseded, suspended, withdrawn.
 */
class ScheduleVersionStateMachine
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEW = 'review';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_WITHDRAWN = 'withdrawn';

    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_REVIEW, self::STATUS_WITHDRAWN],
        self::STATUS_REVIEW => [self::STATUS_PUBLISHED, self::STATUS_DRAFT, self::STATUS_WITHDRAWN],
        self::STATUS_PUBLISHED => [self::STATUS_ACTIVE, self::STATUS_WITHDRAWN],
        self::STATUS_ACTIVE => [self::STATUS_SUPERSEDED, self::STATUS_SUSPENDED],
        self::STATUS_SUSPENDED => [self::STATUS_ACTIVE, self::STATUS_SUPERSEDED, self::STATUS_WITHDRAWN],
        self::STATUS_SUPERSEDED => [],
        self::STATUS_WITHDRAWN => [],
    ];

    public function canTransition(string $fromStatus, string $toStatus): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];

        return in_array($toStatus, $allowed, true);
    }

    public function transition(string $currentStatus, string $targetStatus): string
    {
        if (! $this->canTransition($currentStatus, $targetStatus)) {
            throw new DomainException(sprintf(
                'Transisi status versi jadwal penerbangan dari [%s] ke [%s] tidak sah.',
                $currentStatus,
                $targetStatus
            ));
        }

        return $targetStatus;
    }

    /**
     * @return array<int, string>
     */
    public function allowedTransitionsFrom(string $status): array
    {
        return self::ALLOWED_TRANSITIONS[$status] ?? [];
    }
}
