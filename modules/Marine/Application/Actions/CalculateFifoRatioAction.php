<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use Modules\Marine\Application\Services\SustainableFeedFormulationService;

class CalculateFifoRatioAction
{
    protected SustainableFeedFormulationService $feedService;

    public function __construct(SustainableFeedFormulationService $feedService)
    {
        $this->feedService = $feedService;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(
        int $feedInventoryId,
        float $fishmealInclusionPct,
        float $fishoilInclusionPct,
        float $batchEconomicFcr = 1.25
    ): array {
        return $this->feedService->calculateFifoRatio(
            $feedInventoryId,
            $fishmealInclusionPct,
            $fishoilInclusionPct,
            $batchEconomicFcr
        );
    }
}
