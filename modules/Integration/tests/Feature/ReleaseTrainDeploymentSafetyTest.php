<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ReleaseTrainDeploymentSafetyService;
use Tests\TestCase;

class ReleaseTrainDeploymentSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected ReleaseTrainDeploymentSafetyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReleaseTrainDeploymentSafetyService::class);
    }

    public function test_schedule_release_train_and_hotfix_guard(): void
    {
        // 1. Scheduled release train
        $train = $this->service->scheduleReleaseTrain('SCHEDULED');
        $this->assertEquals('SCHEDULED', $train->release_type);
        $this->assertStringStartsWith('TRAIN-', $train->train_code);
        $this->assertNotEmpty($train->release_notes_summary);

        // 2. Hotfix without approver throws exception (238.6)
        $this->expectException(InvalidArgumentException::class);
        $this->service->scheduleReleaseTrain('HOTFIX', null);
    }

    public function test_urgent_hotfix_with_approval_and_post_merge_review(): void
    {
        $hotfix = $this->service->scheduleReleaseTrain(
            releaseType: 'HOTFIX',
            approvedBy: 'VP_ENG_EMERGENCY',
            notesSummary: 'Critical security patch for auth bypass'
        );

        $this->assertEquals('HOTFIX', $hotfix->release_type);
        $this->assertEquals('VP_ENG_EMERGENCY', $hotfix->approved_by);
        $this->assertFalse((bool) $hotfix->post_merge_review_completed);

        // Complete post-merge review (238.6)
        $reviewed = $this->service->completePostMergeReview((int) $hotfix->id, 'QA_LEAD');
        $this->assertTrue((bool) $reviewed->post_merge_review_completed);
        $this->assertEquals('COMPLETED', $reviewed->status);
    }

    public function test_change_request_risk_scoring_and_cab_guard(): void
    {
        // 1. Low risk change
        $lowRisk = $this->service->evaluateChangeRequest(
            title: 'Fix typo on terms modal',
            blastRadiusType: 'LOW_RISK',
            riskScore: 15.0,
            hasRollbackPlan: true
        );
        $this->assertEquals('NOT_REQUIRED', $lowRisk->cab_approval_status);

        // 2. High risk change with blast radius MONEY
        $highRisk = $this->service->evaluateChangeRequest(
            title: 'Update payment routing logic',
            blastRadiusType: 'MONEY',
            riskScore: 85.0,
            hasRollbackPlan: false
        );
        $this->assertEquals('PENDING', $highRisk->cab_approval_status);

        // CAB approval rejected because rollback plan is missing (238.2)
        try {
            $this->service->approveCab((int) $highRisk->id, 'CAB_BOARD');
            $this->fail('Expected exception for missing rollback plan');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Mandatory rollback plan is missing', $e->getMessage());
        }

        // Add rollback plan and approve
        DB::table('platform_change_requests')
            ->where('id', $highRisk->id)
            ->update(['rollback_plan_documented' => true]);

        $approved = $this->service->approveCab((int) $highRisk->id, 'CAB_BOARD');
        $this->assertEquals('APPROVED', $approved->cab_approval_status);

        $verified = $this->service->verifyPostDeploy((int) $highRisk->id);
        $this->assertTrue((bool) $verified->post_deploy_verified);
    }

    public function test_schema_migration_expand_contract_safety_lint(): void
    {
        // 1. Breaking migration with standard pattern is rejected (238.3 & 238.7)
        $rejectedMigration = $this->service->lintSchemaMigration(
            migrationName: '2026_10_drop_column_users_legacy',
            pattern: 'STANDARD',
            isBreakingChange: true
        );
        $this->assertFalse((bool) $rejectedMigration->has_lint_passed);
        $this->assertStringContainsString('requires expand-contract pattern', $rejectedMigration->rejection_reason);

        // 2. Breaking migration with expand-contract pattern passes lint
        $passedMigration = $this->service->lintSchemaMigration(
            migrationName: '2026_10_expand_users_dual_write',
            pattern: 'EXPAND_CONTRACT',
            isBreakingChange: true
        );
        $this->assertTrue((bool) $passedMigration->has_lint_passed);
        $this->assertNull($passedMigration->rejection_reason);
    }

    public function test_canary_rollout_simulation_and_auto_rollback(): void
    {
        // 1. Healthy Canary promoted to 100%
        $promoted = $this->service->simulateCanaryRollout(
            rolloutCode: 'CANARY-V2-01',
            trainId: null,
            trafficPct: 100.0,
            errorRatePct: 0.8
        );
        $this->assertEquals('PROMOTED', $promoted->status);
        $this->assertNull($promoted->rolled_back_at);

        // 2. Erroneous Canary triggers auto rollback (> 5% error) (238.4)
        $rolledBack = $this->service->simulateCanaryRollout(
            rolloutCode: 'CANARY-V2-02',
            trainId: null,
            trafficPct: 15.0,
            errorRatePct: 8.4,
            errorThresholdPct: 5.0
        );
        $this->assertEquals('AUTO_ROLLED_BACK', $rolledBack->status);
        $this->assertNotNull($rolledBack->rolled_back_at);
    }

    public function test_deployment_safety_audit_healthy_and_discrepancy(): void
    {
        // Setup healthy records
        $train = $this->service->scheduleReleaseTrain('SCHEDULED');
        $this->service->simulateCanaryRollout('CANARY-AUDIT-1', (int) $train->id, 100.0, 1.2);
        $this->service->lintSchemaMigration('valid_expand', 'EXPAND_CONTRACT', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: hotfix COMPLETED without post-merge review
        DB::table('platform_release_trains')->insert([
            'train_code' => 'HOTFIX-ROGUE',
            'release_type' => 'HOTFIX',
            'status' => 'COMPLETED',
            'scheduled_at' => now(),
            'approved_by' => 'LEAD',
            'post_merge_review_completed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
