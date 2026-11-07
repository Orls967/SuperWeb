<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlAlliancePartner;
use Modules\Hotel\Domain\Models\HtlAllianceTransferLog;

final class HotelAllianceReconciliationService
{
    /**
     * @return array<string, array{partner_name: string, count: int, points: int, gross_idr: int, fee_idr: int, net_payable_idr: int}>
     */
    public function generateMonthlyNettingReport(): array
    {
        $partners = HtlAlliancePartner::all();
        $report = [];

        foreach ($partners as $partner) {
            $logs = HtlAllianceTransferLog::where('partner_code', $partner->partner_code)
                ->where('status', 'SUCCESS')
                ->get();

            $report[$partner->partner_code] = [
                'partner_name' => $partner->partner_name,
                'count' => $logs->count(),
                'points' => (int) $logs->sum('points_debited'),
                'gross_idr' => (int) $logs->sum(fn ($l) => $l->points_debited * 100),
                'fee_idr' => (int) $logs->sum('transfer_fee_idr'),
                'net_payable_idr' => (int) $logs->sum('net_payable_idr'),
            ];
        }

        return $report;
    }
}
