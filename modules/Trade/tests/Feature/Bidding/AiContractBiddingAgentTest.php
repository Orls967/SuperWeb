<?php

namespace Modules\Trade\tests\Feature\Bidding;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Trade\Application\Services\Bidding\AiBiddingAgentService;
use Modules\Trade\Domain\Models\Bidding\BiddingAgent;
use Tests\TestCase;

class AiContractBiddingAgentTest extends TestCase
{
    use RefreshDatabase;

    protected AiBiddingAgentService $service;

    protected BiddingAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AiBiddingAgentService::class);

        $this->agent = BiddingAgent::create([
            'agent_code' => 'AGT-COAL-01',
            'entity_code' => 'TRD-GLOBAL-INDONESIA',
            'commodity_code' => 'THERMAL_COAL_GAR5000',
            'min_price_floor_idr' => 800_000_000,
            'max_price_ceiling_idr' => 1_500_000_000,
            'target_margin_pct' => 20.0,
            'max_risk_score' => 0.35,
            'status' => 'ACTIVE',
        ]);

        LedgerAccount::create([
            'code' => 'trd:tender_contract_ar:IDR',
            'name' => 'Tender Contract Receivable',
            'asset_code' => 'IDR',
            'kind' => 'asset',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'trd:tender_revenue_unearned:IDR',
            'name' => 'Tender Unearned Revenue',
            'asset_code' => 'IDR',
            'kind' => 'revenue',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
    }

    public function test_84_2_deterministic_ai_bid_evaluation_and_clause_drafting(): void
    {
        // Cost: 800,000,000 + 50,000,000 = 850,000,000
        // Target margin: +20% -> 850,000,000 * 1.20 = 1,020,000,000 IDR
        $run1 = $this->service->runEvaluation(
            agent: $this->agent,
            tenderCode: 'TND-PLN-2026-09',
            commodityFeedPriceIdr: 800_000_000,
            logisticsCostIdr: 50_000_000,
            buyerRiskScore: 0.20
        );

        $this->assertEquals(1_020_000_000, $run1->optimal_bid_price_idr);
        $this->assertArrayHasKey('payment_terms', $run1->clause_drafts);
        $this->assertNotNull($run1->evaluation_hash);

        // Deterministic repeat check: second run with exact inputs yields identical price and hash
        $run2 = $this->service->runEvaluation(
            agent: $this->agent,
            tenderCode: 'TND-PLN-2026-09',
            commodityFeedPriceIdr: 800_000_000,
            logisticsCostIdr: 50_000_000,
            buyerRiskScore: 0.20
        );
        $this->assertEquals($run1->optimal_bid_price_idr, $run2->optimal_bid_price_idr);
        $this->assertEquals($run1->evaluation_hash, $run2->evaluation_hash);
    }

    public function test_84_2_risk_score_or_boundary_exceeded_fails_evaluation(): void
    {
        // 1. High risk buyer (> 0.35) fails
        $this->expectException(\RuntimeException::class);
        $this->service->runEvaluation(
            agent: $this->agent,
            tenderCode: 'TND-RISKY-01',
            commodityFeedPriceIdr: 800_000_000,
            logisticsCostIdr: 50_000_000,
            buyerRiskScore: 0.55 // > 0.35
        );
    }

    public function test_84_4_and_84_5_four_eyes_approval_mandatory_and_post_win_budget_commitment(): void
    {
        $run = $this->service->runEvaluation(
            agent: $this->agent,
            tenderCode: 'TND-EXPORT-INDIA-01',
            commodityFeedPriceIdr: 750_000_000,
            logisticsCostIdr: 100_000_000,
            buyerRiskScore: 0.15
        ); // Cost = 850m * 1.20 = 1,020,000,000 IDR

        $submission = $this->service->prepareSubmission($run);
        $this->assertEquals('PENDING_APPROVAL', $submission->status);
        $this->assertTrue($submission->requires_four_eyes);

        // Win without prior approval fails
        try {
            $this->service->recordWonAuction($submission);
            $this->fail('Cannot win unsubmitted bid');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Only submitted bids', $e->getMessage());
        }

        // Four-eyes approval by human manager
        $submitted = $this->service->approveAndSubmit($submission, approverUserId: 77);
        $this->assertEquals('SUBMITTED', $submitted->status);
        $this->assertEquals(77, $submitted->approved_by_user_id);
        $this->assertNotNull($submitted->approved_at);

        // Post-win tender award: issues contract & posts budget commitment
        $won = $this->service->recordWonAuction($submitted);
        $this->assertEquals('WON', $won->status);
        $this->assertNotNull($won->created_contract_code);

        // Check ledger: AR +1,020,000,000, Unearned Rev -1,020,000,000
        $ar = LedgerAccount::where('code', 'trd:tender_contract_ar:IDR')->first();
        $this->assertEquals('1020000000', (string) $ar->cached_balance);
    }
}
