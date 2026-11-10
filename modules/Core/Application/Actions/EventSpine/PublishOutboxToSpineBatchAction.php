<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\EventSpine;

use Modules\Core\Application\Services\OutboxToSpineBridgeService;

class PublishOutboxToSpineBatchAction
{
    public function __construct(
        protected OutboxToSpineBridgeService $bridgeService
    ) {}

    /**
     * @return array{dispatched_count: int, failed_count: int}
     */
    public function execute(int $batchSize = 100): array
    {
        return $this->bridgeService->syncPendingOutboxToSpine($batchSize);
    }
}

