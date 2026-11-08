<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use Modules\Marine\Domain\Algorithms\ExportAssayToleranceChecker;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\Models\LaboratoryAssay;

/**
 * Service pengujian laboratorium dan kepatuhan karantina internasional SPS (Fase 171).
 */
class ExportAssayAndComplianceService
{
    protected ExportAssayToleranceChecker $toleranceChecker;

    public function __construct(ExportAssayToleranceChecker $toleranceChecker)
    {
        $this->toleranceChecker = $toleranceChecker;
    }

    /**
     * Catat hasil uji laboratorium dan evaluasi izin ekspor SPS.
     *
     * @param  array<string, mixed>  $testResults
     */
    public function recordAndEvaluateAssay(
        int $exportLotId,
        string $destinationMarket,
        array $testResults,
        string $accreditedLabName = 'Balai Uji Standar Karantina Ikan (BUSKIPM)'
    ): LaboratoryAssay {
        $exportLot = AquacultureExportLot::findOrFail($exportLotId);

        $evaluation = $this->toleranceChecker->evaluateAssay($destinationMarket, $testResults);

        $assayNumber = 'ASSAY-'.strtoupper(substr(hash('sha256', $exportLot->lot_number.microtime()), 0, 10));

        $assay = LaboratoryAssay::create([
            'export_lot_id' => $exportLot->id,
            'assay_number' => $assayNumber,
            'assay_type' => (string) ($testResults['assay_type'] ?? 'FULL_SPS_EXPORT_SCREEN'),
            'tested_at' => (string) ($testResults['tested_at'] ?? now()->toDateTimeString()),
            'accredited_laboratory' => $accreditedLabName,
            'chloramphenicol_ppb' => (float) ($testResults['chloramphenicol_ppb'] ?? 0.0),
            'nitrofurans_detected' => (bool) ($testResults['nitrofurans_detected'] ?? false),
            'histamine_ppm' => (float) ($testResults['histamine_ppm'] ?? 5.0),
            'cadmium_ppm' => (float) ($testResults['cadmium_ppm'] ?? 0.05),
            'mercury_ppm' => (float) ($testResults['mercury_ppm'] ?? 0.02),
            'salmonella_present' => (bool) ($testResults['salmonella_present'] ?? false),
            'vibrio_cholerae_present' => (bool) ($testResults['vibrio_cholerae_present'] ?? false),
            'is_compliant' => $evaluation['is_eligible_for_export'],
            'quarantine_verdict' => $evaluation['quarantine_release_status'],
            'full_assay_metrics' => [
                'destination_market' => $evaluation['destination_market'],
                'violations' => $evaluation['violations'],
                'warnings' => $evaluation['warnings'],
                'health_certificate_grade' => $evaluation['health_certificate_grade'],
            ],
        ]);

        // Perbarui mutu dan status lot ekspor
        $exportLot->update([
            'quality_grade' => $evaluation['health_certificate_grade'],
            'status' => $evaluation['is_eligible_for_export'] ? 'ASSAY_PASSED_READY_FOR_EXPORT' : 'QUARANTINE_REJECTED',
        ]);

        return $assay;
    }

    /**
     * Dapatkan sertifikat kelayakan fitosanitari ekspor.
     *
     * @return array{
     *     lot_number: string,
     *     is_eligible: bool,
     *     verdict: string,
     *     health_grade: string
     * }
     */
    public function getExportClearanceStatus(int $exportLotId): array
    {
        $exportLot = AquacultureExportLot::findOrFail($exportLotId);
        $latestAssay = LaboratoryAssay::where('export_lot_id', $exportLot->id)->latest('tested_at')->first();

        if (! $latestAssay) {
            return [
                'lot_number' => $exportLot->lot_number,
                'is_eligible' => false,
                'verdict' => 'NO_ASSAY_RECORDED',
                'health_grade' => 'PENDING',
            ];
        }

        return [
            'lot_number' => $exportLot->lot_number,
            'is_eligible' => (bool) $latestAssay->is_compliant,
            'verdict' => $latestAssay->quarantine_verdict,
            'health_grade' => $exportLot->quality_grade,
        ];
    }
}
