<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Modules\Hospital\Domain\Enums\CriticalPanicProtocol;
use Modules\Hospital\Domain\Models\CriticalRangeNotification;
use Modules\Hospital\Domain\Models\LabCriticalRange;
use Modules\Hospital\Domain\Models\LaboratoryLoincCatalog;
use Modules\Hospital\Domain\Models\LabSpecimen;

/**
 * Koordinator Penanganan Nilai Kritis & Notifikasi Darurat Laboratorium (Fase 105).
 */
class CriticalValuePanicAlertCoordinator
{
    /**
     * Mengevaluasi hasil lab terhadap ambang batas nilai kritis (panic range).
     *
     * @return array{is_panic: bool, protocol: string, message: string, panic_type?: string, threshold?: float}
     */
    public function evaluateCriticalPanic(string $testCode, float $numericValue): array
    {
        // 1. Cek di konfigurasi hsp_lab_critical_ranges
        /** @var LabCriticalRange|null $range */
        $range = LabCriticalRange::where('test_code', $testCode)->first();

        if ($range) {
            $isPanicLow = $range->panic_low !== null && $numericValue < (float) $range->panic_low;
            $isPanicHigh = $range->panic_high !== null && $numericValue > (float) $range->panic_high;

            if ($isPanicLow || $isPanicHigh) {
                $panicType = $isPanicLow ? 'LOW' : 'HIGH';
                $threshold = $isPanicLow ? (float) $range->panic_low : (float) $range->panic_high;
                $protocol = $range->notification_protocol ?? CriticalPanicProtocol::CRITICAL_HOLD_AND_PAGE->value;

                $msg = sprintf(
                    'CRITICAL PANIC VALUE ALERT: %s is %.2f (%s threshold: %.2f)',
                    $testCode,
                    $numericValue,
                    $panicType === 'LOW' ? 'Low' : 'High',
                    $threshold
                );

                return [
                    'is_panic' => true,
                    'protocol' => $protocol,
                    'message' => $msg,
                    'panic_type' => $panicType,
                    'threshold' => $threshold,
                ];
            }
        }

        // 2. Cek di master katalog LOINC hsp_lab_catalogs
        /** @var LaboratoryLoincCatalog|null $loinc */
        $loinc = LaboratoryLoincCatalog::where('loinc_num', $testCode)
            ->orWhere('component', $testCode)
            ->orWhere('component', 'like', "%{$testCode}%")
            ->first();

        if ($loinc && $loinc->isValuePanic($numericValue)) {
            $isPanicLow = $loinc->panic_low !== null && $numericValue < $loinc->panic_low;
            $panicType = $isPanicLow ? 'LOW' : 'HIGH';
            $threshold = $isPanicLow ? (float) $loinc->panic_low : (float) $loinc->panic_high;

            return [
                'is_panic' => true,
                'protocol' => CriticalPanicProtocol::CRITICAL_HOLD_AND_PAGE->value,
                'message' => "CRITICAL PANIC VALUE ALERT: {$loinc->component} is {$numericValue} ({$panicType} threshold: {$threshold})",
                'panic_type' => $panicType,
                'threshold' => $threshold,
            ];
        }

        return [
            'is_panic' => false,
            'protocol' => CriticalPanicProtocol::STANDARD_REPORT->value,
            'message' => "Nilai hasil {$testCode} ({$numericValue}) berada dalam batas aman.",
        ];
    }

    /**
     * Menerbitkan rekam notifikasi kritis baru ke database.
     */
    public function dispatchCriticalNotification(
        LabSpecimen $specimen,
        string $testCode,
        string $analyteName,
        float $measuredValue,
        float $panicThreshold,
        string $panicType,
        string $protocol,
        ?string $doctorPhone = null,
        ?int $doctorId = null
    ): CriticalRangeNotification {
        $uid = 'NOTIF-CRIT-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));

        return CriticalRangeNotification::create([
            'notification_uid' => $uid,
            'specimen_id' => $specimen->id,
            'patient_id' => $specimen->patient_id,
            'test_code' => $testCode,
            'analyte_name' => $analyteName,
            'measured_value' => $measuredValue,
            'panic_threshold' => $panicThreshold,
            'panic_type' => $panicType,
            'notification_protocol' => $protocol,
            'doctor_id' => $doctorId,
            'doctor_phone' => $doctorPhone,
            'notified_at' => now(),
            'status' => 'CRITICAL_HOLD',
        ]);
    }
}
