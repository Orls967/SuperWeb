<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\StateMachines;

use DomainException;

/**
 * State Machine untuk siklus hidup perjanjian kerjasama codeshare antar-maskapai.
 * 7 Status: proposed, negotiating, signed, active, amendment_pending, suspended, expired.
 */
class CodeshareAgreementStateMachine
{
    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_NEGOTIATING = 'negotiating';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_AMENDMENT_PENDING = 'amendment_pending';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_EXPIRED = 'expired';

    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_PROPOSED => [self::STATUS_NEGOTIATING, self::STATUS_EXPIRED],
        self::STATUS_NEGOTIATING => [self::STATUS_SIGNED, self::STATUS_EXPIRED],
        self::STATUS_SIGNED => [self::STATUS_ACTIVE, self::STATUS_EXPIRED],
        self::STATUS_ACTIVE => [self::STATUS_AMENDMENT_PENDING, self::STATUS_SUSPENDED, self::STATUS_EXPIRED],
        self::STATUS_AMENDMENT_PENDING => [self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_EXPIRED],
        self::STATUS_SUSPENDED => [self::STATUS_ACTIVE, self::STATUS_EXPIRED],
        self::STATUS_EXPIRED => [],
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
                'Transisi status perjanjian codeshare dari [%s] ke [%s] tidak sah.',
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
