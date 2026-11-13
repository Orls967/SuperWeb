<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Application\Actions\EvaluateOrderPromiseAction;
use Modules\ControlTower\Domain\Models\OrderPromise;
use Modules\ControlTower\Domain\Services\OrderPromiseAllocator;
use Modules\ControlTower\Domain\ValueObjects\PromiseTimeBucket;

final class OrderPromiseOrchestratorService
{
    public function __construct(
        private readonly EvaluateOrderPromiseAction $evaluateAction,
        private readonly OrderPromiseAllocator $allocator,
    ) {}

    public function promiseOrder(
        string $orderRef,
        string $itemCode,
        int $requestedQty,
        int $bomComponentAvailableQty = 0,
        int $plantFreeCapacityUnits = 0,
    ): OrderPromise {
        return $this->evaluateAction->execute(
            orderRef: $orderRef,
            itemCode: $itemCode,
            requestedQty: $requestedQty,
            bomComponentAvailableQty: $bomComponentAvailableQty,
            plantFreeCapacityUnits: $plantFreeCapacityUnits,
        );
    }

    /**
     * @param  list<PromiseTimeBucket>  $buckets
     * @return array{total_atp_qty: int, earliest_bucket: ?string}
     */
    public function checkTimePhasedAvailability(array $buckets, int $requestedQty): array
    {
        return $this->allocator->allocateAcrossBuckets($buckets, $requestedQty);
    }
}
