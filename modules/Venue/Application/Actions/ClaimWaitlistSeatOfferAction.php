<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Venue\Domain\Models\TicketWaitlist;
use Modules\Venue\Domain\Models\WaitlistReservation;
use Modules\Venue\Domain\StateMachines\WaitlistStateMachine;
use RuntimeException;

class ClaimWaitlistSeatOfferAction
{
    public function execute(TicketWaitlist $waitlist): TicketWaitlist
    {
        return DB::transaction(function () use ($waitlist) {
            if ($waitlist->offer_expires_at !== null && $waitlist->offer_expires_at->isPast()) {
                WaitlistStateMachine::transition($waitlist, WaitlistStateMachine::STATUS_EXPIRED);
                $waitlist->save();

                throw new RuntimeException('Batas waktu 15 menit penawaran tiket telah kedaluwarsa');
            }

            WaitlistStateMachine::transition($waitlist, WaitlistStateMachine::STATUS_CLAIMED);
            $waitlist->save();

            WaitlistReservation::query()
                ->where('waitlist_id', $waitlist->id)
                ->where('status', 'ACTIVE')
                ->update([
                    'status' => 'CLAIMED',
                    'claimed_at' => Carbon::now(),
                ]);

            return $waitlist->fresh();
        });
    }
}
