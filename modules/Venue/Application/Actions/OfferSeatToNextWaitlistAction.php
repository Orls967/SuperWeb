<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Venue\Domain\Models\TicketWaitlist;
use Modules\Venue\Domain\Models\WaitlistReservation;
use Modules\Venue\Domain\StateMachines\WaitlistStateMachine;

class OfferSeatToNextWaitlistAction
{
    public function execute(TicketWaitlist $waitlist, int $expiryMinutes = 15): TicketWaitlist
    {
        return DB::transaction(function () use ($waitlist, $expiryMinutes) {
            $now = Carbon::now();
            $expiresAt = $now->copy()->addMinutes($expiryMinutes);

            WaitlistStateMachine::transition($waitlist, WaitlistStateMachine::STATUS_OFFERED);

            $waitlist->update([
                'offer_expires_at' => $expiresAt,
            ]);

            $token = 'WTL-RES-'.strtoupper(substr(hash('sha256', (string) $waitlist->id.'-'.uniqid('', true)), 0, 16));

            WaitlistReservation::query()->create([
                'waitlist_id' => $waitlist->id,
                'token' => $token,
                'offered_at' => $now,
                'expires_at' => $expiresAt,
                'status' => 'ACTIVE',
            ]);

            return $waitlist->fresh();
        });
    }
}
