<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Venue\Domain\Models\DynamicBundlePackage;

final class DynamicBundleAllocatedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly DynamicBundlePackage $package,
        public readonly int $buyerUserId,
        public readonly int $allocatedPriceIdr,
        public readonly ?int $hotelReservationId,
        public readonly ?int $shuttleBookingId,
        public readonly \DateTimeImmutable $occurredAt,
    ) {}
}
