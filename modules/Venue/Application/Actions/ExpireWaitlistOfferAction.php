<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Venue\Domain\Models\TicketWaitlist;
use Modules\Venue\Domain\Models\WaitlistReservation;
use Modules\Venue\Domain\StateMachines\WaitlistStateMachine;

class ExpireWaitlistOfferAction
{
    /**
     * Memproses kedaluwarsa reservasi waitlist yang melewati batas timer 15 menit.
     *
     * @return int Jumlah antrean yang berhasil dieksekusi menjadi EXPIRED
     */
    public function execute(): int
    {
        return DB::transaction(function () {
            $now = Carbon::now();

            $expiredWaitlists = TicketWaitlist::query()
                ->where('status', 'OFFERED')
                ->where('offer_expires_at', '<', $now)
                ->get();

            $count = 0;
            foreach ($expiredWaitlists as $waitlist) {
                WaitlistStateMachine::transition($waitlist, WaitlistStateMachine::STATUS_EXPIRED);
                $waitlist->save();

                WaitlistReservation::query()
                    ->where('waitlist_id', $waitlist->id)
                    ->where('status', 'ACTIVE')
                    ->update(['status' => 'EXPIRED']);

                $count++;
            }

            return $count;
        });
    }
}
