<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\StateMachines;

use DomainException;

/**
 * State machine siklus rantai dingin dan logistik pengapalan ekspor seafood (Fase 171).
 */
class ExportShipmentStateMachine
{
    public const STATUS_DRAFT_BOOKED = 'DRAFT_BOOKED';

    public const STATUS_ESCROW_LOCKED = 'ESCROW_LOCKED';

    public const STATUS_INSPECTION_PASSED = 'INSPECTION_PASSED';

    public const STATUS_REEFER_LOADED = 'REEFER_LOADED';

    public const STATUS_CUSTOMS_CLEARED = 'CUSTOMS_CLEARED';

    public const STATUS_IN_TRANSIT = 'IN_TRANSIT';

    public const STATUS_PORT_DELIVERED = 'PORT_DELIVERED';

    public const STATUS_SETTLED = 'SETTLED';

    public const STATUS_REJECTED_QUARANTINE = 'REJECTED_QUARANTINE';

    /**
     * @var array<string, array<string>>
     */
    protected const VALID_TRANSITIONS = [
        self::STATUS_DRAFT_BOOKED => [
            self::STATUS_ESCROW_LOCKED,
            self::STATUS_REJECTED_QUARANTINE,
        ],
        self::STATUS_ESCROW_LOCKED => [
            self::STATUS_INSPECTION_PASSED,
            self::STATUS_REJECTED_QUARANTINE,
        ],
        self::STATUS_INSPECTION_PASSED => [
            self::STATUS_REEFER_LOADED,
            self::STATUS_REJECTED_QUARANTINE,
        ],
        self::STATUS_REEFER_LOADED => [
            self::STATUS_CUSTOMS_CLEARED,
            self::STATUS_REJECTED_QUARANTINE,
        ],
        self::STATUS_CUSTOMS_CLEARED => [
            self::STATUS_IN_TRANSIT,
        ],
        self::STATUS_IN_TRANSIT => [
            self::STATUS_PORT_DELIVERED,
            self::STATUS_REJECTED_QUARANTINE, // Misal cold chain break ekstrem
        ],
        self::STATUS_PORT_DELIVERED => [
            self::STATUS_SETTLED,
            self::STATUS_REJECTED_QUARANTINE,
        ],
        self::STATUS_REJECTED_QUARANTINE => [], // Terminal state
        self::STATUS_SETTLED => [],             // Terminal state
    ];

    public function canTransition(string $fromStatus, string $toStatus): bool
    {
        $allowed = self::VALID_TRANSITIONS[$fromStatus] ?? [];

        return in_array($toStatus, $allowed, true);
    }

    public function transition(string $currentStatus, string $targetStatus): string
    {
        if (! $this->canTransition($currentStatus, $targetStatus)) {
            throw new DomainException(
                "Transisi status pengapalan ekspor seafood ilegal: [{$currentStatus}] -> [{$targetStatus}]."
            );
        }

        return $targetStatus;
    }

    /**
     * @return array<string>
     */
    public function getAvailableTransitions(string $currentStatus): array
    {
        return self::VALID_TRANSITIONS[$currentStatus] ?? [];
    }
}
