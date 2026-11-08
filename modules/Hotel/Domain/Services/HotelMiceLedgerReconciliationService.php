<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Hotel\Domain\Models\HtlWeddingMilestoneSchedule;
use Modules\Hotel\Domain\Models\MiceContract;

final class HotelMiceLedgerReconciliationService
{
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
    public function reconcileContractFolio(MiceContract $contract): array
    {
        return DB::transaction(function () use ($contract) {
            $milestones = HtlWeddingMilestoneSchedule::where('mice_contract_id', $contract->id)->get();

            $totalBilled = (int) $milestones->sum('amount_idr');
            $totalPaid = (int) $milestones->where('status', 'PAID')->sum('paid_amount_idr');
            $outstanding = $totalBilled - $totalPaid;

            $contractAmount = (int) ($contract->contract_total_value_idr ?: $contract->total_contract_amount_idr);
            $isBalanced = ($totalBilled === $contractAmount && $outstanding === 0);

            $idempotencyKey = 'HTL-MICE-REC-'.$contract->id.'-'.hash('sha256', (string) $contractAmount.':'.(string) $totalPaid);

            if ($isBalanced && $contract->status !== 'RECONCILED') {
                $contract->status = 'RECONCILED';
                $contract->save();
            }

            return [
                'contract_code' => $contract->event_code,
                'total_contract_amount_idr' => $contractAmount,
                'total_milestones_billed_idr' => $totalBilled,
                'total_paid_idr' => $totalPaid,
                'outstanding_balance_idr' => $outstanding,
                'is_balanced' => $isBalanced,
                'audit_idempotency_key' => $idempotencyKey,
            ];
        });
    }
}
