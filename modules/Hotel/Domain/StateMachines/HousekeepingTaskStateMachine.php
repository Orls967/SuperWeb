<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\StateMachines;

use InvalidArgumentException;

/**
 * State Machine Tugas Kebersihan Housekeeping (7 status)
 * Status: assigned, in_progress, done, inspected, failed_inspection, reassigned, closed
 */
final class HousekeepingTaskStateMachine
{
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_INSPECTED = 'inspected';
    public const STATUS_FAILED_INSPECTION = 'failed_inspection';
    public const STATUS_REASSIGNED = 'reassigned';
    public const STATUS_CLOSED = 'closed';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_ASSIGNED => [self::STATUS_IN_PROGRESS, self::STATUS_REASSIGNED],
        self::STATUS_IN_PROGRESS => [self::STATUS_DONE],
        self::STATUS_DONE => [self::STATUS_INSPECTED, self::STATUS_FAILED_INSPECTION],
        self::STATUS_FAILED_INSPECTION => [self::STATUS_REASSIGNED, self::STATUS_IN_PROGRESS],
        self::STATUS_REASSIGNED => [self::STATUS_IN_PROGRESS],
        self::STATUS_INSPECTED => [self::STATUS_CLOSED],
        self::STATUS_CLOSED => [],
    ];

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::ALLOWED_TRANSITIONS[$fromStatus] ?? [], true);
    }

    public static function assertCanTransition(string $fromStatus, string $toStatus): void
    {
        if (!self::canTransition($fromStatus, $toStatus)) {
            throw new InvalidArgumentException("Transisi tugas housekeeping ilegal dari {$fromStatus} ke {$toStatus}.");
        }
    }
}
