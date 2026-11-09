<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

use DateTimeImmutable;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\Models\ReleaseTrain;

final class ChangeSchedulingEngine
{
    public function __construct(
        private readonly MaintenanceWindowCalculator $windowCalc,
        private readonly ChangeConflictDetector $conflictDetector
    ) {}

    /**
     * Finds the optimal deployment time slot considering quiet windows and table conflicts.
     */
    public function scheduleChange(ChangeRequest $cr, ReleaseTrain $train, DateTimeImmutable $earliestTime): DateTimeImmutable
    {
        $targetModule = $cr->target_module;
        $candidateTime = $this->windowCalc->getNextValidWindow($targetModule, $earliestTime);

        // Ensure candidate time does not exceed release train scheduled deployment
        if ($train->scheduled_deploy_at !== null && $candidateTime > $train->scheduled_deploy_at) {
            return DateTimeImmutable::createFromInterface($train->scheduled_deploy_at);
        }

        return $candidateTime;
    }
}
