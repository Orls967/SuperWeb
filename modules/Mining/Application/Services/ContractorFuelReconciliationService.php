<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;

/**
 * Laporan BBM per kontraktor (D93-5): BBM aktual vs baseline M3 dan posisi dana hold, dihitung dari fakta kasus + hold.
 */
final class ContractorFuelReconciliationService
{
    /**
     * @return array{
     *     contractor: string,
     *     actual_ml: int,
     *     baseline_ml: int,
     *     excess_bps: int,
     *     held_idr: int,
     *     deducted_idr: int,
     *     released_idr: int,
     *     cases: int
     * }
     */
    public function summarize(string $contractorRef, string $from, string $to): array
    {
        $cases = MiningFuelAnomalyCase::query()
            ->with('paymentHolds')
            ->where('contractor_party_ref', $contractorRef)
            ->whereDate('shift_date', '>=', $from)
            ->whereDate('shift_date', '<=', $to)
            ->orderBy('shift_date')
            ->get();

        $actual = (int) $cases->sum('actual_fuel_ml');
        $baseline = (int) $cases->sum('baseline_fuel_ml');
        $holds = $cases->flatMap->paymentHolds;

        return [
            'contractor' => $contractorRef,
            'actual_ml' => $actual,
            'baseline_ml' => $baseline,
            'excess_bps' => $baseline > 0 ? intdiv(($actual - $baseline) * 10000, $baseline) : 0,
            'held_idr' => (int) $holds->where('status', 'HELD')->sum('hold_amount_idr'),
            'deducted_idr' => (int) $holds->sum('deducted_amount_idr'),
            'released_idr' => (int) $holds->sum('released_amount_idr'),
            'cases' => $cases->count(),
        ];
    }
}
