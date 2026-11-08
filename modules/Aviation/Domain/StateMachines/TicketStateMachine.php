<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\StateMachines;

use Modules\Aviation\Domain\Enums\TicketStatus;
use Modules\Aviation\Domain\Exceptions\InvalidStateTransitionException;

/**
 * State Machine untuk Tiket Penumpang (8 status).
 * held -> paid -> issued -> checked_in -> boarded -> flown -> refunded -> no_show
 */
final class TicketStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        TicketStatus::HELD->value => [
            TicketStatus::PAID->value,
            TicketStatus::REFUNDED->value,
        ],
        TicketStatus::PAID->value => [
            TicketStatus::ISSUED->value,
            TicketStatus::REFUNDED->value,
        ],
        TicketStatus::ISSUED->value => [
            TicketStatus::CHECKED_IN->value,
            TicketStatus::REFUNDED->value,
            TicketStatus::NO_SHOW->value,
        ],
        TicketStatus::CHECKED_IN->value => [
            TicketStatus::BOARDED->value,
            TicketStatus::REFUNDED->value,
            TicketStatus::NO_SHOW->value,
        ],
        TicketStatus::BOARDED->value => [
            TicketStatus::FLOWN->value,
        ],
        TicketStatus::FLOWN->value => [],
        TicketStatus::REFUNDED->value => [],
        TicketStatus::NO_SHOW->value => [
            TicketStatus::REFUNDED->value, // Refund no-show via A4 FLEX
        ],
    ];

    public static function canTransition(string|TicketStatus $from, string|TicketStatus $to): bool
    {
        $fromVal = is_string($from) ? $from : $from->value;
        $toVal = is_string($to) ? $to : $to->value;

        return in_array($toVal, self::TRANSITIONS[$fromVal] ?? [], true);
    }

    public static function assertCanTransition(string|TicketStatus $from, string|TicketStatus $to): void
    {
        $fromVal = is_string($from) ? $from : $from->value;
        $toVal = is_string($to) ? $to : $to->value;

        if (! self::canTransition($fromVal, $toVal)) {
            throw InvalidStateTransitionException::make('Ticket', $fromVal, $toVal);
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
