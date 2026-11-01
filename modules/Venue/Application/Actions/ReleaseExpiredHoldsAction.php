<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Venue\Domain\Models\InventoryCounter;
use Modules\Venue\Domain\Models\QuotaHold;
use Modules\Venue\Domain\Models\SeatClaim;

class ReleaseExpiredHoldsAction
{
    public function __construct(
        private readonly ClockInterface $clock
    ) {}

    public function handle(): int
    {
        $now = $this->clock->now();

        return DB::transaction(function () use ($now) {
            $released = 0;

            // Release expired GA quota holds
            $expiredQuotaHolds = QuotaHold::where('status', 'HELD')
                ->where('held_until', '<', $now)
                ->lockForUpdate()
                ->get();

            foreach ($expiredQuotaHolds as $hold) {
                $counter = InventoryCounter::where('event_id', $hold->event_id)
                    ->where('tier_id', $hold->tier_id)
                    ->lockForUpdate()
                    ->first();

                if ($counter) {
                    $counter->held_count = max(0, $counter->held_count - $hold->quantity);
                    $counter->save();
                }

                $hold->update(['status' => 'EXPIRED']);
                $released++;
            }

            // Release expired seat claims
            $expiredSeats = SeatClaim::where('status', 'HELD')
                ->where('held_until', '<', $now)
                ->lockForUpdate()
                ->get();

            foreach ($expiredSeats as $seat) {
                $counter = InventoryCounter::where('event_id', $seat->event_id)
                    ->where('tier_id', $seat->tier_id)
                    ->lockForUpdate()
                    ->first();

                if ($counter) {
                    $counter->held_count = max(0, $counter->held_count - 1);
                    $counter->save();
                }

                $seat->update([
                    'status' => 'AVAILABLE',
                    'buyer_user_id' => null,
                    'held_until' => null,
                    'hold_token' => null,
                    'idempotency_key' => null,
                ]);

                $released++;
            }

            return $released;
        });
    }
}
