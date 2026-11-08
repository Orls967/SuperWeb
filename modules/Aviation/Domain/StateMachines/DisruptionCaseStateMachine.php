<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\StateMachines;

use DomainException;

/**
 * State Machine untuk penanganan kasus disrupsi operasional (IRROPS).
 * 8 Status: detected, classified, care_dispatched, reaccommodating, rebooked, compensation_settled, closed, disputed.
 */
class DisruptionCaseStateMachine
{
    public const STATUS_DETECTED = 'detected';

    public const STATUS_CLASSIFIED = 'classified';

    public const STATUS_CARE_DISPATCHED = 'care_dispatched';

    public const STATUS_REACCOMMODATING = 'reaccommodating';

    public const STATUS_REBOOKED = 'rebooked';

    public const STATUS_COMPENSATION_SETTLED = 'compensation_settled';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_DISPUTED = 'disputed';

    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_DETECTED => [self::STATUS_CLASSIFIED, self::STATUS_DISPUTED],
        self::STATUS_CLASSIFIED => [self::STATUS_CARE_DISPATCHED, self::STATUS_REACCOMMODATING, self::STATUS_DISPUTED],
        self::STATUS_CARE_DISPATCHED => [self::STATUS_REACCOMMODATING, self::STATUS_COMPENSATION_SETTLED, self::STATUS_DISPUTED],
        self::STATUS_REACCOMMODATING => [self::STATUS_REBOOKED, self::STATUS_DISPUTED],
        self::STATUS_REBOOKED => [self::STATUS_COMPENSATION_SETTLED, self::STATUS_CLOSED, self::STATUS_DISPUTED],
        self::STATUS_COMPENSATION_SETTLED => [self::STATUS_CLOSED, self::STATUS_DISPUTED],
        self::STATUS_DISPUTED => [self::STATUS_CLASSIFIED, self::STATUS_COMPENSATION_SETTLED, self::STATUS_CLOSED],
        self::STATUS_CLOSED => [],
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
                'Transisi status kasus disrupsi IRROPS dari [%s] ke [%s] tidak sah.',
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
