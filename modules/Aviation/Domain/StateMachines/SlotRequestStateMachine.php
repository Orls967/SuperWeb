<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\StateMachines;

use Modules\Aviation\Domain\Enums\SlotRequestStatus;
use Modules\Aviation\Domain\Exceptions\InvalidStateTransitionException;

/**
 * State Machine untuk Permintaan Slot Bandara (8 status).
 * submitted -> under_review -> allocated -> waitlisted -> confirmed -> returned -> revoked -> expired
 */
final class SlotRequestStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        SlotRequestStatus::SUBMITTED->value => [
            SlotRequestStatus::UNDER_REVIEW->value,
            SlotRequestStatus::EXPIRED->value,
        ],
        SlotRequestStatus::UNDER_REVIEW->value => [
            SlotRequestStatus::ALLOCATED->value,
            SlotRequestStatus::WAITLISTED->value,
            SlotRequestStatus::EXPIRED->value,
        ],
        SlotRequestStatus::ALLOCATED->value => [
            SlotRequestStatus::CONFIRMED->value,
            SlotRequestStatus::RETURNED->value,
            SlotRequestStatus::EXPIRED->value,
        ],
        SlotRequestStatus::WAITLISTED->value => [
            SlotRequestStatus::ALLOCATED->value,
            SlotRequestStatus::EXPIRED->value,
        ],
        SlotRequestStatus::CONFIRMED->value => [
            SlotRequestStatus::RETURNED->value,
            SlotRequestStatus::REVOKED->value,
        ],
        SlotRequestStatus::RETURNED->value => [],
        SlotRequestStatus::REVOKED->value => [],
        SlotRequestStatus::EXPIRED->value => [],
    ];

    public static function canTransition(string|SlotRequestStatus $from, string|SlotRequestStatus $to): bool
    {
        $fromVal = is_string($from) ? $from : $from->value;
        $toVal = is_string($to) ? $to : $to->value;

        return in_array($toVal, self::TRANSITIONS[$fromVal] ?? [], true);
    }

    public static function assertCanTransition(string|SlotRequestStatus $from, string|SlotRequestStatus $to): void
    {
        $fromVal = is_string($from) ? $from : $from->value;
        $toVal = is_string($to) ? $to : $to->value;

        if (! self::canTransition($fromVal, $toVal)) {
            throw InvalidStateTransitionException::make('SlotRequest', $fromVal, $toVal);
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
