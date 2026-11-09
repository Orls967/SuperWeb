<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Events;

use Modules\Platform\Domain\Models\ChangeRequest;

final class ChangeRiskAssessedEvent
{
    public function __construct(
        public readonly ChangeRequest $changeRequest,
        public readonly float $score,
        public readonly string $tier
    ) {}
}
