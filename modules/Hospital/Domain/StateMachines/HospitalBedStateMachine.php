<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\StateMachines;

use DomainException;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Models\HospitalBedTransition;

/**
 * State Machine untuk status tempat tidur rumah sakit (7 status).
 * available -> reserved -> occupied -> cleaning -> maintenance -> isolated_hold -> out_of_service
 */
final class HospitalBedStateMachine
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_CLEANING = 'cleaning';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_ISOLATED_HOLD = 'isolated_hold';

    public const STATUS_OUT_OF_SERVICE = 'out_of_service';

    private const TRANSITIONS = [
        self::STATUS_AVAILABLE => [self::STATUS_RESERVED, self::STATUS_MAINTENANCE, self::STATUS_ISOLATED_HOLD, self::STATUS_OUT_OF_SERVICE],
        self::STATUS_RESERVED => [self::STATUS_OCCUPIED, self::STATUS_AVAILABLE],
        self::STATUS_OCCUPIED => [self::STATUS_CLEANING, self::STATUS_ISOLATED_HOLD],
        self::STATUS_CLEANING => [self::STATUS_AVAILABLE, self::STATUS_MAINTENANCE],
        self::STATUS_MAINTENANCE => [self::STATUS_AVAILABLE, self::STATUS_OUT_OF_SERVICE],
        self::STATUS_ISOLATED_HOLD => [self::STATUS_AVAILABLE, self::STATUS_CLEANING],
        self::STATUS_OUT_OF_SERVICE => [self::STATUS_MAINTENANCE, self::STATUS_AVAILABLE],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $fromNorm = strtolower($from);
        $toNorm = strtolower($to);
        $allowed = self::TRANSITIONS[$fromNorm] ?? [];

        return in_array($toNorm, $allowed, true);
    }

    public static function transition(
        HospitalBed $bed,
        string $toStatus,
        ?string $reason = null,
        ?int $actorId = null
    ): void {
        $fromStatus = strtolower($bed->status);
        $targetStatus = strtolower($toStatus);

        if (! self::canTransition($fromStatus, $targetStatus)) {
            throw new DomainException("Transisi status ranjang tidak sah dari '{$fromStatus}' ke '{$targetStatus}'.");
        }

        $bed->status = strtoupper($targetStatus);
        $bed->save();

        HospitalBedTransition::create([
            'bed_id' => $bed->id,
            'from_status' => $fromStatus,
            'to_status' => $targetStatus,
            'reason' => $reason,
            'actor_id' => $actorId,
            'transitioned_at' => now(),
        ]);
    }
}
