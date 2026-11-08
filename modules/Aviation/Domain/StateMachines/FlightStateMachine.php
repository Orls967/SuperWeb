<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\StateMachines;

use Modules\Aviation\Domain\Enums\FlightStatus;
use Modules\Aviation\Domain\Exceptions\InvalidStateTransitionException;

/**
 * State Machine untuk Siklus Penerbangan (8 status).
 * planned -> slot_secured -> airworthy_cleared -> boarding -> departed -> arrived -> closed -> cancelled
 */
final class FlightStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        FlightStatus::PLANNED->value => [
            FlightStatus::SLOT_SECURED->value,
            FlightStatus::CANCELLED->value,
        ],
        FlightStatus::SLOT_SECURED->value => [
            FlightStatus::AIRWORTHY_CLEARED->value,
            FlightStatus::CANCELLED->value,
        ],
        FlightStatus::AIRWORTHY_CLEARED->value => [
            FlightStatus::BOARDING->value,
            FlightStatus::DEPARTED->value,
            FlightStatus::CANCELLED->value,
        ],
        FlightStatus::BOARDING->value => [
            FlightStatus::DEPARTED->value,
            FlightStatus::CANCELLED->value,
        ],
        FlightStatus::DEPARTED->value => [
            FlightStatus::ARRIVED->value,
        ],
        FlightStatus::ARRIVED->value => [
            FlightStatus::CLOSED->value,
        ],
        FlightStatus::CLOSED->value => [],
        FlightStatus::CANCELLED->value => [],
    ];

    public static function canTransition(string|FlightStatus $from, string|FlightStatus $to): bool
    {
        $fromVal = is_string($from) ? $from : $from->value;
        $toVal = is_string($to) ? $to : $to->value;

        return in_array($toVal, self::TRANSITIONS[$fromVal] ?? [], true);
    }

    public static function assertCanTransition(string|FlightStatus $from, string|FlightStatus $to): void
    {
        $fromVal = is_string($from) ? $from : $from->value;
        $toVal = is_string($to) ? $to : $to->value;

        if (! self::canTransition($fromVal, $toVal)) {
            throw InvalidStateTransitionException::make('Flight', $fromVal, $toVal);
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
