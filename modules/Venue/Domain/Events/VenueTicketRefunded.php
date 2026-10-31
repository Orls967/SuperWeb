<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Venue\Domain\Models\EntertainmentTicket;

class VenueTicketRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly EntertainmentTicket $ticket
    ) {}
}
