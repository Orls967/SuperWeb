<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\AiDecisionAuditService;
use Tests\TestCase;

/**
 * Fase 197 — AI: Decision Log, Explainability & Model Audit Tests
 *
 * Covers:
 *  (a) decision log stores immutable input snapshot and features
 *  (b) decision can be replayed identically
 *  (c) fairness disparity threshold detects bias
 *  (d) aidecision:audit = 0 discrepancy
 */
class AiDecisionAuditTest extends TestCase
{
    use RefreshDatabase;

    protected AiDecisionAuditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AiDecisionAuditService::class);
    }

    /**
     * (a) & (b) Immutable decision log and deterministic replay.
     */
    public function test_decision_log_and_identical_replay(): void
    {
        $input = ['applicant_age' => 35, 'credit_score' => 750, 'loan_amount' => 150000000];
        $output = ['status' => 'APPROVED', 'approved_limit' => 150000000, 'interest_rate' => 0.085];
        $features = ['credit_score_weight' => 0.65, 'income_to_debt_weight' => 0.25];

        $log = $this->service->logDecision('L08_MICROFINANCE', '2.1.0', $input, $output, $features, 0.04);
        $this->assertFalse((bool) $log->bias_flag);

        // Replay decision
        $replay = $this->service->replayDecision($log->decision_code);
        $this->assertTrue($replay['is_identical']);
        $this->assertSame('2.1.0', $replay['model_version']);
        $this->assertSame($input, $replay['replayed_input']);
        $this->assertSame($output, $replay['replayed_output']);
    }

    /**
     * (c) Fairness and bias disparity detection.
     */
    public function test_fairness_disparity_bias_detection(): void
    {
        // 1. Within tolerance (4% disparity <= 15%) -> No bias flag
        $log1 = $this->service->logDecision('L07_INSURANCE', '1.0.0', ['age' => 40], ['claim' => 'APPROVED'], ['loss_ratio' => 0.4], 0.0400);
        $this->assertFalse((bool) $log1->bias_flag);

        // 2. Beyond tolerance (22% disparity > 15%) -> Flagged
        $log2 = $this->service->logDecision('L07_INSURANCE', '1.0.0', ['age' => 20], ['claim' => 'FLAGGED'], ['loss_ratio' => 0.9], 0.2200);
        $this->assertTrue((bool) $log2->bias_flag);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies (when bias resolved).
     */
    public function test_ai_decision_audit(): void
    {
        // Clean database with valid non-biased log
        DB::table('ai_decision_logs')->truncate();
        $this->service->logDecision('L07_INSURANCE', '1.0.0', ['age' => 40], ['claim' => 'APPROVED'], ['loss_ratio' => 0.4], 0.0200);

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
