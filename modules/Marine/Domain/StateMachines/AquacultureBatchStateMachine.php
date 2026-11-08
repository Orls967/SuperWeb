<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\StateMachines;

use DomainException;

/**
 * State machine siklus budidaya tambak udang / KJA ikan (Fase 171).
 */
class AquacultureBatchStateMachine
{
    public const STATUS_HATCHERY_STOCKED = 'HATCHERY_STOCKED';

    public const STATUS_NURSERY_REARING = 'NURSERY_REARING';

    public const STATUS_GROWOUT_PHASE = 'GROWOUT_PHASE';

    public const STATUS_PRE_HARVEST_TESTING = 'PRE_HARVEST_TESTING';

    public const STATUS_HARVESTING = 'HARVESTING';

    public const STATUS_HARVESTED = 'HARVESTED';

    public const STATUS_FALLOWING = 'FALLOWING';

    public const STATUS_POND_STERILIZED = 'POND_STERILIZED';

    /**
     * @var array<string, array<string>>
     */
    protected const VALID_TRANSITIONS = [
        self::STATUS_HATCHERY_STOCKED => [
            self::STATUS_NURSERY_REARING,
            self::STATUS_GROWOUT_PHASE,
        ],
        self::STATUS_NURSERY_REARING => [
            self::STATUS_GROWOUT_PHASE,
        ],
        self::STATUS_GROWOUT_PHASE => [
            self::STATUS_PRE_HARVEST_TESTING,
            self::STATUS_HARVESTING,
        ],
        self::STATUS_PRE_HARVEST_TESTING => [
            self::STATUS_HARVESTING,
            self::STATUS_GROWOUT_PHASE, // Jika belum memenuhi ukuran pasar
        ],
        self::STATUS_HARVESTING => [
            self::STATUS_HARVESTED,
        ],
        self::STATUS_HARVESTED => [
            self::STATUS_FALLOWING,
        ],
        self::STATUS_FALLOWING => [
            self::STATUS_POND_STERILIZED,
        ],
        self::STATUS_POND_STERILIZED => [
            self::STATUS_HATCHERY_STOCKED, // Siklus baru
        ],
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
                "Transisi status siklus budidaya akuakultur ilegal: [{$currentStatus}] -> [{$targetStatus}]."
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
