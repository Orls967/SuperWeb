<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\ClockInterface;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\InventoryCounter;
use Modules\Venue\Domain\Models\SeatClaim;

class HoldNumberedSeatsAction
{
    public function __construct(
        private readonly ClockInterface $clock
    ) {}

    /**
     * @param  int[]  $seatIds
     * @return array{success: bool, hold_token: string, seats: array<int, SeatClaim>, held_until: CarbonImmutable}
     */
    public function handle(
        int $eventId,
        int $tierId,
        array $seatIds,
        int $buyerUserId,
        string $idempotencyKey
    ): array {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency key wajib diisi (U-4)');
        }

        $event = EntertainmentEvent::findOrFail($eventId);
        if ($event->status === 'CLOSED') {
            throw new \RuntimeException('Event telah ditutup dan tidak lagi menerima pembelian tiket');
        }

        $quantity = count($seatIds);
        if ($quantity > 6 || $quantity <= 0) {
            throw new \InvalidArgumentException('Batas pembelian maksimum adalah 6 tiket per akun');
        }

        // Idempotency check
        $existingClaims = SeatClaim::where('event_id', $eventId)
            ->where('idempotency_key', $idempotencyKey)
            ->get();

        if ($existingClaims->isNotEmpty()) {
            return [
                'success' => true,
                'hold_token' => (string) $existingClaims->first()->hold_token,
                'seats' => $existingClaims->all(),
                'held_until' => $existingClaims->first()->held_until,
            ];
        }

        $existingTicketCount = EntertainmentTicket::where('event_id', $eventId)
            ->where('buyer_user_id', $buyerUserId)
            ->where('status', '!=', 'REFUNDED')
            ->count();

        $activeHeldSeats = SeatClaim::where('event_id', $eventId)
            ->where('buyer_user_id', $buyerUserId)
            ->where('status', 'HELD')
            ->where('held_until', '>=', $this->clock->now())
            ->count();

        if ($existingTicketCount + $activeHeldSeats + $quantity > 6) {
            throw new \InvalidArgumentException('Batas pembelian maksimum adalah 6 tiket per akun');
        }

        return DB::transaction(function () use ($eventId, $tierId, $seatIds, $buyerUserId, $idempotencyKey, $quantity) {
            $seats = SeatClaim::where('event_id', $eventId)
                ->where('tier_id', $tierId)
                ->whereIn('id', $seatIds)
                ->lockForUpdate()
                ->get();

            if ($seats->count() !== $quantity) {
                throw new \RuntimeException('Beberapa kursi yang dipilih tidak tersedia');
            }

            foreach ($seats as $seat) {
                if ($seat->status !== 'AVAILABLE') {
                    throw new \RuntimeException('Beberapa kursi yang dipilih tidak tersedia');
                }
            }

            $counter = InventoryCounter::where('event_id', $eventId)
                ->where('tier_id', $tierId)
                ->lockForUpdate()
                ->first();

            if ($counter) {
                $counter->held_count += $quantity;
                $counter->save();
            }

            $holdToken = 'HOLD-SEAT-'.Str::uuid();
            $heldUntil = $this->clock->now()->addMinutes(10);

            foreach ($seats as $seat) {
                $seat->update([
                    'status' => 'HELD',
                    'buyer_user_id' => $buyerUserId,
                    'held_until' => $heldUntil,
                    'hold_token' => $holdToken,
                    'idempotency_key' => $idempotencyKey,
                ]);
            }

            return [
                'success' => true,
                'hold_token' => $holdToken,
                'seats' => $seats->all(),
                'held_until' => $heldUntil,
            ];
        });
    }
}
