<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Algorithms\CanaryErrorEvaluator;
use Modules\Platform\Domain\Algorithms\CanaryEvaluationResult;
use Modules\Platform\Domain\Models\CanaryObservation;
use Modules\Platform\Domain\Models\ChangeDeployment;

final class ObserveCanaryMetricsAction
{
    public function __construct(
        private readonly CanaryErrorEvaluator $evaluator
    ) {}

    public function execute(ChangeDeployment $deployment, int $baseReq, int $baseErr, int $canReq, int $canErr): CanaryEvaluationResult
    {
        $result = $this->evaluator->evaluate([
            'baseline_requests' => $baseReq,
            'baseline_errors' => $baseErr,
            'canary_requests' => $canReq,
            'canary_errors' => $canErr,
        ]);

        CanaryObservation::create([
            'deployment_id' => $deployment->id,
            'sample_window_start' => now()->subMinutes(5),
            'sample_window_end' => now(),
            'baseline_requests' => $baseReq,
            'baseline_errors' => $baseErr,
            'canary_requests' => $canReq,
            'canary_errors' => $canErr,
            'z_score' => (int) round($result->zScore * 1000),
            'relative_risk_bps' => (int) round($result->relativeRisk * 10000),
            'recommendation' => $result->recommendation->value,
        ]);

        return $result;
    }
}
