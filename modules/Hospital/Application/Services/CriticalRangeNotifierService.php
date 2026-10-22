<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Modules\Hospital\Domain\Models\LabCriticalRange;

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

        if (! $range) {
            return [
                'is_panic' => false,
                'protocol' => 'NONE',
                'message' => 'No critical panic range defined.',
            ];
        }

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
                'protocol' => $range->notification_protocol,
                'message' => "CRITICAL PANIC VALUE ALERT: {$testCode} result {$numericValue} breaches life-safety limits.",
            ];
        }

        return [
            'is_panic' => false,
            'protocol' => 'STANDARD_REPORT',
            'message' => 'Result is within non-panic limits.',
        ];
    }
}
