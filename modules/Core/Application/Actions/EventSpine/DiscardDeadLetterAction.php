<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\EventSpine;

use Modules\Core\Application\Services\DeadLetterQueueService;

class DiscardDeadLetterAction
{
    public function __construct(
        protected DeadLetterQueueService $dlqService
    ) {}

    /**
     * @param  array<int, int>  $deliveryIds
     * @return array{success_count: int, pending_approval: bool, task_id: int|null}
     */
    public function execute(array $deliveryIds, string $reason, ?int $actorId = null): array
    {
        return $this->dlqService->discardBatch($deliveryIds, $reason, $actorId);
    }
}
