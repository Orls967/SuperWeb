<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BatteryDegradedThresholdReached
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $vehicleId,
        public readonly float $currentSohPct,
        public readonly string $recommendation = 'trade_in_autodex_hodl_to_drive',
        public readonly array $metadata = []
    ) {}
}
