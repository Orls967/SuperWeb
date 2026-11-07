<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Venue\Domain\Events\WaitlistSeatOfferedEvent;

final class NotifyNextWaitlistUserListener
{
    public function handle(WaitlistSeatOfferedEvent $event): void
    {
        Log::info('Waitlist seat reservation offered to candidate', [
            'waitlist_id' => $event->waitlist->id,
            'user_id' => $event->waitlist->user_id,
            'event_id' => $event->waitlist->event_id,
            'ticket_id' => $event->ticketId,
            'reservation_token' => $event->reservationToken,
            'expires_at' => $event->expiresAt->format(\DateTimeInterface::ATOM),
        ]);
    }
}
