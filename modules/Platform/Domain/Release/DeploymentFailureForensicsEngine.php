<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

use Carbon\CarbonImmutable;
use Modules\Platform\Domain\Models\CanaryObservation;
use Modules\Platform\Domain\Models\ChangeDeployment;

final class DeploymentFailureForensicsEngine
{
    /**
     * @param  array<string, mixed>  $runtimeExceptions
     * @return array{
     *     root_cause_category: string,
     *     primary_error_message: string,
     *     failed_at: string,
     *     telemetry_snapshot: array<string, mixed>,
     *     recommended_mitigation: string
     * }
     */
    public function compilePostMortemDiagnostics(
        ChangeDeployment $deployment,
        array $runtimeExceptions = []
    ): array {
        $observations = CanaryObservation::query()
            ->where('change_deployment_id', $deployment->id)
            ->orderBy('observed_at', 'desc')
            ->limit(5)
            ->get();

        $maxObservedErrorRate = 0.0;
        foreach ($observations as $obs) {
            if ($obs->error_rate_delta > $maxObservedErrorRate) {
                $maxObservedErrorRate = $obs->error_rate_delta;
            }
        }

        $rootCause = 'UNKNOWN_FAILURE';
        $primaryError = 'No explicit exception captured in runtime buffer.';
        $mitigation = 'Inspect system logs and application error reporting sink.';

        if (! empty($runtimeExceptions)) {
            $rootCause = 'RUNTIME_EXCEPTION';
            $primaryError = (string) ($runtimeExceptions[0]['message'] ?? 'Application exception thrown');
            $mitigation = 'Fix regression in application code and trigger patch release train.';
        } elseif ($maxObservedErrorRate > 0.05) {
            $rootCause = 'CANARY_METRIC_DEGRADATION';
            $primaryError = "Canary error rate exceeded tolerance with delta +{$maxObservedErrorRate}";
            $mitigation = 'Verify external dependencies and upstream API availability before re-running.';
        }

        return [
            'root_cause_category' => $rootCause,
            'primary_error_message' => $primaryError,
            'failed_at' => CarbonImmutable::now()->toIso8601String(),
            'telemetry_snapshot' => [
                'deployment_code' => $deployment->deployment_code,
                'target_environment' => $deployment->environment,
                'recent_observations_count' => $observations->count(),
                'max_error_rate_delta' => $maxObservedErrorRate,
            ],
            'recommended_mitigation' => $mitigation,
        ];
    }
}
