<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Events;

use Modules\Hospital\Application\DTOs\VitalsTelemetryDTO;

final class VitalSignDeterioratedEvent
{
    /**
     * @param  list<string>  $decompensationFlags
     */
    public function __construct(
        public readonly VitalsTelemetryDTO $telemetry,
        public readonly int $news2Score,
        public readonly string $urgencyTier,
        public readonly array $decompensationFlags = []
    ) {}
}
