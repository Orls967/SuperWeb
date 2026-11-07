<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

use Carbon\CarbonImmutable;

final class GuestHighChurnRiskDetectedEvent
{
    public readonly string $occurredAt;

    public function __construct(
        public readonly string $guestId,
        public readonly float $churnProbability,
        public readonly string $riskLevel,
        public readonly string $recommendedIntervention
    ) {
        $this->occurredAt = CarbonImmutable::now()->toIso8601String();
    }
}
