<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Modules\Mining\Domain\Models\MiningContractorPaymentHold;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningFuelDispenseRecord;

/**
 * Layanan Rekonsiliasi Bahan Bakar Multi-Kontraktor Tambang.
 * Membandingkan kuota BBM vs jam operasi, deviasi baseline M3, dan status escrow penahanan dana (hold).
 */
final class ContractorFuelReconciliationService
{
    /**
     * @param string $contractorPartyRef
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return array{
     *     contractor_ref: string,
     *     period: array{start: string, end: string},
     *     total_fuel_consumed_liters: float,
     *     total_baseline_liters: float,
     *     net_excess_liters: float,
     *     total_held_amount_idr: int,
     *     total_deducted_idr: int,
     *     total_released_idr: int,
     *     cases: list<array{
     *         case_code: string,
     *         shift_date: string,
     *         equipment_code: string,
     *         actual_l: float,
     *         baseline_l: float,
     *         deviation_pct: float,
     *         held_idr: int,
     *         status: string
     *     }>,
     *     compliance_status: string
     * }
     */
    public function reconcileContractor(string $contractorPartyRef, string $startDate, string $endDate): array
    {
        $cases = MiningFuelAnomalyCase::where('contractor_party_ref', $contractorPartyRef)
            ->whereBetween('shift_date', [$startDate, $endDate])
            ->with(['equipment', 'paymentHolds'])
            ->get();

        $totalActualMl = 0;
        $totalBaseMl = 0;
        $totalHeldIdr = 0;
        $totalDeductedIdr = 0;
        $totalReleasedIdr = 0;

        $caseSummaries = [];

        foreach ($cases as $c) {
            $totalActualMl += $c->actual_fuel_ml;
            $totalBaseMl += $c->baseline_fuel_ml;

            foreach ($c->paymentHolds as $h) {
                $totalHeldIdr += $h->hold_amount_idr;
                $totalDeductedIdr += $h->deducted_amount_idr;
                $totalReleasedIdr += $h->released_amount_idr;
            }

            $caseSummaries[] = [
                'case_code' => $c->case_number,
                'shift_date' => $c->shift_date->format('Y-m-d'),
                'equipment_code' => $c->equipment?->equipment_code ?? $c->equipment?->code ?? '-',
                'actual_l' => round($c->actual_fuel_ml / 1000.0, 1),
                'baseline_l' => round($c->baseline_fuel_ml / 1000.0, 1),
                'deviation_pct' => round((float) ($c->excess_bps / 100.0), 1),
                'held_idr' => $c->held_amount_idr,
                'status' => $c->status->value,
            ];
        }

        $totalConsumedL = round($totalActualMl / 1000.0, 1);
        $totalBaseL = round($totalBaseMl / 1000.0, 1);
        $netExcessL = round($totalConsumedL - $totalBaseL, 1);

        $compliance = ($netExcessL <= 0 || ($totalBaseL > 0 && ($netExcessL / $totalBaseL) <= 0.05))
            ? 'COMPLIANT'
            : 'UNDER_INVESTIGATION';

        return [
            'contractor_ref' => $contractorPartyRef,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'total_fuel_consumed_liters' => $totalConsumedL,
            'total_baseline_liters' => $totalBaseL,
            'net_excess_liters' => $netExcessL,
            'total_held_amount_idr' => $totalHeldIdr,
            'total_deducted_idr' => $totalDeductedIdr,
            'total_released_idr' => $totalReleasedIdr,
            'cases' => $caseSummaries,
            'compliance_status' => $compliance,
        ];
    }
}
