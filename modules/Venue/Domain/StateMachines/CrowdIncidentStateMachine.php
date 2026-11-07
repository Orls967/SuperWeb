<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\StateMachines;

use InvalidArgumentException;

/**
 * State Machine Penanganan Insiden & Eskalasi Keselamatan Kerumunan.
 * NORMAL -> WARNING_SURGE -> GATE_RESTRICTED -> SECONDARY_DISPATCH -> EVACUATION_STANDBY -> RESOLVED_CLEAR.
 */
final class CrowdIncidentStateMachine
{
    public const string STATUS_NORMAL = 'NORMAL';

    public const string STATUS_WARNING_SURGE = 'WARNING_SURGE';

    public const string STATUS_GATE_RESTRICTED = 'GATE_RESTRICTED';

    public const string STATUS_SECONDARY_DISPATCH = 'SECONDARY_DISPATCH';

    public const string STATUS_EVACUATION_STANDBY = 'EVACUATION_STANDBY';

    public const string STATUS_RESOLVED_CLEAR = 'RESOLVED_CLEAR';

    /**
     * @var array<string, array<int, string>>
     */
    private const array VALID_TRANSITIONS = [
        self::STATUS_NORMAL => [self::STATUS_WARNING_SURGE, self::STATUS_GATE_RESTRICTED, self::STATUS_EVACUATION_STANDBY],
        self::STATUS_WARNING_SURGE => [self::STATUS_NORMAL, self::STATUS_GATE_RESTRICTED, self::STATUS_EVACUATION_STANDBY, self::STATUS_RESOLVED_CLEAR],
        self::STATUS_GATE_RESTRICTED => [self::STATUS_SECONDARY_DISPATCH, self::STATUS_EVACUATION_STANDBY, self::STATUS_RESOLVED_CLEAR],
        self::STATUS_SECONDARY_DISPATCH => [self::STATUS_EVACUATION_STANDBY, self::STATUS_RESOLVED_CLEAR],
        self::STATUS_EVACUATION_STANDBY => [self::STATUS_RESOLVED_CLEAR],
        self::STATUS_RESOLVED_CLEAR => [self::STATUS_NORMAL],
    ];

    /**
     * Validasi apakah transisi legal.
     */
    public function canTransition(string $fromStatus, string $toStatus): bool
    {
        $allowed = self::VALID_TRANSITIONS[$fromStatus] ?? [];

        return in_array($toStatus, $allowed, true);
    }

    /**
     * Terapkan transisi status.
     *
     * @throws InvalidArgumentException
     */
    public function transition(string $fromStatus, string $toStatus): string
    {
        if (! $this->canTransition($fromStatus, $toStatus)) {
            throw new InvalidArgumentException(sprintf(
                'Transisi status insiden kerumunan ilegal dari [%s] ke [%s]',
                $fromStatus,
                $toStatus
            ));
        }

        return $toStatus;
    }

    /**
     * @return array<int, string>
     */
    public static function allStatuses(): array
    {
        return [
            self::STATUS_NORMAL,
            self::STATUS_WARNING_SURGE,
            self::STATUS_GATE_RESTRICTED,
            self::STATUS_SECONDARY_DISPATCH,
            self::STATUS_EVACUATION_STANDBY,
            self::STATUS_RESOLVED_CLEAR,
        ];
    }
}
