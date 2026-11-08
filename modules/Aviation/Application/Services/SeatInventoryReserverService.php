<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Application\Actions\BookPassengerTicketAction;
use Modules\Aviation\Application\Actions\HoldSeatInventoryAction;
use Modules\Aviation\Contracts\SeatInventoryReserver;
use Modules\Aviation\Domain\Models\AviationSeatHold;
use Modules\Aviation\Domain\Models\AviationSeatInventory;
use Modules\Aviation\Domain\Models\AviationTicket;

class SeatInventoryReserverService implements SeatInventoryReserver
{
    public function __construct(
        private readonly HoldSeatInventoryAction $holdAction,
        private readonly BookPassengerTicketAction $bookAction
    ) {}

    public function hold(int $flightId, string $cabin, int $seats, string $idempotencyKey): array
    {
        $hold = $this->holdAction->execute($flightId, $cabin, $seats, $idempotencyKey);

        return [
            'hold_id' => (int) $hold->id,
            'fare_minor' => (int) $hold->fare_minor,
            'currency' => (string) $hold->currency,
            'expires_at' => (string) $hold->expires_at,
        ];
    }

    public function confirm(int $holdId, array $passengerPartyIds, string $idempotencyKey): array
    {
        $names = array_map(fn ($id) => "Passenger Party #{$id}", $passengerPartyIds);
        $tickets = $this->bookAction->execute($holdId, $passengerPartyIds, $names, $idempotencyKey);

        return array_map(fn (AviationTicket $t) => (int) $t->id, $tickets);
    }

    public function release(int $holdId, string $idempotencyKey): void
    {
        DB::transaction(function () use ($holdId) {
            $hold = AviationSeatHold::find($holdId);
            if (! $hold || $hold->is_released || $hold->is_confirmed) {
                return;
            }

            $inventory = AviationSeatInventory::where('flight_id', $hold->flight_id)
                ->where('cabin', $hold->cabin)
                ->lockForUpdate()
                ->first();

            if ($inventory) {
                $inventory->held_seats = max(0, $inventory->held_seats - $hold->seats_count);
                $inventory->save();
            }

            $hold->is_released = true;
            $hold->save();
        });
    }
}
