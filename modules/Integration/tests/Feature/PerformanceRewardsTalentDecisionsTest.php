<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PerformanceRewardsTalentDecisionsService;
use Tests\TestCase;

class PerformanceRewardsTalentDecisionsTest extends TestCase
{
    use RefreshDatabase;

    protected PerformanceRewardsTalentDecisionsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PerformanceRewardsTalentDecisionsService::class);
    }

    public function test_goal_setting_calibration_and_reward_payout_flow(): void
    {
        // 423.1 Strategy cascade goal
        $goal = $this->service->setGoal(
            goalCode: 'GOAL-2026-ENG-001',
            employeeId: 'EMP-9001',
            parentStrategyCode: 'CORP-STRAT-SCALABILITY-2026',
            title: 'Improve Monolith Throughput by 25%',
            targetValue: 25.00
        );

        $this->assertEquals('GOAL-2026-ENG-001', $goal->goal_code);

        // 423.2 & 423.3 9-box calibration
        $rating = $this->service->recordCalibratedRating(
            ratingCode: 'RAT-2026-001',
            employeeId: 'EMP-9001',
            performanceRating: '5',
            potentialRating: 'HIGH',
            biasCleared: true
        );

        $this->assertFalse((bool) $rating->rating_locked);

        // Lock rating
        $locked = $this->service->lockRating('RAT-2026-001');
        $this->assertTrue((bool) $locked->rating_locked);

        // 423.4 Payout reward
        $payout = $this->service->payoutReward('RAT-2026-001', 50000000.00);
        $this->assertEquals(50000000.00, (float) $payout->reward_bonus_amount);

        // 423.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_unlocked_payout_and_unapproved_goal_modification_blocked_edge_cases(): void
    {
        // 423.4 Attempting reward payout with unlocked rating is blocked
        $this->service->recordCalibratedRating('RAT-UNLOCKED', 'EMP-99', '4', 'MED', true);

        try {
            $this->service->payoutReward('RAT-UNLOCKED', 10000000.00);
            $this->fail('Expected exception for payout on unlocked rating');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must be calibrated and locked prior to payout', $e->getMessage());
        }

        // 423.5 Mid-period goal modification without committee approval is blocked
        $this->service->setGoal('GOAL-STRICT', 'EMP-99', 'STRAT-01', 'Initial Target', 100);

        try {
            $this->service->adjustGoalMidPeriod('GOAL-STRICT', 150, false);
            $this->fail('Expected exception for unapproved goal adjustment');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Mid-period changes require formal committee approval', $e->getMessage());
        }
    }
}
