<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Venue\Domain\Models\TicketResale;

final class TicketResaleListedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TicketResale $resale,
        public readonly int $faceValueIdr,
        public readonly int $listingPriceIdr,
        public readonly \DateTimeImmutable $occurredAt,
    ) {}
}
