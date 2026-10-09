<?php

namespace Modules\Hospital\Application\Services;

use Modules\Hospital\Domain\Models\EquipmentRoi;
use Modules\Hospital\Domain\Models\HospitalGroupScorecard;

class HospitalGroupAnalyticsService
{
    /**
     * 110.1 & 110.5 Compute Monthly Group Health Scorecard
     */
    public function computeGroupScorecard(
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
        int $insuranceArIdr
    ): HospitalGroupScorecard {
        // Overall quality score: 100 - (mortality * 5) - (readmission * 2) + (satisfaction * 4)
        $qualityScore = max(0, min(100, 80 - ($mortalityRate * 5) - ($readmissionRate * 2) + ($patientSatisfaction * 4)));

        return HospitalGroupScorecard::updateOrCreate(
            [
                'hospital_code' => $hospitalCode,
                'period_year' => $year,
                'period_month' => $month,
            ],
            [
                'scorecard_code' => "SC-{$hospitalCode}-{$year}".sprintf('%02d', $month),
                'hospital_name' => $hospitalName,
                'total_admissions' => $admissions,
                'mortality_rate_percent' => $mortalityRate,
                'readmission_rate_percent' => $readmissionRate,
                'patient_satisfaction_score' => $patientSatisfaction,
                'total_revenue_idr' => $revenueIdr,
                'bpjs_receivable_idr' => $bpjsArIdr,
                'insurance_receivable_idr' => $insuranceArIdr,
                'overall_quality_score' => $qualityScore,
            ]
        );
    }

    /**
     * 110.3 Compute & Update Equipment ROI
     */
    public function trackEquipmentRoi(
        string $equipmentCode,
        string $modality,
        int $capexIdr,
        int $additionalRevenueIdr,
        int $proceduresDone
    ): EquipmentRoi {
        $record = EquipmentRoi::firstOrNew(['equipment_code' => $equipmentCode]);

        $record->modality = $modality;
        $record->capital_expenditure_idr = $capexIdr;
        $record->cumulative_revenue_idr = ($record->cumulative_revenue_idr ?? 0) + $additionalRevenueIdr;
        $record->total_procedures_done = ($record->total_procedures_done ?? 0) + $proceduresDone;

        $roi = ($capexIdr > 0) ? ($record->cumulative_revenue_idr / $capexIdr) * 100 : 0;
        $record->roi_percentage = round($roi, 2);
        $record->save();

        return $record;
    }
}
