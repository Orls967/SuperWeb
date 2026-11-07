<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\StateMachines;

use InvalidArgumentException;

/**
 * State Machine Kunci Digital Kamar Hotel (7 status)
 * Status: pending_activation, issued, active, suspended, expired, revoked, reissued
 */
final class DigitalKeyStateMachine
{
    public const STATUS_PENDING_ACTIVATION = 'pending_activation';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_REISSUED = 'reissued';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING_ACTIVATION => [self::STATUS_ISSUED, self::STATUS_REVOKED],
        self::STATUS_ISSUED => [self::STATUS_ACTIVE, self::STATUS_REVOKED, self::STATUS_EXPIRED],
        self::STATUS_ACTIVE => [self::STATUS_SUSPENDED, self::STATUS_EXPIRED, self::STATUS_REVOKED],
        self::STATUS_SUSPENDED => [self::STATUS_ACTIVE, self::STATUS_REVOKED],
        self::STATUS_EXPIRED => [self::STATUS_REISSUED],
        self::STATUS_REVOKED => [self::STATUS_REISSUED],
        self::STATUS_REISSUED => [self::STATUS_ACTIVE, self::STATUS_REVOKED],
    ];

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::ALLOWED_TRANSITIONS[$fromStatus] ?? [], true);
    }

    public static function assertCanTransition(string $fromStatus, string $toStatus): void
    {
        if (!self::canTransition($fromStatus, $toStatus)) {
            throw new InvalidArgumentException("Transisi kunci digital ilegal dari {$fromStatus} ke {$toStatus}.");
        }
    }
}
