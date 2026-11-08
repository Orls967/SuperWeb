<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Queries;

use Modules\Marine\Domain\Models\LaboratoryAssay;

/**
 * Query service analitik kepatuhan karantina internasional SPS dan monitoring residu (Fase 171).
 */
class ExportComplianceAnalyticsQuery
{
    /**
     * Hitung metrik kepatuhan pengujian laboratorium seluruh lot ekspor.
     *
     * @return array{
     *     total_assays_conducted: int,
     *     passed_export_assays: int,
     *     quarantine_rejected_assays: int,
     *     compliance_rate_percentage: float,
     *     mean_histamine_ppm: float,
     *     zero_tolerance_chloramphenicol_breaches: int,
     *     zero_tolerance_salmonella_detections: int
     * }
     */
    public function getComplianceMetrics(): array
    {
        $assays = LaboratoryAssay::all();
        $total = $assays->count();

        if ($total === 0) {
            return [
                'total_assays_conducted' => 0,
                'passed_export_assays' => 0,
                'quarantine_rejected_assays' => 0,
                'compliance_rate_percentage' => 100.0,
                'mean_histamine_ppm' => 0.0,
                'zero_tolerance_chloramphenicol_breaches' => 0,
                'zero_tolerance_salmonella_detections' => 0,
            ];
        }

        $passed = $assays->where('overall_compliance_verdict', 'PASSED_FOR_EXPORT')->count();
        $rejected = $total - $passed;
        $complianceRate = round(($passed / $total) * 100.0, 2);

        $meanHistamine = (float) $assays->avg('histamine_ppm');
        $chloramphenicolBreaches = $assays->where('chloramphenicol_ppb', '>', 0.3)->count();
        $salmonellaBreaches = $assays->where('microbiology_salmonella_cfu_25g', '>', 0.0)->count();

        return [
            'total_assays_conducted' => $total,
            'passed_export_assays' => $passed,
            'quarantine_rejected_assays' => $rejected,
            'compliance_rate_percentage' => $complianceRate,
            'mean_histamine_ppm' => round($meanHistamine, 2),
            'zero_tolerance_chloramphenicol_breaches' => $chloramphenicolBreaches,
            'zero_tolerance_salmonella_detections' => $salmonellaBreaches,
        ];
    }
}
