<?php

namespace Modules\Agri\tests\Feature\Ndvi;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agri\Application\Services\Ndvi\PrecisionAgriAndDaoService;
use Modules\Agri\Domain\Models\Ndvi\AgriFinancingInstallment;
use Modules\Agri\Domain\Models\Ndvi\GovProposal;
use Modules\Agri\Domain\Models\Ndvi\GovVoterWeight;
use Modules\Banking\Domain\Models\LedgerAccount;
use Tests\TestCase;

class PrecisionAgriAndDaoGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected PrecisionAgriAndDaoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PrecisionAgriAndDaoService::class);

        LedgerAccount::create([
            'code' => 'agri:farmer_loan_ar:IDR',
            'name' => 'Agri Farmer Microfinance Receivable',
            'asset_code' => 'IDR',
            'kind' => 'asset',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'agri:escrow_disbursement:IDR',
            'name' => 'Agri Loan Escrow Disbursement Account',
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
    }

    public function test_86_1_and_86_2_satellite_ndvi_scan_and_conditional_loan_disbursement(): void
    {
        $scan = $this->service->recordSatelliteScan(
            plotCode: 'PLT-PLASMA-RIAU-01',
            scanDate: Carbon::parse('2026-10-15'),
            ndviScore: 0.745,
            polygonGeojson: ['type' => 'Polygon', 'coordinates' => [[[101.4, 0.5], [101.5, 0.5], [101.5, 0.6], [101.4, 0.6], [101.4, 0.5]]]]
        );

        $this->assertEquals('HEALTHY', $scan->crop_health_status);
        $this->assertEquals(0.745, (float) $scan->ndvi_score);

        // Create financing installment requiring min NDVI 0.650
        $installment = AgriFinancingInstallment::create([
            'installment_code' => 'INST-TR-01',
            'contract_farming_code' => 'CTR-FARM-991',
            'plot_code' => 'PLT-PLASMA-RIAU-01',
            'tranche_number' => 1,
            'amount_idr' => 25_000_000,
            'required_min_ndvi' => 0.650,
            'status' => 'PENDING_EVALUATION',
        ]);

        // 1. Evaluate with low NDVI (0.520 < 0.650) -> held with corrective plan
        $held = $this->service->evaluateAndDisburseInstallment($installment, 0.520);
        $this->assertEquals('HELD_CORRECTIVE_PLAN', $held->status);
        $this->assertNotNull($held->corrective_action_plan);

        // 2. Evaluate with healthy NDVI (0.745 >= 0.650) -> disbursed to farmer via ledger
        $held->update(['status' => 'PENDING_EVALUATION']); // retry after corrective fertigation
        $disbursed = $this->service->evaluateAndDisburseInstallment($held, 0.745);
        $this->assertEquals('DISBURSED', $disbursed->status);
        $this->assertNotNull($disbursed->disbursed_at);

        // Verify ledger balances
        $loanAr = LedgerAccount::where('code', 'agri:farmer_loan_ar:IDR')->first();
        $this->assertEquals('25000000', (string) $loanAr->cached_balance);
    }

    public function test_86_4_and_86_6_dao_voting_weight_hash_chain_and_auto_execution(): void
    {
        $proposal = GovProposal::create([
            'proposal_code' => 'PROP-2026-08',
            'title' => 'Open Cloud Kitchen Satellite in Surabaya East',
            'proposal_type' => 'EXPANSION_RESTO',
            'description' => 'Deploy CK-02 satellite node to cover 15km industrial radius',
            'action_payload' => ['target_city' => 'Surabaya', 'budget_idr' => 1_500_000_000],
            'quorum_weight_required' => 50_000,
            'status' => 'ACTIVE',
        ]);

        $voter1 = GovVoterWeight::create([
            'voter_user_id' => 1001,
            'voter_role' => 'RWA_HOLDER',
            'voting_weight' => 35_000,
            'passport_hash' => 'HASH-RWA-HOLDER-01',
        ]);

        $voter2 = GovVoterWeight::create([
            'voter_user_id' => 1002,
            'voter_role' => 'FRANCHISEE_RESTO',
            'voting_weight' => 25_000,
            'passport_hash' => 'HASH-FRANCHISEE-02',
        ]);

        // Voter 1 casts YES
        $vote1 = $this->service->castVote($proposal, $voter1, 'YES');
        $this->assertNotNull($vote1->vote_receipt_hash);

        // Duplicate vote by voter 1 rejected
        try {
            $this->service->castVote($proposal, $voter1, 'YES');
            $this->fail('Duplicate vote must be rejected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Duplicate vote rejected', $e->getMessage());
        }

        // Voter 2 casts YES -> hash chain links to voter 1 receipt
        $vote2 = $this->service->castVote($proposal, $voter2, 'YES');
        $this->assertEquals($vote1->vote_receipt_hash, $vote2->previous_vote_hash);

        // Finalize proposal: total YES = 60,000 >= quorum 50,000 -> APPROVED & Auto-executed
        $finalized = $this->service->finalizeAndExecuteProposal($proposal);
        $this->assertEquals('APPROVED', $finalized->status);
        $this->assertNotNull($finalized->execution_reference_code);
    }
}
