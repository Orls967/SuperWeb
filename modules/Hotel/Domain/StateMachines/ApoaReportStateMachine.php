<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\StateMachines;

use InvalidArgumentException;

/**
 * State Machine Kepatuhan Laporan APOA UU Keimigrasian 6/2011 (7 status)
 * Status: pending, submitted, acknowledged, late, escalated, corrected, closed
 */
final class ApoaReportStateMachine
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_LATE = 'late';
    public const STATUS_ESCALATED = 'escalated';
    public const STATUS_CORRECTED = 'corrected';
    public const STATUS_CLOSED = 'closed';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_SUBMITTED, self::STATUS_LATE],
        self::STATUS_SUBMITTED => [self::STATUS_ACKNOWLEDGED, self::STATUS_CORRECTED],
        self::STATUS_LATE => [self::STATUS_ESCALATED, self::STATUS_SUBMITTED],
        self::STATUS_ESCALATED => [self::STATUS_SUBMITTED],
        self::STATUS_CORRECTED => [self::STATUS_ACKNOWLEDGED, self::STATUS_SUBMITTED],
        self::STATUS_ACKNOWLEDGED => [self::STATUS_CLOSED],
        self::STATUS_CLOSED => [],
    ];

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::ALLOWED_TRANSITIONS[$fromStatus] ?? [], true);
    }

    public static function assertCanTransition(string $fromStatus, string $toStatus): void
    {
        if (!self::canTransition($fromStatus, $toStatus)) {
            throw new InvalidArgumentException("Transisi status pelaporan APOA ilegal dari {$fromStatus} ke {$toStatus}.");
        }
    }
}
