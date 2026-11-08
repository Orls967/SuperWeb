<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\StateMachines;

use Modules\Aviation\Domain\Enums\CargoConsignmentStatus;
use Modules\Aviation\Domain\Exceptions\InvalidStateTransitionException;

/**
 * State Machine untuk Kargo Udara (7 status).
 * booked -> accepted -> dg_cleared -> loaded -> in_flight -> delivered -> rejected
 */
final class CargoConsignmentStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        CargoConsignmentStatus::BOOKED->value => [
            CargoConsignmentStatus::ACCEPTED->value,
            CargoConsignmentStatus::REJECTED->value,
        ],
        CargoConsignmentStatus::ACCEPTED->value => [
            CargoConsignmentStatus::DG_CLEARED->value,
            CargoConsignmentStatus::REJECTED->value,
        ],
        CargoConsignmentStatus::DG_CLEARED->value => [
            CargoConsignmentStatus::LOADED->value,
            CargoConsignmentStatus::REJECTED->value,
        ],
        CargoConsignmentStatus::LOADED->value => [
            CargoConsignmentStatus::IN_FLIGHT->value,
            CargoConsignmentStatus::REJECTED->value,
        ],
        CargoConsignmentStatus::IN_FLIGHT->value => [
            CargoConsignmentStatus::DELIVERED->value,
        ],
        CargoConsignmentStatus::DELIVERED->value => [],
        CargoConsignmentStatus::REJECTED->value => [],
    ];

    public static function canTransition(string|CargoConsignmentStatus $from, string|CargoConsignmentStatus $to): bool
    {
        $fromVal = is_string($from) ? $from : $from->value;
        $toVal = is_string($to) ? $to : $to->value;

        return in_array($toVal, self::TRANSITIONS[$fromVal] ?? [], true);
    }

    public static function assertCanTransition(string|CargoConsignmentStatus $from, string|CargoConsignmentStatus $to): void
    {
        $fromVal = is_string($from) ? $from : $from->value;
        $toVal = is_string($to) ? $to : $to->value;

        if (! self::canTransition($fromVal, $toVal)) {
            throw InvalidStateTransitionException::make('CargoConsignment', $fromVal, $toVal);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(): array
    {
        return self::TRANSITIONS;
    }
}
