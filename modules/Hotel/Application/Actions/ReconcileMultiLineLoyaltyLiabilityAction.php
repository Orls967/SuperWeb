<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Banking\Application\Services\LedgerService;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;

final class ReconcileMultiLineLoyaltyLiabilityAction
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{total_members: int, total_active_points: int, theoretical_liability_idr: int, ledger_balance_idr: int, discrepancy_idr: int, is_clean: bool}
     */
    public function execute(): array
    {
        $totalMembers = HtlTravelPass::count();
        $totalActivePoints = (int) HtlTravelPass::where('is_active', true)->sum('pts_balance');
        $theoreticalLiabilityIdr = $totalActivePoints * 100;

        $batchRemainingPoints = (int) HtlTravelPassPointBatch::whereIn('state', ['ACTIVE', 'PARTIALLY_REDEEMED'])->sum('remaining_pts');
        $batchLiabilityIdr = $batchRemainingPoints * 100;

        $discrepancy = abs($theoreticalLiabilityIdr - $batchLiabilityIdr);

        return [
            'total_members' => $totalMembers,
            'total_active_points' => $totalActivePoints,
            'theoretical_liability_idr' => $theoreticalLiabilityIdr,
            'ledger_balance_idr' => $batchLiabilityIdr,
            'discrepancy_idr' => $discrepancy,
            'is_clean' => $discrepancy === 0,
        ];
    }
}
