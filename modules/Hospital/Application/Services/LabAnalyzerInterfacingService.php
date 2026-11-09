<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Modules\Hospital\Domain\Models\LabQualityControl;
use Modules\Hospital\Domain\Models\LabResult;
use Modules\Hospital\Domain\Models\LabSpecimen;
use RuntimeException;

/**
 * Layanan Antarmuka Alat Analisis Laboratorium & Delta Checking (Fase 105).
 */
class LabAnalyzerInterfacingService
{
    /**
     * Memproses transmisi data mentah instrumen analitik lab.
     *
     * @param  array{analyzer_code: string, test_code: string, raw_value: float, hil_flags?: array{hemolysis: int, icterus: int, lipemia: int}}  $instrumentPayload
     *
     * @throws RuntimeException Jika instrumen sedang terkunci akibat pelanggaran QC Westgard.
     */
    public function ingestAnalyzerRawResult(
        LabSpecimen $specimen,
        array $instrumentPayload,
        CriticalValuePanicAlertCoordinator $panicCoordinator
    ): LabResult {
        $analyzerCode = $instrumentPayload['analyzer_code'];
        $testCode = $instrumentPayload['test_code'];
        $rawValue = $instrumentPayload['raw_value'];

        // 1. Cek apakah instrumen terkunci akibat kegagalan QC Westgard
        $isLocked = LabQualityControl::where('analyzer_code', $analyzerCode)
            ->where('is_rejected', true)
            ->where('is_resolved', false)
            ->exists();

        if ($isLocked) {
            throw new RuntimeException("Analyzer is locked due to Westgard QC violation: [{$analyzerCode}]");
        }

        // 2. Evaluasi indeks interferensi HIL (Hemolisis, Ikterus, Lipemia)
        $hil = $instrumentPayload['hil_flags'] ?? null;
        if ($hil && ($hil['hemolysis'] > 3 || $hil['icterus'] > 4 || $hil['lipemia'] > 4)) {
            throw new RuntimeException(
                "Sample rejected by analyzer optical pre-scan due to severe interferent index: Hemolysis={$hil['hemolysis']}, Lipemia={$hil['lipemia']}"
            );
        }

        // 3. Evaluasi ambang batas panik
        $panicEval = $panicCoordinator->evaluateCriticalPanic($testCode, $rawValue);
        $isPanic = $panicEval['is_panic'];
        $status = $isPanic ? 'CRITICAL_HOLD' : 'AUTO_VERIFIED';

        $resultCode = 'RES-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));

        $result = LabResult::create([
            'result_code' => $resultCode,
            'specimen_id' => $specimen->id,
            'test_code' => $testCode,
            'numeric_value' => $rawValue,
            'is_critical' => $isPanic,
            'status' => $status,
        ]);

        // Jika kritis, catat notifikasi darurat
        if ($isPanic) {
            $panicCoordinator->dispatchCriticalNotification(
                specimen: $specimen,
                testCode: $testCode,
                analyteName: $testCode,
                measuredValue: $rawValue,
                panicThreshold: $panicEval['threshold'] ?? $rawValue,
                panicType: $panicEval['panic_type'] ?? 'HIGH',
                protocol: $panicEval['protocol']
            );
        }

        return $result;
    }

    /**
     * Melakukan evaluasi Delta Check terhadap riwayat hasil tes pasien sebelumnya.
     *
     * @return array{delta_exceeded: bool, delta_percentage: float, previous_value: float|null}
     */
    public function evaluateDeltaCheck(int $patientId, string $testCode, float $currentValue, float $maxDeltaPercent = 50.0): array
    {
        /** @var LabResult|null $prev */
        $prev = LabResult::join('hsp_lab_specimens', 'hsp_lab_results.specimen_id', '=', 'hsp_lab_specimens.id')
            ->where('hsp_lab_specimens.patient_id', $patientId)
            ->where('hsp_lab_results.test_code', $testCode)
            ->where('hsp_lab_results.status', '!=', 'REJECTED')
            ->orderBy('hsp_lab_results.created_at', 'desc')
            ->select('hsp_lab_results.*')
            ->first();

        if (! $prev || $prev->numeric_value === null || (float) $prev->numeric_value <= 0.0) {
            return [
                'delta_exceeded' => false,
                'delta_percentage' => 0.0,
                'previous_value' => null,
            ];
        }

        $prevVal = (float) $prev->numeric_value;
        $deltaPercent = abs(($currentValue - $prevVal) / $prevVal) * 100.0;
        $exceeded = $deltaPercent > $maxDeltaPercent;

        return [
            'delta_exceeded' => $exceeded,
            'delta_percentage' => round($deltaPercent, 2),
            'previous_value' => $prevVal,
        ];
    }
}
