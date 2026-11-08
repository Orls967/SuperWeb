<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use Modules\Marine\Application\Services\AquacultureBatchLifecycleService;
use Modules\Marine\Domain\Models\AquacultureBatch;

class StockPondBatchAction
{
    protected AquacultureBatchLifecycleService $lifecycleService;

    public function __construct(AquacultureBatchLifecycleService $lifecycleService)
    {
        $this->lifecycleService = $lifecycleService;
    }

    /**
     * @param  array<string, mixed>  $stockingData
     */
    public function execute(int $hatcheryId, array $stockingData): AquacultureBatch
    {
        return $this->lifecycleService->stockPondBatch($hatcheryId, $stockingData);
    }
}
