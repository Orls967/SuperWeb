<?php

declare(strict_types=1);

namespace Modules\Hcm\Application\Services;

use Modules\Shared\Domain\Algorithms\Assignment\AssignmentResult;
use Modules\Shared\Domain\Algorithms\Assignment\HungarianSolver;

class HcmRosterOptimizationService
{
    public function __construct(private ?HungarianSolver $solver = null)
    {
        $this->solver = $solver ?? new HungarianSolver;
    }

    /**
     * @param  list<list<int>>  $costMatrix
     */
    public function optimizeWeeklyRoster(array $costMatrix): AssignmentResult
    {
        return $this->solver->solve($costMatrix);
    }
}
