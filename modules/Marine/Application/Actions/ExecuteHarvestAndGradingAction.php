<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use Modules\Marine\Application\Services\HarvestProcessingColdChainService;

class ExecuteHarvestAndGradingAction
{
    protected HarvestProcessingColdChainService $harvestService;

    public function __construct(HarvestProcessingColdChainService $harvestService)
    {
        $this->harvestService = $harvestService;
    }

    /**
     * @param  array<string, mixed>  $harvestData
     * @param  array<string, mixed>  $lotData
     * @param  array<string, mixed>  $packagingData
     */
    public function execute(
        int $batchId,
        array $harvestData,
        array $lotData,
        array $packagingData
    ): array {
        $harvest = $this->harvestService->recordHarvest($batchId, $harvestData);
        $exportLot = $this->harvestService->processAndPackExportLot($harvest->id, $lotData, $packagingData);

        return [
            'harvest_operation' => $harvest,
            'export_lot' => $exportLot,
        ];
    }
}
