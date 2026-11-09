<?php

declare(strict_types=1);

namespace Tests\Acceptance\PlatformRelease;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Platform\Domain\Algorithms\CanaryErrorEvaluator;
use Modules\Platform\Domain\Enums\CanaryRecommendation;
use Modules\Platform\Domain\Models\CanaryObservation;
use Modules\Platform\Domain\Models\ChangeDeployment;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\Models\ChangeRollbackPlan;
use Modules\Platform\Domain\Models\ReleaseTrain;
use Modules\Platform\Domain\Services\CanaryRollbackOrchestrator;
use Tests\TestCase;

class CanaryDeploymentAutoRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_canary_evaluator_triggers_rollback_when_error_rate_spikes(): void
    {
        $evaluator = new CanaryErrorEvaluator;

        // Healthy Canary: Baseline 50/10000 (0.5%), Canary 6/1000 (0.6%) -> continue
        $healthy = $evaluator->evaluate([
            'baseline_requests' => 10000,
            'baseline_errors' => 50,
            'canary_requests' => 1000,
            'canary_errors' => 6,
        ]);

        $this->assertSame(CanaryRecommendation::CONTINUE, $healthy->recommendation);
        $this->assertFalse($healthy->shouldRollback());

        // Spiking Canary: Baseline 50/10000 (0.5%), Canary 45/1000 (4.5% - 9x increase!) -> rollback
        $spiking = $evaluator->evaluate([
            'baseline_requests' => 10000,
            'baseline_errors' => 50,
            'canary_requests' => 1000,
            'canary_errors' => 45,
        ]);

        $this->assertSame(CanaryRecommendation::ROLLBACK, $spiking->recommendation);
        $this->assertTrue($spiking->shouldRollback());
        $this->assertGreaterThan(3.0, $spiking->zScore);
    }

    public function test_automated_rollback_execution_updates_deployment_and_change_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $train = ReleaseTrain::create([
            'train_code' => 'REL-2026-TEST-ROLLBACK',
            'version' => 'v8.2.0-canary',
            'release_manager_id' => $admin->id,
            'status' => 'deploying',
            'freeze_at' => now(),
            'scheduled_deploy_at' => now(),
        ]);

        $cr = ChangeRequest::create([
            'reference_number' => 'CR-2026-ROLLBACK-01',
            'title' => 'High Risk Payment Flow Change',
            'requester_id' => $admin->id,
            'target_module' => 'Payment',
            'change_type' => 'normal',
            'status' => 'deploying',
            'risk_score' => 8200,
            'risk_tier' => 'critical',
            'release_train_id' => $train->id,
        ]);

        $rollbackPlan = ChangeRollbackPlan::create([
            'change_request_id' => $cr->id,
            'strategy' => 'feature_flag_disable',
            'estimated_rto_seconds' => 30,
            'automated' => true,
            'playbook_steps' => ['disable_traffic_split', 'drain_canary_pods'],
            'verification_checklist' => ['error_rate_below_threshold', 'health_check_200'],
        ]);

        $deployment = ChangeDeployment::create([
            'release_train_id' => $train->id,
            'environment' => 'canary',
            'strategy' => 'canary',
            'traffic_percentage' => 10,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        // Record spiking canary observation
        $observation = CanaryObservation::create([
            'deployment_id' => $deployment->id,
            'sample_window_start' => now()->subMinutes(5),
            'sample_window_end' => now(),
            'baseline_requests' => 10000,
            'baseline_errors' => 20,
            'canary_requests' => 1000,
            'canary_errors' => 80, // 8% error rate vs 0.2%
            'z_score' => 6500,
            'relative_risk_bps' => 4000,
            'recommendation' => CanaryRecommendation::ROLLBACK->value,
        ]);

        // Trigger automated rollback service
        $service = app(CanaryRollbackOrchestrator::class);
        $result = $service->executeAutomatedRollback($deployment, $observation);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('rolled_back', $deployment->fresh()->status);
        $this->assertSame('rolled_back', $cr->fresh()->status);
    }
}
