<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Pipeline;

use Modules\Platform\Domain\Models\ChangeDeployment;
use Modules\Platform\Domain\Models\ReleaseTrain;

final class DeploymentPipelineOrchestrator
{
    /**
     * @var list<string>
     */
    private const ORDERED_STAGES = [
        'PRE_FLIGHT_LINT',
        'STAGING_SMOKE_TEST',
        'CANARY_5_PERCENT',
        'CANARY_OBSERVATION_WINDOW',
        'CANARY_25_PERCENT',
        'PRODUCTION_FULL_PROMOTION',
        'POST_DEPLOY_AUDIT',
    ];

    /**
     * Evaluates whether a deployment can proceed to the next progressive delivery stage.
     *
     * @param  array<string, bool>  $stageCompletionMap
     * @return array{can_advance: bool, current_stage: string, next_stage: ?string, pending_gates: list<string>}
     */
    public function evaluateProgression(ChangeDeployment $deployment, array $stageCompletionMap): array
    {
        $currentStage = 'PRE_FLIGHT_LINT';
        $nextStage = null;
        $pendingGates = [];

        foreach (self::ORDERED_STAGES as $stage) {
            $isComplete = $stageCompletionMap[$stage] ?? false;
            if (! $isComplete) {
                $pendingGates[] = $stage;
                if ($nextStage === null) {
                    $nextStage = $stage;
                }
            } else {
                $currentStage = $stage;
            }
        }

        $canAdvance = ($nextStage !== null && count($pendingGates) < count(self::ORDERED_STAGES));

        return [
            'can_advance' => $canAdvance,
            'current_stage' => $currentStage,
            'next_stage' => $nextStage,
            'pending_gates' => $pendingGates,
        ];
    }

    /**
     * Synthesizes automated audit report for deployment pipeline.
     */
    public function generatePipelineSummary(ReleaseTrain $train, ChangeDeployment $deployment): array
    {
        return [
            'pipeline_id' => "PIPE-{$train->train_code}-{$deployment->id}",
            'train_version' => $train->version,
            'environment' => $deployment->environment,
            'traffic_percentage' => $deployment->traffic_percentage,
            'status' => $deployment->status,
            'started_at' => $deployment->started_at?->toIso8601String(),
            'completed_at' => $deployment->completed_at?->toIso8601String(),
            'is_healthy' => $deployment->status === 'succeeded',
        ];
    }
}
