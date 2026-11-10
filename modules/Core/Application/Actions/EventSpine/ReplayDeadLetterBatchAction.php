<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\EventSpine;

use Modules\Core\Application\Services\DeadLetterQueueService;

class ReplayDeadLetterBatchAction
{
    public function __construct(
        protected DeadLetterQueueService $dlqService
    ) {}

    /**
     * @param  array<int, int>  $deliveryIds
     * @return array{success_count: int, failed_count: int}
     */
    public function execute(array $deliveryIds, string $reason, ?int $actorId = null): array
    {
        return $this->dlqService->replayBatch($deliveryIds, $reason, $actorId);
    }
}
