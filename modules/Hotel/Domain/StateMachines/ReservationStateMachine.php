<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\StateMachines;

use InvalidArgumentException;

/**
 * State Machine Reservasi Tamu Hotel (9 status)
 * Status: inquiry, tentative, confirmed, reconfirmed, checked_in, checked_out, no_show, cancelled, walked
 */
final class ReservationStateMachine
{
    public const STATUS_INQUIRY = 'inquiry';
    public const STATUS_TENTATIVE = 'tentative';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_RECONFIRMED = 'reconfirmed';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_WALKED = 'walked';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_INQUIRY => [self::STATUS_TENTATIVE, self::STATUS_CANCELLED],
        self::STATUS_TENTATIVE => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_RECONFIRMED, self::STATUS_CHECKED_IN, self::STATUS_CANCELLED, self::STATUS_WALKED, self::STATUS_NO_SHOW],
        self::STATUS_RECONFIRMED => [self::STATUS_CHECKED_IN, self::STATUS_CANCELLED, self::STATUS_WALKED, self::STATUS_NO_SHOW],
        self::STATUS_CHECKED_IN => [self::STATUS_CHECKED_OUT],
        self::STATUS_CHECKED_OUT => [],
        self::STATUS_NO_SHOW => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_WALKED => [],
    ];

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::ALLOWED_TRANSITIONS[$fromStatus] ?? [], true);
    }

    public static function assertCanTransition(string $fromStatus, string $toStatus): void
    {
        if (!self::canTransition($fromStatus, $toStatus)) {
            throw new InvalidArgumentException("Transisi status reservasi ilegal dari {$fromStatus} ke {$toStatus}.");
        }
    }
}
