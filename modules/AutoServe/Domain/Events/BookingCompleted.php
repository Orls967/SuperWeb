<?php

declare(strict_types=1);

namespace Modules\AutoServe\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\AutoServe\Domain\Models\Booking;

class BookingCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?int $odometerKm = null,
        public ?int $actorId = null
    ) {}
}
