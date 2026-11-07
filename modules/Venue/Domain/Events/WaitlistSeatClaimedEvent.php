<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Venue\Domain\Models\TicketWaitlist;

final class WaitlistSeatClaimedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TicketWaitlist $waitlist,
        public readonly int $ticketId,
        public readonly int $paidPriceIdr,
        public readonly string $claimReference,
        public readonly \DateTimeImmutable $occurredAt,
    ) {}
}
