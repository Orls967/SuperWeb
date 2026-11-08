<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use Modules\Marine\Application\Services\AquacultureInsuranceUnderwritingService;

class BindParametricCoverAction
{
    protected AquacultureInsuranceUnderwritingService $insuranceService;

    public function __construct(AquacultureInsuranceUnderwritingService $insuranceService)
    {
        $this->insuranceService = $insuranceService;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(
        int $batchId,
        int $sumInsuredMinorCents,
        int $estimatedPopulationCap,
        string $coverageType = 'PARAMETRIC_WATER_QUALITY_MORTALITY'
    ): array {
        return $this->insuranceService->bindParametricPondPolicy(
            $batchId,
            $sumInsuredMinorCents,
            $estimatedPopulationCap,
            $coverageType
        );
    }
}
