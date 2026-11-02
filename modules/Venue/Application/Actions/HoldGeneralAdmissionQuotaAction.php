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
use Modules\Venue\Domain\Models\QuotaHold;

class HoldGeneralAdmissionQuotaAction
{
    public function __construct(
        private readonly ClockInterface $clock
    ) {}

    /**
     * @return array{success: bool, hold_token: string, quantity: int, held_until: CarbonImmutable}
     */
    public function handle(
        int $eventId,
        int $tierId,
        int $quantity,
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

        $counterCheck = InventoryCounter::where('event_id', $eventId)
            ->where('tier_id', $tierId)
            ->first();

        if ($counterCheck && ($counterCheck->total_quota - $counterCheck->held_count - $counterCheck->sold_count < $quantity)) {
            throw new \RuntimeException('Kuota tiket tidak mencukupi');
        }

        if ($quantity > 6 || $quantity <= 0) {
            throw new \InvalidArgumentException('Batas pembelian maksimum adalah 6 tiket per akun');
        }

        // Idempotensi check
        $existing = QuotaHold::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if (
                $existing->event_id !== $eventId
                || $existing->tier_id !== $tierId
                || $existing->quantity !== $quantity
                || $existing->buyer_user_id !== $buyerUserId
            ) {
                throw new \RuntimeException('Idempotency conflict: payload berbeda untuk key yang sama');
            }

            return [
                'success' => true,
                'hold_token' => $existing->hold_token,
                'quantity' => $existing->quantity,
                'held_until' => $existing->held_until,
            ];
        }

        $existingTicketCount = EntertainmentTicket::where('event_id', $eventId)
            ->where('buyer_user_id', $buyerUserId)
            ->where('status', '!=', 'REFUNDED')
            ->count();

        $activeHeldQuota = (int) QuotaHold::where('event_id', $eventId)
            ->where('buyer_user_id', $buyerUserId)
            ->where('status', 'HELD')
            ->where('held_until', '>=', $this->clock->now())
            ->where('idempotency_key', '!=', $idempotencyKey)
            ->sum('quantity');

        if ($existingTicketCount + $activeHeldQuota + $quantity > 6) {
            throw new \InvalidArgumentException('Batas pembelian maksimum adalah 6 tiket per akun');
        }

        return DB::transaction(function () use ($eventId, $tierId, $quantity, $buyerUserId, $idempotencyKey) {
            $counter = InventoryCounter::where('event_id', $eventId)
                ->where('tier_id', $tierId)
                ->lockForUpdate()
                ->firstOrFail();

            $available = $counter->total_quota - $counter->held_count - $counter->sold_count;
            if ($available < $quantity) {
                throw new \RuntimeException('Kuota tiket tidak mencukupi');
            }

            $counter->held_count += $quantity;
            $counter->save();

            $holdToken = 'HOLD-GA-'.Str::uuid();
            $heldUntil = $this->clock->now()->addMinutes(10);

            $hold = QuotaHold::create([
                'event_id' => $eventId,
                'tier_id' => $tierId,
                'buyer_user_id' => $buyerUserId,
                'quantity' => $quantity,
                'hold_token' => $holdToken,
                'idempotency_key' => $idempotencyKey,
                'status' => 'HELD',
                'held_until' => $heldUntil,
            ]);

            return [
                'success' => true,
                'hold_token' => $holdToken,
                'quantity' => $quantity,
                'held_until' => $heldUntil,
            ];
        });
    }
}
