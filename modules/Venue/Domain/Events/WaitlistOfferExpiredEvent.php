<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Venue\Domain\Models\TicketWaitlist;

final class WaitlistOfferExpiredEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TicketWaitlist $waitlist,
        public readonly int $ticketId,
        public readonly string $reservationToken,
        public readonly \DateTimeImmutable $occurredAt,
    ) {}
}
