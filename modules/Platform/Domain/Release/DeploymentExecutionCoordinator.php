<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

use Carbon\CarbonImmutable;
use Modules\Platform\Domain\Enums\ChangeRiskTier;
use Modules\Platform\Domain\Enums\ChangeStatus;
use Modules\Platform\Domain\Models\ChangeDeployment;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\Services\CanaryRollbackOrchestrator;
use RuntimeException;

final class DeploymentExecutionCoordinator
{
    public function __construct(
        private readonly CanaryRollbackOrchestrator $rollbackOrchestrator,
        private readonly DoraMetricsCalculator $metricsCalculator,
    ) {}

    /**
     * @return array{
     *     allowed: bool,
     *     reasons: list<string>,
     *     prerequisites_met: bool,
     *     concurrency_slot: string|null
     * }
     */
    public function evaluateDeploymentReadiness(ChangeRequest $changeRequest, string $targetEnvironment): array
    {
        $reasons = [];
        $allowed = true;

        if ($changeRequest->status !== ChangeStatus::Scheduled && $changeRequest->status !== ChangeStatus::Approved) {
            $allowed = false;
            $reasons[] = "Change request {$changeRequest->change_number} must be in scheduled or approved status (current: {$changeRequest->status->value})";
        }

        if ($targetEnvironment === 'production' && $changeRequest->risk_tier === ChangeRiskTier::Critical) {
            if ($changeRequest->cab_approved_at === null) {
                $allowed = false;
                $reasons[] = 'Critical change requests require CAB sign-off prior to production deployment';
            }
        }

        $activeDeployments = ChangeDeployment::query()
            ->where('environment', $targetEnvironment)
            ->whereIn('status', ['deploying', 'canary_evaluating', 'promoting'])
            ->count();

        if ($activeDeployments > 0) {
            $allowed = false;
            $reasons[] = "Target environment '{$targetEnvironment}' currently has {$activeDeployments} in-flight deployment(s)";
        }

        return [
            'allowed' => $allowed,
            'reasons' => $reasons,
            'prerequisites_met' => empty($reasons),
            'concurrency_slot' => $allowed ? "env-{$targetEnvironment}-slot-".bin2hex(random_bytes(4)) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordDeploymentProgression(
        ChangeDeployment $deployment,
        string $stage,
        string $status,
        array $payload = []
    ): ChangeDeployment {
        $history = $deployment->metadata ?? [];
        $steps = $history['execution_steps'] ?? [];

        $steps[] = [
            'stage' => $stage,
            'status' => $status,
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
            'payload' => $payload,
        ];

        $history['execution_steps'] = $steps;
        $deployment->metadata = $history;
        $deployment->status = $status;
        $deployment->save();

        return $deployment;
    }

    /**
     * @return array{
     *     total_deployments: int,
     *     successful_deployments: int,
     *     rolled_back_deployments: int,
     *     failure_rate_percent: float,
     *     mean_canary_duration_seconds: float
     * }
     */
    public function calculateEnvironmentHealth(string $environment, int $windowDays = 14): array
    {
        $since = CarbonImmutable::now()->subDays($windowDays);

        $deployments = ChangeDeployment::query()
            ->where('environment', $environment)
            ->where('created_at', '>=', $since)
            ->get();

        $total = $deployments->count();
        if ($total === 0) {
            return [
                'total_deployments' => 0,
                'successful_deployments' => 0,
                'rolled_back_deployments' => 0,
                'failure_rate_percent' => 0.0,
                'mean_canary_duration_seconds' => 0.0,
            ];
        }

        $successful = $deployments->where('status', 'promoted')->count();
        $rolledBack = $deployments->where('status', 'rolled_back')->count();
        $failureRate = round(($rolledBack / $total) * 100, 2);

        $durations = [];
        foreach ($deployments as $dep) {
            if ($dep->status === 'promoted' && $dep->started_at && $dep->completed_at) {
                $durations[] = $dep->started_at->diffInSeconds($dep->completed_at);
            }
        }

        $meanDuration = count($durations) > 0 ? array_sum($durations) / count($durations) : 0.0;

        return [
            'total_deployments' => $total,
            'successful_deployments' => $successful,
            'rolled_back_deployments' => $rolledBack,
            'failure_rate_percent' => $failureRate,
            'mean_canary_duration_seconds' => round($meanDuration, 2),
        ];
    }

    public function emergencyAbort(ChangeDeployment $deployment, string $operatorReason): void
    {
        if ($deployment->status === 'promoted' || $deployment->status === 'rolled_back') {
            throw new RuntimeException("Cannot abort deployment {$deployment->deployment_code} in terminal status {$deployment->status}");
        }

        $deployment->status = 'rolled_back';
        $metadata = $deployment->metadata ?? [];
        $metadata['aborted_by_operator'] = true;
        $metadata['abort_reason'] = $operatorReason;
        $metadata['aborted_at'] = CarbonImmutable::now()->toIso8601String();
        $deployment->metadata = $metadata;
        $deployment->save();

        $changeRequest = $deployment->changeRequest;
        if ($changeRequest !== null) {
            $changeRequest->status = ChangeStatus::RolledBack;
            $changeRequest->save();
        }
    }
}
