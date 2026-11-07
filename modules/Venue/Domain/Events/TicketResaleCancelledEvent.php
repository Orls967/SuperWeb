<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Venue\Domain\Models\TicketResale;

final class TicketResaleCancelledEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TicketResale $resale,
        public readonly string $reason,
        public readonly \DateTimeImmutable $occurredAt,
    ) {}
}
