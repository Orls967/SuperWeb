<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Events;

use Modules\Platform\Domain\Models\ServiceSlo;

final class SloBurnRateExceededEvent
{
    public function __construct(
        public readonly ServiceSlo $slo,
        public readonly float $currentBurnRate,
        public readonly string $urgency,
        public readonly string $occurredAt
    ) {
    }

    public static function create(ServiceSlo $slo, float $burnRate, string $urgency): self
    {
        return new self($slo, $burnRate, $urgency, date('c'));
    }
}

