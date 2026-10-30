<?php

declare(strict_types=1);

namespace Modules\Egy\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EgyMeterReadV1
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $readingId,
        public readonly string $meterId,
        public readonly string $propertyId,
        public readonly string $region,
        public readonly int $intervalWh,
        public readonly string $readingAt,
    ) {}
}
