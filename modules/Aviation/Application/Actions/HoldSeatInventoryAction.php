<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Exceptions\CapacityExceededException;
use Modules\Aviation\Domain\Exceptions\IdempotencyConflictException;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationSeatHold;
use Modules\Aviation\Domain\Models\AviationSeatInventory;

class HoldSeatInventoryAction
{
    private const TTL_MINUTES = 15;

    public function execute(
        int $flightId,
        string $cabin,
        int $seats,
        string $idempotencyKey
    ): AviationSeatHold {
        return DB::transaction(function () use ($flightId, $cabin, $seats, $idempotencyKey) {
            // Cek idempotensi
            $existing = AviationSeatHold::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->flight_id !== $flightId || $existing->cabin !== $cabin || $existing->seats_count !== $seats) {
                    throw IdempotencyConflictException::forAction('HOLD_SEAT', $idempotencyKey);
                }

                return $existing;
            }

            $flight = AviationFlight::with('route')->findOrFail($flightId);

            // Lock row inventori kursi
            $inventory = AviationSeatInventory::where('flight_id', $flightId)
                ->where('cabin', $cabin)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                // Buat jika belum ada
                $inventory = AviationSeatInventory::create([
                    'flight_id' => $flightId,
                    'cabin' => $cabin,
                    'total_capacity' => ($cabin === 'J') ? 12 : 150,
                    'held_seats' => 0,
                    'booked_seats' => 0,
                    'oversell_limit_seats' => 0,
                ]);
            }

            $available = $inventory->availableSeats();
            if ($available < $seats) {
                throw CapacityExceededException::forFlightCabin($flightId, $cabin, $seats, $available);
            }

            $inventory->held_seats += $seats;
            $inventory->save();

            $baseFare = $flight->route ? $flight->route->base_fare_minor : 150000000;
            $fareMinor = $baseFare * $seats;
            $currency = $flight->route ? $flight->route->currency : 'IDR';
            $expiresAt = CarbonImmutable::now()->addMinutes(self::TTL_MINUTES);

            return AviationSeatHold::create([
                'flight_id' => $flightId,
                'cabin' => $cabin,
                'seats_count' => $seats,
                'fare_minor' => $fareMinor,
                'currency' => $currency,
                'expires_at' => $expiresAt->toDateTimeString(),
                'idempotency_key' => $idempotencyKey,
                'is_confirmed' => false,
                'is_released' => false,
            ]);
        });
    }
}
