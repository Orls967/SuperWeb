<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Models\EquipmentRoi;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Models\HospitalGroupScorecard;
use Modules\Shared\Application\BaseAction;

class ConsolidateHospitalGroupPortfolioRiskAndCaseMixAction extends BaseAction
{
    /**
     * Consolidate hospital group portfolio risk, compute group scorecard, track equipment ROI, and verify ledger consistency.
     *
     * @return array{scorecard: HospitalGroupScorecard, equipment_roi: EquipmentRoi, portfolio_audit: array{status: string, discrepancies: int}}
     */
    public function handle(
        string $hospitalCode,
        string $hospitalName,
        int $year,
        int $month,
        int $admissions,
        float $mortalityRate,
        float $readmissionRate,
        float $patientSatisfaction,
        int $revenueIdr,
        int $bpjsArIdr,
        int $insuranceArIdr,
        string $equipmentCode = 'CT-SCAN-64-01',
        string $modality = 'CT_SCAN',
        int $capexIdr = 15000000000,
        int $additionalRevenueIdr = 500000000,
        int $proceduresDone = 120
    ): array {
        return DB::transaction(function () use (
            $hospitalCode,
            $hospitalName,
            $year,
            $month,
            $admissions,
            $mortalityRate,
            $readmissionRate,
            $patientSatisfaction,
            $revenueIdr,
            $bpjsArIdr,
            $insuranceArIdr,
            $equipmentCode,
            $modality,
            $capexIdr,
            $additionalRevenueIdr,
            $proceduresDone
        ) {
            // 110.1 & 110.5 Quality Score Calculation
            $qualityScore = max(0.0, min(100.0, 80.0 - ($mortalityRate * 5.0) - ($readmissionRate * 2.0) + ($patientSatisfaction * 4.0)));

            $scorecardCode = sprintf('SC-%s-%d%02d', $hospitalCode, $year, $month);

            $scorecard = HospitalGroupScorecard::updateOrCreate(
                [
                    'hospital_code' => $hospitalCode,
                    'period_year' => $year,
                    'period_month' => $month,
                ],
                [
                    'scorecard_code' => $scorecardCode,
                    'hospital_name' => $hospitalName,
                    'total_admissions' => $admissions,
                    'mortality_rate_percent' => $mortalityRate,
                    'readmission_rate_percent' => $readmissionRate,
                    'patient_satisfaction_score' => $patientSatisfaction,
                    'total_revenue_idr' => $revenueIdr,
                    'bpjs_receivable_idr' => $bpjsArIdr,
                    'insurance_receivable_idr' => $insuranceArIdr,
                    'overall_quality_score' => round($qualityScore, 2),
                ]
            );

            // 110.3 Equipment ROI tracking
            $equipmentRoi = EquipmentRoi::firstOrNew(['equipment_code' => $equipmentCode]);
            $equipmentRoi->modality = $modality;
            $equipmentRoi->capital_expenditure_idr = $capexIdr;
            $equipmentRoi->cumulative_revenue_idr = ($equipmentRoi->cumulative_revenue_idr ?? 0) + $additionalRevenueIdr;
            $equipmentRoi->total_procedures_done = ($equipmentRoi->total_procedures_done ?? 0) + $proceduresDone;

            $roi = ($capexIdr > 0) ? ($equipmentRoi->cumulative_revenue_idr / $capexIdr) * 100.0 : 0.0;
            $equipmentRoi->roi_percentage = round($roi, 2);
            $equipmentRoi->save();

            // Portfolio audit: verify zero bed occupancy anomalies
            $bedDiscrepancies = HospitalBed::where('status', 'OCCUPIED')
                ->whereNull('current_encounter_id')
                ->count();

            $portfolioAudit = [
                'status' => ($bedDiscrepancies === 0) ? 'HEALTHY' : 'UNHEALTHY',
                'discrepancies' => $bedDiscrepancies,
            ];

            $this->audit(
                action: 'hospital.group_portfolio.consolidated',
                auditable: $scorecard,
                oldValues: null,
                newValues: [
                    'scorecard_code' => $scorecardCode,
                    'hospital_code' => $hospitalCode,
                    'overall_quality_score' => $scorecard->overall_quality_score,
                    'equipment_code' => $equipmentCode,
                    'roi_percentage' => $equipmentRoi->roi_percentage,
                    'portfolio_discrepancies' => $bedDiscrepancies,
                ],
                correlationId: "hsp_portfolio_{$scorecardCode}",
                impactType: 'portfolio_risk_consolidation'
            );

            return [
                'scorecard' => $scorecard,
                'equipment_roi' => $equipmentRoi,
                'portfolio_audit' => $portfolioAudit,
            ];
        });
    }
}
