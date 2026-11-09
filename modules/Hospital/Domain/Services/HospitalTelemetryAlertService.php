<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

final class HospitalTelemetryAlertService
{
    /**
     * Memproses multicast alert telemetri ke nurse station dan dokter jaga ICU/IGD.
     *
     * @param  array<string, mixed>  $telemetryData
     * @param  list<string>  $recipientRoles
     * @return array<string, mixed>
     */
    public function dispatchCriticalTelemetryAlert(
        int $admissionId,
        int $bedId,
        string $alertType,
        int $severityScore,
        array $telemetryData,
        array $recipientRoles = ['nurse', 'doctor', 'bed_manager']
    ): array {
        $alertId = 'ALT-'.strtoupper(substr(hash('sha256', uniqid((string) $admissionId, true)), 0, 10));

        $payload = [
            'alert_id' => $alertId,
            'admission_id' => $admissionId,
            'bed_id' => $bedId,
            'alert_type' => $alertType,
            'severity' => $severityScore >= 7 ? 'CRITICAL_CODE_BLUE_RISK' : ($severityScore >= 5 ? 'URGENT_RRT' : 'WARNING'),
            'dispatched_roles' => $recipientRoles,
            'telemetry' => $telemetryData,
            'dispatched_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        Log::warning("TELEMETRY_CRITICAL_ALERT: [{$alertId}] Bed #{$bedId} Admission #{$admissionId} Type: {$alertType} Severity: {$severityScore}");

        return $payload;
    }
}
