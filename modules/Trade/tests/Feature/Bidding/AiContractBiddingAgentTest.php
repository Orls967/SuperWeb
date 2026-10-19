<?php

namespace Modules\Trade\tests\Feature\Bidding;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Domain\Models\Role;
use Modules\Trade\Application\Services\Bidding\AiBiddingAgentService;
use Modules\Trade\Domain\Models\Bidding\BiddingAgent;
use Modules\Trade\Domain\Models\Bidding\BidRun;
use Modules\Trade\Domain\Models\Bidding\BidSubmission;
use Tests\TestCase;

class AiContractBiddingAgentTest extends TestCase
{
    use RefreshDatabase;

    protected AiBiddingAgentService $service;

    protected BiddingAgent $agent;

    protected User $admin;

    protected User $procurement;

    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->service = app(AiBiddingAgentService::class);

        Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'procurement'], ['label' => 'Procurement Officer', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'driver'], ['label' => 'Driver', 'is_system' => true]);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->procurement = User::factory()->create(['role' => 'procurement']);
        $this->driver = User::factory()->create(['role' => 'driver']);

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

        // Check ledger: AR -1,020,000,000, Unearned Rev +1,020,000,000
        $ar = LedgerAccount::where('code', 'trd:tender_contract_ar:IDR')->first();
        $this->assertEquals('-1020000000', (string) $ar->cached_balance);
    }

    public function test_84_3_and_84_6_lost_auction_feedback_and_bid_desk_summary(): void
    {
        $run = $this->service->runEvaluation(
            agent: $this->agent,
            tenderCode: 'TND-CEMENT-SUMATRA-01',
            commodityFeedPriceIdr: 700_000_000,
            logisticsCostIdr: 50_000_000,
            buyerRiskScore: 0.10
        );

        $submission = $this->service->prepareSubmission($run);
        $submitted = $this->service->approveAndSubmit($submission, approverUserId: 88);

        $lost = $this->service->recordLostAuction(
            submission: $submitted,
            feedbackReason: 'Kompetitor menurunkan harga sebesar 5% di putaran final'
        );

        $this->assertEquals('LOST', $lost->status);
        $this->assertEquals('Kompetitor menurunkan harga sebesar 5% di putaran final', $lost->feedback_reason);

        // Test desk summary calculation
        $summary = $this->service->getBidDeskSummary();
        $this->assertGreaterThanOrEqual(1, $summary['total_agents']);
        $this->assertGreaterThanOrEqual(1, $summary['total_evaluations']);
        $this->assertGreaterThanOrEqual(0, $summary['submitted_count']);
        $this->assertGreaterThanOrEqual(1, $summary['lost_count']);
        $this->assertIsFloat($summary['win_rate_percent']);
        $this->assertGreaterThanOrEqual(0, $summary['total_won_value_idr']);
    }

    public function test_84_audit_command_and_corruption_detection(): void
    {
        // 1. Clean audit passes
        $this->artisan('bidding:audit')->assertSuccessful();

        // 2. Corrupted record: WON without contract code
        $run = $this->service->runEvaluation(
            agent: $this->agent,
            tenderCode: 'TND-AUDIT-CORRUPT-01',
            commodityFeedPriceIdr: 700_000_000,
            logisticsCostIdr: 50_000_000,
            buyerRiskScore: 0.10
        );
        $badSubmission = BidSubmission::create([
            'submission_code' => 'SUB-CORRUPT-TEST',
            'bid_run_id' => $run->id,
            'submitted_bid_price_idr' => 900_000_000,
            'requires_four_eyes' => true,
            'status' => 'WON',
            'created_contract_code' => null, // violation!
        ]);

        $this->artisan('bidding:audit')
            ->assertFailed()
            ->expectsOutputToContain('bidding.won_missing_contract');

        // Cleanup
        $badSubmission->delete();
        $this->artisan('bidding:audit')->assertSuccessful();
    }

    public function test_84_http_endpoints_and_rbac(): void
    {
        // 1. Unauthorized driver receives 403
        $this->actingAs($this->driver)
            ->get(route('trade.bidding.index'))
            ->assertForbidden();

        // 2. Authorized admin accesses desk
        $res = $this->actingAs($this->admin)->get(route('trade.bidding.index'));
        $res->assertOk();
        $res->assertViewIs('trade::bidding');

        // 3. Create Agent POST
        $postAgent = $this->actingAs($this->procurement)->post(route('trade.bidding.agents.store'), [
            'agent_code' => 'AGT-HTTP-NICKEL-01',
            'entity_code' => 'TRD-GLOBAL-INDONESIA',
            'commodity_code' => 'NICKEL_ORE_18',
            'min_price_floor_idr' => 500_000_000,
            'max_price_ceiling_idr' => 1_200_000_000,
            'target_margin_pct' => 18.0,
            'max_risk_score' => 0.30,
        ]);
        $postAgent->assertRedirect();
        $agent = BiddingAgent::where('agent_code', 'AGT-HTTP-NICKEL-01')->first();
        $this->assertNotNull($agent);

        // 4. Evaluate Run POST
        $postRun = $this->actingAs($this->procurement)->post(route('trade.bidding.runs.evaluate'), [
            'bidding_agent_id' => $agent->id,
            'tender_reference_code' => 'TND-HTTP-EVAL-01',
            'commodity_feed_price_idr' => 600_000_000,
            'estimated_logistics_cost_idr' => 40_000_000,
            'buyer_risk_score' => 0.15,
        ]);
        $postRun->assertRedirect();
        $run = BidRun::where('tender_reference_code', 'TND-HTTP-EVAL-01')->first();
        $this->assertNotNull($run);

        // 5. Prepare Submission POST
        $postPrepare = $this->actingAs($this->procurement)->post(route('trade.bidding.runs.prepare', $run));
        $postPrepare->assertRedirect();
        $sub = BidSubmission::where('bid_run_id', $run->id)->first();
        $this->assertNotNull($sub);
        $this->assertEquals('PENDING_APPROVAL', $sub->status);

        // 6. Approve Submission POST (Four-eyes)
        $postApprove = $this->actingAs($this->admin)->post(route('trade.bidding.submissions.approve', $sub));
        $postApprove->assertRedirect();
        $this->assertEquals('SUBMITTED', $sub->fresh()->status);

        // 7. Record Result POST (WON)
        $postResult = $this->actingAs($this->procurement)->post(route('trade.bidding.submissions.result', $sub), [
            'result' => 'WON',
        ]);
        $postResult->assertRedirect();
        $this->assertEquals('WON', $sub->fresh()->status);
        $this->assertNotNull($sub->fresh()->created_contract_code);
    }
}
