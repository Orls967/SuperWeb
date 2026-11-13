<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Services;

use Modules\ControlTower\Domain\ValueObjects\PromiseTimeBucket;

final class OrderPromiseAllocator
{
    /**
     * @param  list<PromiseTimeBucket>  $buckets
     * @return array{total_atp_qty: int, earliest_bucket: ?string}
     */
    public function allocateAcrossBuckets(array $buckets, int $requestedQty): array
    {
        $cumulative = 0;
        $earliest = null;

        foreach ($buckets as $bucket) {
            $cumulative += $bucket->netAtpQty();
            if ($earliest === null && $cumulative >= $requestedQty) {
                $earliest = $bucket->periodLabel;
            }
        }

        return [
            'total_atp_qty' => $cumulative,
            'earliest_bucket' => $earliest,
        ];
    }
}
