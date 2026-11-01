<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MiningWeighbridgeRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $ticketId,
        public readonly int $netWeightKg,
        public readonly string $siteId,
        public readonly string $stockpileId
    ) {}
}
