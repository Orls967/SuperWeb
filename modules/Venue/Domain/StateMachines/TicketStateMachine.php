<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\StateMachines;

use DomainException;
use Modules\Venue\Domain\Enums\TicketStatus;
use Modules\Venue\Domain\Models\EntertainmentTicket;

final class TicketStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        TicketStatus::RESERVED->value => [
            TicketStatus::ISSUED->value,
            TicketStatus::VOID->value,
        ],
        TicketStatus::ISSUED->value => [
            TicketStatus::TRANSFERRED->value,
            TicketStatus::SCANNED_IN->value,
            TicketStatus::REFUNDED->value,
            TicketStatus::VOID->value,
        ],
        TicketStatus::TRANSFERRED->value => [
            TicketStatus::SCANNED_IN->value,
            TicketStatus::REFUNDED->value,
        ],
        TicketStatus::SCANNED_IN->value => [
            TicketStatus::SCANNED_OUT->value,
            TicketStatus::USED->value,
            TicketStatus::VOID->value,
        ],
        TicketStatus::SCANNED_OUT->value => [
            TicketStatus::SCANNED_IN->value,
            TicketStatus::USED->value,
        ],
        TicketStatus::USED->value => [],
        TicketStatus::REFUNDED->value => [],
        TicketStatus::VOID->value => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $normalizedFrom = match (strtoupper($from)) {
            'VALID' => TicketStatus::ISSUED->value,
            default => strtolower($from),
        };
        $normalizedTo = strtolower($to);

        return in_array($normalizedTo, self::TRANSITIONS[$normalizedFrom] ?? [], true);
    }

    public static function transition(EntertainmentTicket $ticket, TicketStatus $targetStatus, ?string $reason = null): void
    {
        $current = $ticket->status;
        $target = $targetStatus->value;

        if (! self::canTransition($current, $target)) {
            throw new DomainException("Transisi tiket tidak sah dari '{$current}' ke '{$target}'");
        }

        if ($targetStatus === TicketStatus::SCANNED_IN) {
            $ticket->scanned_at = $ticket->scanned_at ?? now();
            $ticket->last_direction = 'IN';
        }

        if ($targetStatus === TicketStatus::SCANNED_OUT) {
            $ticket->last_direction = 'OUT';
        }

        $ticket->status = $target;
        $ticket->save();
    }
}

