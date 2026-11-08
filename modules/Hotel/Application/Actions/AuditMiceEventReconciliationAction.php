<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Services\HotelMiceLedgerReconciliationService;

final class AuditMiceEventReconciliationAction
{
    public function __construct(
        private readonly HotelMiceLedgerReconciliationService $reconciliationService
    ) {}

    /**
     * @return array{
     *     contract_code: string,
     *     total_contract_amount_idr: int,
     *     total_milestones_billed_idr: int,
     *     total_paid_idr: int,
     *     outstanding_balance_idr: int,
     *     is_balanced: bool,
     *     audit_idempotency_key: string
     * }
     */
    public function execute(MiceContract $contract): array
    {
        return $this->reconciliationService->reconcileContractFolio($contract);
    }
}
