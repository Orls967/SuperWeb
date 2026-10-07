<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrossEcosystemWave2Event
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $topic, // e.g. egy.demand_surge, tlx.iot_reading, med.stream_started, edu.cert_issued, ret.cashback_issued
        public array $payload,
        public string $sourceLine
    ) {}
}
