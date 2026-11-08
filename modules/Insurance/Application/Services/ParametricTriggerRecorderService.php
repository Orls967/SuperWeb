<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Illuminate\Support\Facades\Log;
use Modules\Insurance\Contracts\ParametricTriggerRecorder;

class ParametricTriggerRecorderService implements ParametricTriggerRecorder
{
    /**
     * Catat pemicu kejadian parametrik untuk pencairan klaim otomatis.
     */
    public function recordTrigger(
        string $policyNumber,
        string $flightNumber,
        string $triggerType,
        int $durationMinutes,
        int $payoutAmountMinorUnit,
        array $telemetryData = []
    ): string {
        $triggerId = sprintf(
            'PTR-%s-%s-%s',
            strtoupper(substr(hash('sha256', $policyNumber.$flightNumber.$triggerType), 0, 10)),
            date('Ymd'),
            substr(uniqid('', true), -4)
        );

        Log::info('Parametric insurance trigger recorded', [
            'trigger_id' => $triggerId,
            'policy_number' => $policyNumber,
            'flight_number' => $flightNumber,
            'trigger_type' => $triggerType,
            'duration_minutes' => $durationMinutes,
            'payout_amount_minor_unit' => $payoutAmountMinorUnit,
            'telemetry' => $telemetryData,
        ]);

        return $triggerId;
    }
}
