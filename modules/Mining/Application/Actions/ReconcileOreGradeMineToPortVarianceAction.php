<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Shared\Application\BaseAction;

class ReconcileOreGradeMineToPortVarianceAction extends BaseAction
{
    /**
     * Reconcile mine-site assay laboratory grade vs export port loading assay to identify grade bias.
     *
     * @return array{lot_code: string, mine_grade_ni_pct: float, port_grade_ni_pct: float, grade_variance_pct: float, is_dispute_flagged: bool, status: string}
     */
    public function handle(
        string $lotCode,
        float $mineGradeNiPct,
        float $portGradeNiPct,
        float $lotTonnage
    ): array {
        $gradeVariancePct = round(abs($portGradeNiPct - $mineGradeNiPct), 3);

        // Standard commercial assay tolerance threshold is 0.10% Ni
        $isDisputeFlagged = ($gradeVariancePct > 0.10);

        $status = match (true) {
            $gradeVariancePct > 0.20 => 'CRITICAL_ASSAY_BIAS_DISPUTE',
            $gradeVariancePct > 0.10 => 'ELEVATED_VARIANCE_RETEST_REQUIRED',
            default => 'COMMERCIALLY_RECONCILED_TOLERANCE_OK',
        };

        $this->audit(
            action: 'mining.assay.reconciled',
            auditable: null,
            oldValues: null,
            newValues: [
                'lot_code' => $lotCode,
                'mine_grade' => $mineGradeNiPct,
                'port_grade' => $portGradeNiPct,
                'variance' => $gradeVariancePct,
                'status' => $status,
            ],
            correlationId: "min_assay_{$lotCode}_".date('YmdHis'),
            impactType: 'mine_to_port_grade_reconciliation'
        );

        return [
            'lot_code' => $lotCode,
            'mine_grade_ni_pct' => $mineGradeNiPct,
            'port_grade_ni_pct' => $portGradeNiPct,
            'grade_variance_pct' => $gradeVariancePct,
            'is_dispute_flagged' => $isDisputeFlagged,
            'status' => $status,
        ];
    }
}
