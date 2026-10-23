<?php

declare(strict_types=1);

namespace Modules\Supplier\Application\Actions;

use Modules\Supplier\Domain\Models\Supplier;
use Modules\Supplier\Domain\Models\SupplierRiskFlag;
use Modules\Supplier\Domain\Models\SupplierScorecard;

class EvaluateSupplierSlaPenaltyAction
{
    /**
     * Evaluate SLA compliance and calculate penalty for late delivery and defect rate.
     * Penalty rule:
     * - Late delivery: 0.1% (10 bps) per day of delay, capped at 5% (500 bps) of PO value.
     * - Defect penalty: 0.5% (50 bps) per 1% defect above 1.0% threshold.
     *
     * @return array{penalty_amount_idr: int, late_penalty_idr: int, defect_penalty_idr: int, risk_flag: ?SupplierRiskFlag}
     */
    public function execute(
        Supplier $supplier,
        int $orderValueIdr,
        int $lateDays,
        float $defectRatePct,
        string $period = '2026-10'
    ): array {
        // Late delivery penalty
        $lateBps = min(500, max(0, $lateDays * 10));
        $latePenaltyIdr = (int) intdiv($orderValueIdr * $lateBps, 10000);

        // Defect penalty
        $defectPenaltyIdr = 0;
        if ($defectRatePct > 1.0) {
            $excessDefectPct = $defectRatePct - 1.0;
            $defectBps = (int) round($excessDefectPct * 50);
            $defectPenaltyIdr = (int) intdiv($orderValueIdr * $defectBps, 10000);
        }

        $totalPenaltyIdr = $latePenaltyIdr + $defectPenaltyIdr;

        $riskFlag = null;
        if ($totalPenaltyIdr > 5000000 || $lateDays >= 14 || $defectRatePct >= 5.0) {
            $riskFlag = SupplierRiskFlag::create([
                'supplier_id' => (string) $supplier->id,
                'type' => 'score_below_threshold',
                'severity' => $defectRatePct >= 10.0 ? 'critical' : 'high',
                'message' => sprintf(
                    'Supplier exceeded SLA thresholds: %d days late, %.2f%% defect rate. Total penalty IDR %d',
                    $lateDays,
                    $defectRatePct,
                    $totalPenaltyIdr
                ),
                'meta' => [
                    'order_value_idr' => $orderValueIdr,
                    'late_days' => $lateDays,
                    'defect_rate_pct' => $defectRatePct,
                    'penalty_idr' => $totalPenaltyIdr,
                ],
                'is_open' => true,
            ]);
        }

        SupplierScorecard::updateOrCreate(
            [
                'supplier_id' => (string) $supplier->id,
                'period' => $period,
            ],
            [
                'otd_score' => max(0, 100 - ($lateDays * 5)),
                'quality_score' => max(0, (int) round(100 - ($defectRatePct * 5))),
                'price_score' => 90,
                'responsiveness_score' => 85,
                'overall_score' => max(0, 100 - ($lateDays * 3) - ((int) round($defectRatePct * 3))),
                'action' => $totalPenaltyIdr > 0 ? 'corrective' : 'none',
                'notes' => sprintf('Evaluated SLA: Penalty IDR %d', $totalPenaltyIdr),
            ]
        );

        return [
            'penalty_amount_idr' => $totalPenaltyIdr,
            'late_penalty_idr' => $latePenaltyIdr,
            'defect_penalty_idr' => $defectPenaltyIdr,
            'risk_flag' => $riskFlag,
        ];
    }
}
