<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Venue\Domain\Models\TicketResale;

final class TicketResaleSettledEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TicketResale $resale,
        public readonly int $buyerUserId,
        public readonly int $settledPriceIdr,
        public readonly int $pbjtTaxIdr,
        public readonly int $platformFeeIdr,
        public readonly int $promoterRoyaltyIdr,
        public readonly int $sellerPayoutIdr,
        public readonly string $rotatedTicketHash,
        public readonly \DateTimeImmutable $occurredAt,
    ) {}
}
