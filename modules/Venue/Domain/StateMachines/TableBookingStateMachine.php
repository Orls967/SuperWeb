<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\StateMachines;

use DomainException;
use Modules\Venue\Domain\Enums\TableBookingStatus;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;

final class TableBookingStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        TableBookingStatus::REQUESTED->value => [
            TableBookingStatus::DEPOSIT_HELD->value,
            TableBookingStatus::CANCELLED_REFUNDED->value,
        ],
        TableBookingStatus::DEPOSIT_HELD->value => [
            TableBookingStatus::CONFIRMED->value,
            TableBookingStatus::CANCELLED_REFUNDED->value,
        ],
        TableBookingStatus::CONFIRMED->value => [
            TableBookingStatus::SEATED->value,
            TableBookingStatus::NO_SHOW_FORFEITED->value,
            TableBookingStatus::CANCELLED_REFUNDED->value,
        ],
        TableBookingStatus::SEATED->value => [
            TableBookingStatus::SETTLED->value,
            TableBookingStatus::CANCELLED_REFUNDED->value,
        ],
        TableBookingStatus::SETTLED->value => [],
        TableBookingStatus::NO_SHOW_FORFEITED->value => [],
        TableBookingStatus::CANCELLED_REFUNDED->value => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function transition(EntertainmentTableBooking $booking, TableBookingStatus $targetStatus, ?string $reason = null): void
    {
        $current = $booking->status;
        $target = $targetStatus->value;

        if (! self::canTransition($current, $target)) {
            throw new DomainException("Transisi meja VIP tidak sah dari '{$current}' ke '{$target}'");
        }

        $booking->status = $target;
        $booking->save();
    }
}

