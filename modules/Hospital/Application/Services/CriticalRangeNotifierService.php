<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Modules\Hospital\Domain\Models\LabCriticalRange;
use Modules\Hospital\Domain\Models\LaboratoryLoincCatalog;

class CriticalRangeNotifierService
{
    /**
     * Evaluate laboratory test result against critical panic ranges
     *
     * @return array{
     *     is_panic: bool,
     *     protocol: string,
     *     message: string
     * }
     */
    public function evaluateCriticalPanic(string $testCode, float $numericValue): array
    {
        $range = LabCriticalRange::where('test_code', $testCode)->first();

        if ($range) {
            $panicLow = $range->panic_low !== null ? (float) $range->panic_low : null;
            $panicHigh = $range->panic_high !== null ? (float) $range->panic_high : null;

            $isPanic = false;

            if ($panicLow !== null && $numericValue < $panicLow) {
                $isPanic = true;
            } elseif ($panicHigh !== null && $numericValue > $panicHigh) {
                $isPanic = true;
            }

            if ($isPanic) {
                return [
                    'is_panic' => true,
                    'protocol' => $range->notification_protocol ?? 'CRITICAL_HOLD_AND_PAGE',
                    'message' => "CRITICAL PANIC VALUE ALERT: {$testCode} result {$numericValue} breaches life-safety limits.",
                ];
            }

            return [
                'is_panic' => false,
                'protocol' => 'STANDARD_REPORT',
                'message' => 'Result is within non-panic limits.',
            ];
        }

        // Fallback: evaluate against LaboratoryLoincCatalog
        $loinc = LaboratoryLoincCatalog::where('loinc_num', $testCode)
            ->orWhere('component', $testCode)
            ->orWhere('component', 'like', "%{$testCode}%")
            ->first();

        if ($loinc && $loinc->isValuePanic($numericValue)) {
            return [
                'is_panic' => true,
                'protocol' => 'CRITICAL_HOLD_AND_PAGE',
                'message' => "CRITICAL PANIC VALUE ALERT: {$loinc->component} result {$numericValue} breaches life-safety limits.",
            ];
        }

        return [
            'is_panic' => false,
            'protocol' => 'NONE',
            'message' => 'No critical panic range defined.',
        ];
    }
}
