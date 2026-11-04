<?php

declare(strict_types=1);

namespace Tests\Acceptance\BidAssistant;

use App\Models\User;
use Database\Seeders\BidAssistantT1Seeder;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Contract\Contracts\ClauseTemplateReader;
use Modules\Contract\Domain\Models\ClauseTemplate;
use Modules\Core\Domain\Models\Role;
use Modules\Trade\Application\Services\Bidding\AiBiddingAgentService;
use Modules\Trade\Domain\Models\Bidding\BiddingAgent;
use Modules\Trade\Domain\Models\Bidding\BidRun;
use Modules\Trade\Domain\Models\Bidding\BidSubmission;
use Tests\TestCase;

/**
 * Acceptance Test Fase 84 — AI Contract Bidding Agent & Tender Assistant (KONSEP §7F Slice 3)
 */
class BidAssistantAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected AiBiddingAgentService $service;

    protected BiddingAgent $agent;

    protected User $adminUser;

    protected User $procurementUser;

    protected User $managerUser;

    protected User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->service = app(AiBiddingAgentService::class);

        Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'procurement'], ['label' => 'Procurement Officer', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'procurement_manager'], ['label' => 'Procurement Manager', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'driver'], ['label' => 'Driver', 'is_system' => true]);

        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->procurementUser = User::factory()->create(['role' => 'procurement']);
        $this->managerUser = User::factory()->create(['role' => 'procurement_manager']);
        $this->unauthorizedUser = User::factory()->create(['role' => 'driver']);

        $this->agent = $this->service->createAgent(
            agentCode: 'AGT-TND-ACCEPTANCE-01',
            entityCode: 'TRD-GLOBAL-INDONESIA',
            commodityCode: 'THERMAL_COAL_GAR5000',
            minPriceFloorIdr: 900_000_000,
            maxPriceCeilingIdr: 1_500_000_000,
            targetMarginPct: 15.0,
            maxRiskScore: 0.35
        );

        // Siapkan template klausul di modul Contract untuk kontrak K13 ClauseTemplateReader
        ClauseTemplate::firstOrCreate(
            ['code' => 'CTR-PAY-LC-ACCEPTANCE'],
            [
                'title' => 'Letter of Credit 30 Days',
                'category' => 'COMMERCIAL_DEFAULT',
                'body_template' => 'Pembayaran melalui Sight L/C 30 hari kalender setelah verifikasi dokumen perkapalan.',
                'version' => 1,
                'is_active' => true,
                'dispute_count' => 0,
            ]
        );
        ClauseTemplate::firstOrCreate(
            ['code' => 'CTR-DEL-FOB-ACCEPTANCE'],
            [
                'title' => 'Incoterms 2020 FOB Port',
                'category' => 'COMMERCIAL_DEFAULT',
                'body_template' => 'Penyerahan komoditas secara FOB pelabuhan muat yang disepakati.',
                'version' => 1,
                'is_active' => true,
                'dispute_count' => 0,
            ]
        );
    }

    /**
     * Skenario 1: Konfigurasi agent dan penegakan batasan harga agen
     */
    public function test_84_1_bidding_agent_configuration_and_boundaries(): void
    {
        $this->assertEquals('AGT-TND-ACCEPTANCE-01', $this->agent->agent_code);
        $this->assertEquals(900_000_000, $this->agent->min_price_floor_idr);
        $this->assertEquals(1_500_000_000, $this->agent->max_price_ceiling_idr);
        $this->assertEquals('ACTIVE', $this->agent->status);

        // Buyer dengan risiko melebihi batas (0.55 > 0.35) ditolak
        $this->expectException(\RuntimeException::class);
        $this->service->runEvaluation(
            agent: $this->agent,
            tenderCode: 'TND-RISK-HIGH-01',
            commodityFeedPriceIdr: 800_000_000,
            logisticsCostIdr: 100_000_000,
            buyerRiskScore: 0.55
        );
    }

    /**
     * Skenario 2: Evaluasi optimasi penawaran deterministik & integrasi draf klausul K13
     */
    public function test_84_2_deterministic_optimizer_evaluates_bid_and_clause_drafting(): void
    {
        $run1 = $this->service->runEvaluation(
            agent: $this->agent,
            tenderCode: 'TND-PLN-ACCEPTANCE-2026',
            commodityFeedPriceIdr: 850_000_000,
            logisticsCostIdr: 50_000_000,
            buyerRiskScore: 0.20
        );

        $this->assertNotNull($run1->optimal_bid_price_idr);
        $this->assertGreaterThanOrEqual($this->agent->min_price_floor_idr, $run1->optimal_bid_price_idr);
        $this->assertLessThanOrEqual($this->agent->max_price_ceiling_idr, $run1->optimal_bid_price_idr);
        $this->assertArrayHasKey('payment_terms', $run1->clause_drafts);
        $this->assertNotEmpty($run1->evaluation_hash);

        // Replay deterministik: masukan sama menghasilkan kode dan hash evaluasi identik
        $run2 = $this->service->runEvaluation(
            agent: $this->agent,
            tenderCode: 'TND-PLN-ACCEPTANCE-2026',
            commodityFeedPriceIdr: 850_000_000,
            logisticsCostIdr: 50_000_000,
            buyerRiskScore: 0.20
        );

        $this->assertEquals($run1->optimal_bid_price_idr, $run2->optimal_bid_price_idr);
        $this->assertEquals($run1->evaluation_hash, $run2->evaluation_hash);
    }

    /**
     * Skenario 3: Penawaran tanpa approval berstatus PENDING_APPROVAL dan ditolak untuk submit
     */
    public function test_84_3_unapproved_submission_is_blocked_from_submission(): void
    {
        $run = $this->service->runEvaluation(
            agent: $this->agent,
            tenderCode: 'TND-BLOCK-TEST-01',
            commodityFeedPriceIdr: 800_000_000,
            logisticsCostIdr: 100_000_000,
            buyerRiskScore: 0.15
        );

        $submission = $this->service->prepareSubmission($run);
        $this->assertEquals('PENDING_APPROVAL', $submission->status);
        $this->assertTrue($submission->requires_four_eyes);

        // Menandai penawaran menang tanpa approval sebelumnya harus gagal
        $this->expectException(\RuntimeException::class);
        $this->service->recordWonAuction($submission);
    }

    /**
     * Skenario 4: Persetujuan four-eyes oleh procurement manager mengotorisasi penawaran
     */
    public function test_84_4_four_eyes_approval_authorizes_submission(): void
    {
        $run = $this->service->runEvaluation(
            agent: $this->agent,
            tenderCode: 'TND-FOUREYES-01',
            commodityFeedPriceIdr: 800_000_000,
            logisticsCostIdr: 100_000_000,
            buyerRiskScore: 0.15
        );

        $submission = $this->service->prepareSubmission($run, $this->procurementUser->id);

        // Pengusul = penyetuju harus ditolak oleh four-eyes guard
        try {
            $this->service->approveAndSubmit(
                submission: $submission,
                approverUserId: $this->procurementUser->id
            );
            $this->fail('Seharusnya gagal ketika pengusul mencoba menyetujui penawarannya sendiri.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Four-Eyes', $e->getMessage());
        }

        // Approval oleh procurement manager (berbeda user) berhasil
        $submitted = $this->service->approveAndSubmit(
            submission: $submission,
            approverUserId: $this->managerUser->id
        );

        $this->assertEquals('SUBMITTED', $submitted->status);
        $this->assertEquals($this->managerUser->id, $submitted->approved_by_user_id);
        $this->assertNotNull($submitted->approved_at);

        // RBAC endpoint /procurement/bid-assistant: procurement 200, driver 403
        $this->actingAs($this->procurementUser)
            ->get(route('procurement.bid-assistant.index'))
            ->assertOk()
            ->assertSee('Asisten Penawaran Tender');

        $this->actingAs($this->unauthorizedUser)
            ->get(route('procurement.bid-assistant.index'))
            ->assertForbidden();
    }

    /**
     * Skenario 5: Penawaran menang menerbitkan kontrak dan komitmen anggaran di ledger; kalah mencatat alasan
     */
    public function test_84_5_won_auction_issues_contract_and_ledger_commitment(): void
    {
        $run = $this->service->runEvaluation(
            agent: $this->agent,
            tenderCode: 'TND-POSTWIN-01',
            commodityFeedPriceIdr: 800_000_000,
            logisticsCostIdr: 100_000_000,
            buyerRiskScore: 0.10
        );

        $submission = $this->service->prepareSubmission($run);
        $submitted = $this->service->approveAndSubmit($submission, $this->managerUser->id);

        $won = $this->service->recordWonAuction($submitted);
        $this->assertEquals('WON', $won->status);
        $this->assertNotNull($won->created_contract_code);
        $this->assertStringStartsWith('CTR-TND-', $won->created_contract_code);

        // Ledger terakru: tender_contract_ar debit (negatif) dan tender_revenue_unearned kredit (positif)
        $ar = LedgerAccount::where('code', 'trd:tender_contract_ar:IDR')->first();
        $this->assertNotNull($ar);
        $this->assertEquals(-$submitted->submitted_bid_price_idr, (int) $ar->cached_balance);

        // Penawaran kedua kalah: mencatat feedback penyebab kekalahan
        $run2 = $this->service->runEvaluation(
            agent: $this->agent,
            tenderCode: 'TND-POSTLOST-01',
            commodityFeedPriceIdr: 800_000_000,
            logisticsCostIdr: 100_000_000,
            buyerRiskScore: 0.10
        );
        $submission2 = $this->service->prepareSubmission($run2);
        $submitted2 = $this->service->approveAndSubmit($submission2, $this->managerUser->id);

        $lost = $this->service->recordLostAuction(
            submission: $submitted2,
            feedbackReason: 'Penawaran kalah harga 2% dari kompetitor luar negeri'
        );
        $this->assertEquals('LOST', $lost->status);
        $this->assertEquals('Penawaran kalah harga 2% dari kompetitor luar negeri', $lost->feedback_reason);
    }

    /**
     * Skenario 6: Command bidding:audit memverifikasi integritas dua sumber dan mendeteksi anomali korupsi
     */
    public function test_84_6_bidding_audit_verifies_two_sources_and_detects_corruption(): void
    {
        // Audit bersih lulus 0 temuan
        $this->artisan('bidding:audit')->assertSuccessful();

        // Simulasi anomali: status WON tanpa kode kontrak
        $run = $this->service->runEvaluation(
            agent: $this->agent,
            tenderCode: 'TND-CORRUPT-TEST-01',
            commodityFeedPriceIdr: 800_000_000,
            logisticsCostIdr: 50_000_000,
            buyerRiskScore: 0.10
        );
        $corruptSub = BidSubmission::create([
            'submission_code' => 'SUB-CORRUPT-ACCEPTANCE',
            'bid_run_id' => $run->id,
            'submitted_bid_price_idr' => 1_000_000_000,
            'requires_four_eyes' => true,
            'status' => 'WON',
            'approved_by_user_id' => $this->managerUser->id,
            'approved_at' => now(),
            'created_contract_code' => null, // Korupsi: status WON tanpa kode kontrak
        ]);

        $this->artisan('bidding:audit')
            ->assertFailed()
            ->expectsOutputToContain('bidding.won_missing_contract_code');

        $corruptSub->delete();
        $this->artisan('bidding:audit')->assertSuccessful();

        // Simulasi T1 Seeder (50 tender, 500 evaluasi, 100 klausul) + audit tetap 0 selisih
        $this->seed(BidAssistantT1Seeder::class);
        $this->assertGreaterThanOrEqual(500, BidRun::count());
        $this->artisan('bidding:audit')->assertSuccessful();
    }
}
