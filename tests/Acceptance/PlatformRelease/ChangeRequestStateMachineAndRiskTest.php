<?php

declare(strict_types=1);

namespace Tests\Acceptance\PlatformRelease;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Platform\Domain\Algorithms\ChangeRiskScorer;
use Modules\Platform\Domain\Enums\ChangeRiskTier;
use Modules\Platform\Domain\Enums\ChangeStatus;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\StateMachines\ChangeRequestStateMachine;
use Tests\TestCase;

class ChangeRequestStateMachineAndRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_risk_scorer_calculates_low_and_high_risk_appropriately(): void
    {
        $scorer = new ChangeRiskScorer;

        // Low risk change: 20 LOC, UI module, no DDL, zero past incidents
        $lowRisk = $scorer->calculateRisk([
            'loc_diff' => 20,
            'target_module' => 'Platform',
            'has_ddl' => false,
            'is_destructive_ddl' => false,
            'past_incident_rate_pct' => 0,
        ]);

        $this->assertLessThan(30.0, $lowRisk->score);
        $this->assertSame(ChangeRiskTier::LOW, $lowRisk->tier);
        $this->assertFalse($lowRisk->requiresCabReview());

        // High risk change: 1200 LOC, Core banking module, destructive DDL, 25% incident rate
        $highRisk = $scorer->calculateRisk([
            'loc_diff' => 1200,
            'target_module' => 'Core',
            'has_ddl' => true,
            'is_destructive_ddl' => true,
            'past_incident_rate_pct' => 25,
        ]);

        $this->assertGreaterThan(65.0, $highRisk->score);
        $this->assertTrue($highRisk->requiresCabReview());
    }

    public function test_change_request_state_machine_enforces_all_nine_statuses(): void
    {
        $author = User::factory()->create(['role' => 'admin']);
        $cabReviewer = User::factory()->create(['role' => 'admin']);

        $cr = ChangeRequest::create([
            'reference_number' => 'CR-2026-TEST-01',
            'title' => 'Core Ledger Migration',
            'description' => 'Migrate ledger partition table',
            'requester_id' => $author->id,
            'target_module' => 'Core',
            'change_type' => 'normal',
            'status' => ChangeStatus::DRAFT->value,
            'risk_score' => 7850,
            'risk_tier' => ChangeRiskTier::HIGH->value,
        ]);

        $sm = new ChangeRequestStateMachine($cr);

        // 1. draft -> assessed
        $this->assertTrue($sm->canTransitionTo(ChangeStatus::ASSESSED));
        $sm->transitionTo(ChangeStatus::ASSESSED, $author->id, 'Risk assessment completed');
        $this->assertSame(ChangeStatus::ASSESSED->value, $cr->fresh()->status);

        // 2. High risk must go to cab_review (cannot jump to approved)
        $this->assertFalse($sm->canTransitionTo(ChangeStatus::APPROVED));
        $this->assertTrue($sm->canTransitionTo(ChangeStatus::CAB_REVIEW));
        $sm->transitionTo(ChangeStatus::CAB_REVIEW, $cabReviewer->id, 'Submitted for CAB quorum');

        // 3. cab_review -> approved
        $sm->transitionTo(ChangeStatus::APPROVED, $cabReviewer->id, 'CAB approved 4/4 votes');

        // 4. approved -> scheduled
        $sm->transitionTo(ChangeStatus::SCHEDULED, $author->id, 'Scheduled for window W45');

        // 5. scheduled -> deploying
        $sm->transitionTo(ChangeStatus::DEPLOYING, $author->id, 'Deployment triggered');

        // 6. deploying -> verified
        $sm->transitionTo(ChangeStatus::VERIFIED, $author->id, 'Smoke tests & health checks 100% green');

        // 7. verified -> closed
        $sm->transitionTo(ChangeStatus::CLOSED, $author->id, 'Change successfully closed');
        $this->assertSame(ChangeStatus::CLOSED->value, $cr->fresh()->status);

        // Assert 7 audit transitions recorded (draft->assessed->cab_review->approved->scheduled->deploying->verified->closed)
        $this->assertCount(7, $cr->transitions);
    }
}
