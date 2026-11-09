<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Telemedicine;

use Modules\Hospital\Domain\Services\ChronicRefillPredictiveScheduler;

class ExecuteBatchRefillRunAction
{
    public function __construct(
        protected ChronicRefillPredictiveScheduler $scheduler
    ) {}

    /**
     * @return array{
     *     processed_count: int,
     *     orders_created: array<int, array{
     *         subscription_code: string,
     *         order_code: string,
     *         drug_name: string,
     *         total_idr: int,
     *         next_refill_date: string
     *     }>
     * }
     */
    public function execute(int $advanceNoticeDays = 3): array
    {
        return $this->scheduler->processBatchRefills($advanceNoticeDays);
    }
}
