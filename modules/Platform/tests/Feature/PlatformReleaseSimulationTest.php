<?php

declare(strict_types=1);

namespace Modules\Platform\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Platform\Domain\Actions\CreateReleaseTrainAction;
use Modules\Platform\Domain\Algorithms\ChangeRiskScorer;
use Modules\Platform\Domain\Enums\CanaryRecommendation;
use Modules\Platform\Domain\Enums\ChangeRiskTier;
use Modules\Platform\Domain\Enums\ChangeStatus;
use Modules\Platform\Domain\Models\CanaryObservation;
use Modules\Platform\Domain\Models\ChangeDeployment;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\Models\ChangeRollbackPlan;
use Modules\Platform\Domain\Models\ReleaseTrain;
use Modules\Platform\Domain\Services\CanaryRollbackOrchestrator;
use Modules\Platform\Domain\StateMachines\ChangeRequestStateMachine;
use Tests\TestCase;

class PlatformReleaseSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_release_and_canary_auto_rollback_simulation(): void
    {
        $user = User::factory()->create();

        // 1. Create Release Train
        $trainAction = app(CreateReleaseTrainAction::class);
        $train = $trainAction->execute(
            'Sprint-88-Train',
            'v2.1.0',
            $user->id,
            new \DateTimeImmutable('+3 days'),
            new \DateTimeImmutable('+5 days')
        );

        $this->assertInstanceOf(ReleaseTrain::class, $train);
        $statusVal = is_string($train->status) ? $train->status : $train->status->value;
        $this->assertEquals('open', $statusVal);

        // 2. Create Change Request
        $cr = ChangeRequest::create([
            'reference_number' => 'CR-SIM-001',
            'title' => 'Critical Payment Microservice Migration',
            'description' => 'Migrate ledger write path to new schema',
            'requester_id' => $user->id,
            'target_module' => 'Billing',
            'change_type' => 'normal',
            'status' => ChangeStatus::DRAFT->value,
            'risk_score' => 7850,
            'risk_tier' => ChangeRiskTier::HIGH->value,
            'release_train_id' => $train->id,
        ]);

        ChangeRollbackPlan::create([
            'change_request_id' => $cr->id,
            'strategy' => 'feature_flag_disable',
            'estimated_rto_seconds' => 30,
            'automated' => true,
            'playbook_steps' => ['disable_traffic_split', 'drain_canary_pods'],
            'verification_checklist' => ['error_rate_below_threshold', 'health_check_200'],
        ]);

        // 3. Assess Risk via scorer
        $scorer = app(ChangeRiskScorer::class);
        $assessment = $scorer->calculateRisk([
            'loc_diff' => 1200,
            'target_module' => 'Core',
            'has_ddl' => true,
            'is_destructive_ddl' => true,
            'past_incident_rate_pct' => 15,
        ]);
        $this->assertGreaterThanOrEqual(50, $assessment->score);
        $this->assertEquals(ChangeRiskTier::CRITICAL, $assessment->tier);

        // 4. Progress State Machine: DRAFT -> ASSESSED -> CAB_REVIEW -> APPROVED -> SCHEDULED -> DEPLOYING
        $sm = new ChangeRequestStateMachine($cr);
        $sm->transitionTo(ChangeStatus::ASSESSED, $user->id, 'Risk assessed');
        $sm->transitionTo(ChangeStatus::CAB_REVIEW, $user->id, 'Escalating to CAB due to High risk tier');
        $sm->transitionTo(ChangeStatus::APPROVED, $user->id, 'CAB quorum approved change');
        $sm->transitionTo(ChangeStatus::SCHEDULED, $user->id, 'Scheduled into train v2.1.0');
        $sm->transitionTo(ChangeStatus::DEPLOYING, $user->id, 'Starting canary rollout');

        $this->assertEquals('deploying', $cr->fresh()->status);

        // 5. Initiate Deployment record
        $deployment = ChangeDeployment::create([
            'release_train_id' => $train->id,
            'environment' => 'canary',
            'strategy' => 'canary',
            'traffic_percentage' => 10,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        // 6. Record spiking canary observation
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

        // 7. Auto-Rollback Execution
        $orchestrator = app(CanaryRollbackOrchestrator::class);
        $rollbackResult = $orchestrator->executeAutomatedRollback($deployment, $observation);

        $this->assertTrue($rollbackResult->isSuccess());
        $this->assertEquals('rolled_back', $deployment->fresh()->status);
        $this->assertEquals('rolled_back', $cr->fresh()->status);
    }
}
