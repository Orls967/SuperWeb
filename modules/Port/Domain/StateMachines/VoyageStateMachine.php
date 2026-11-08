<?php

declare(strict_types=1);

namespace Modules\Port\Domain\StateMachines;

use DomainException;

/**
 * State Machine Siklus Hidup Pelayaran Kapal (Voyage Lifecycle State Machine).
 * 
 * Mengatur 12 status operasional kapal niaga dari perencanaan, passage ballast,
 * tender NOR, muat, passage laden, bongkar, penyelesaian, hingga pembatalan darurat.
 */
final class VoyageStateMachine
{
    public const STATUS_PLANNED = 'PLANNED';
    public const STATUS_BALLAST_PASSAGE = 'BALLAST_PASSAGE';
    public const STATUS_ARRIVED_LOADPORT = 'ARRIVED_LOADPORT';
    public const STATUS_TENDERED_NOR = 'TENDERED_NOR';
    public const STATUS_COMMENCED_LOADING = 'COMMENCED_LOADING';
    public const STATUS_COMPLETED_LOADING = 'COMPLETED_LOADING';
    public const STATUS_LADEN_PASSAGE = 'LADEN_PASSAGE';
    public const STATUS_ARRIVED_DISCHPORT = 'ARRIVED_DISCHPORT';
    public const STATUS_DISCHARGING = 'DISCHARGING';
    public const STATUS_COMPLETED_DISCHARGING = 'COMPLETED_DISCHARGING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    /**
     * Peta transisi status yang sah beserta persyaratannya.
     * @var array<string, array<int, string>>
     */
    private const VALID_TRANSITIONS = [
        self::STATUS_PLANNED => [
            self::STATUS_BALLAST_PASSAGE,
            self::STATUS_ARRIVED_LOADPORT, // Jika kapal sudah berada di load port
            self::STATUS_CANCELLED,
        ],
        self::STATUS_BALLAST_PASSAGE => [
            self::STATUS_ARRIVED_LOADPORT,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_ARRIVED_LOADPORT => [
            self::STATUS_TENDERED_NOR,
            self::STATUS_COMMENCED_LOADING,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_TENDERED_NOR => [
            self::STATUS_COMMENCED_LOADING,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_COMMENCED_LOADING => [
            self::STATUS_COMPLETED_LOADING,
        ],
        self::STATUS_COMPLETED_LOADING => [
            self::STATUS_LADEN_PASSAGE,
        ],
        self::STATUS_LADEN_PASSAGE => [
            self::STATUS_ARRIVED_DISCHPORT,
        ],
        self::STATUS_ARRIVED_DISCHPORT => [
            self::STATUS_DISCHARGING,
        ],
        self::STATUS_DISCHARGING => [
            self::STATUS_COMPLETED_DISCHARGING,
        ],
        self::STATUS_COMPLETED_DISCHARGING => [
            self::STATUS_COMPLETED,
        ],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    public function canTransition(string $currentStatus, string $newStatus): bool
    {
        $allowed = self::VALID_TRANSITIONS[$currentStatus] ?? [];
        return in_array($newStatus, $allowed, true);
    }

    public function transition(string $currentStatus, string $newStatus, array $context = []): string
    {
        if (! $this->canTransition($currentStatus, $newStatus)) {
            throw new DomainException("Transisi status pelayaran tidak sah dari {$currentStatus} ke {$newStatus}.");
        }

        // Guard conditions spesifik:
        if ($newStatus === self::STATUS_TENDERED_NOR) {
            if (empty($context['nor_timestamp'])) {
                throw new DomainException('Tender NOR memerlukan stempel waktu deklarasi.');
            }
        }

        if ($newStatus === self::STATUS_LADEN_PASSAGE) {
            if (empty($context['bill_of_lading_issued'])) {
                throw new DomainException('Pelayaran laden hanya dapat dimulai setelah konosemen (B/L) diterbitkan.');
            }
        }

        return $newStatus;
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedTransitions(string $currentStatus): array
    {
        return self::VALID_TRANSITIONS[$currentStatus] ?? [];
    }

    public function isTerminal(string $status): bool
    {
        return in_array($status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }
}

