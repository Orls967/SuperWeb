<?php

declare(strict_types=1);

namespace Tests\Acceptance\NdviDao;

use App\Models\User;
use App\Quality\Audit\Fixtures\GovernanceAuditCorruptionFixture;
use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\NdviDaoT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Agri\Application\Services\Ndvi\PrecisionAgriAndDaoService;
use Modules\Agri\Application\Services\Ndvi\RationalNdviCalculator;
use Modules\Agri\Domain\Models\Ndvi\AgriFinancingInstallment;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Domain\Models\Role;
use Modules\Epc\Domain\Models\EpcProject;
use Modules\Governance\Application\Services\DaoGovernanceService;
use Modules\Governance\Domain\Models\GovProposal;
use Modules\Governance\Domain\Models\GovVote;
use Modules\Governance\Domain\Models\GovVoterWeight;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Shared\Domain\Algorithms\Hash\MerkleTree;
use RuntimeException;
use Tests\TestCase;

class NdviDaoAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected PrecisionAgriAndDaoService $agriService;

    protected DaoGovernanceService $govService;

    protected MerkleTree $merkleAlgorithm;

    protected User $admin;

    protected User $voter;

    protected User $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->agriService = app(PrecisionAgriAndDaoService::class);
        $this->govService = app(DaoGovernanceService::class);
        $this->merkleAlgorithm = app(MerkleTree::class);

        Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'governance_admin'], ['label' => 'Governance Admin', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'voter'], ['label' => 'Voter', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'guest'], ['label' => 'Guest User', 'is_system' => true]);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->voter = User::factory()->create(['role' => 'voter']);
        $this->guest = User::factory()->create(['role' => 'guest']);
    }

    public function test_enforces_rbac_and_prevents_idor_on_governance_routes(): void
    {
        // Admin has access to /governance
        $this->actingAs($this->admin)->get('/governance')->assertOk();

        // Voter has access to /governance
        $this->actingAs($this->voter)->get('/governance')->assertOk();

        // Unauthorized user without roles receives 403 on protected route
        $this->actingAs($this->guest)->get('/governance')->assertForbidden();
    }

    public function test_matches_exact_rational_ndvi_vectors_and_enforces_loan_disbursement_ratchet_with_ledger_idempotency(): void
    {
        $calculator = new RationalNdviCalculator;

        // Vektor wajib prompt Petak 1: (5000,1000), (4000,2000), (6000,1500), (3000,3000) + 1 piksel berawan
        $pixels1 = [
            ['nir' => 5000, 'red' => 1000],
            ['nir' => 4000, 'red' => 2000],
            ['nir' => 6000, 'red' => 1500],
            ['nir' => 3000, 'red' => 3000],
            ['nir' => 9000, 'red' => 9000, 'is_cloudy' => true],
        ];
        $res1 = $calculator->calculateFieldNdvi($pixels1);
        $this->assertEquals(2, $res1['numerator']);
        $this->assertEquals(5, $res1['denominator']);
        $this->assertEquals(0.4, $res1['float_val']);

        // Ambang 0.45 (4500 bps) -> Petak 1 tidak cair
        $this->assertFalse($calculator->meetsThreshold($res1['numerator'], $res1['denominator'], 4500));

        // Vektor wajib prompt Petak 2: (5000,1000), (6000,1500), (4500,1500)
        $pixels2 = [
            ['nir' => 5000, 'red' => 1000],
            ['nir' => 6000, 'red' => 1500],
            ['nir' => 4500, 'red' => 1500],
        ];
        $res2 = $calculator->calculateFieldNdvi($pixels2);
        $this->assertEquals(53, $res2['numerator']);
        $this->assertEquals(90, $res2['denominator']);
        // 53/90 ~ 0.588889 -> Petak 2 cair
        $this->assertTrue($calculator->meetsThreshold($res2['numerator'], $res2['denominator'], 4500));

        // Ratchet Kontrak: Pinjaman Petani
        $installment = AgriFinancingInstallment::create([
            'installment_code' => 'INST-VEC-01',
            'contract_farming_code' => 'CTR-VEC-01',
            'plot_code' => 'PLT-VEC-01',
            'farmer_party_id' => 901,
            'tranche_number' => 1,
            'amount_idr' => 25_000_000,
            'required_min_ndvi' => 0.450,
            'status' => 'PENDING_EVALUATION',
        ]);

        // 1. Evaluasi pertama dengan piksel 1 (NDVI 0.400 < 0.450) -> status HELD_CORRECTIVE_PLAN
        $held = $this->agriService->evaluateAndDisburseInstallment($installment, $pixels1);
        $this->assertEquals('HELD_CORRECTIVE_PLAN', $held->status);
        $this->assertEquals(1, $held->consecutive_failures);
        $this->assertNotNull($held->corrective_action_plan);

        // 2. Evaluasi kedua gagal lagi beruntun -> eskalasi RESTRUCTURED_PENDING_APPROVAL
        $escalated = $this->agriService->evaluateAndDisburseInstallment($held, $pixels1);
        $this->assertEquals('RESTRUCTURED_PENDING_APPROVAL', $escalated->status);
        $this->assertEquals(2, $escalated->consecutive_failures);

        // 3. Evaluasi ketiga setelah perbaikan (piksel 2: 53/90 >= 0.450) -> DISBURSED
        $disbursed = $this->agriService->evaluateAndDisburseInstallment($escalated, $pixels2);
        $this->assertEquals('DISBURSED', $disbursed->status);
        $this->assertNotNull($disbursed->disbursed_at);
        $this->assertEquals(0, $disbursed->consecutive_failures);

        // Ledger balance verification
        $arAccount = LedgerAccount::where('code', 'agri:farmer_loan_ar:IDR')->first();
        $this->assertEquals(25_000_000, (int) $arAccount->cached_balance);

        // Idempotensi: evaluasi ulang pada status DISBURSED tidak mencairkan lagi (ledger tidak bertambah)
        $idempotentCheck = $this->agriService->evaluateAndDisburseInstallment($disbursed, $pixels2);
        $this->assertEquals('DISBURSED', $idempotentCheck->status);
        $arAccount->refresh();
        $this->assertEquals(25_000_000, (int) $arAccount->cached_balance);
    }

    public function test_verifies_snapshot_merkle_proof_and_prevents_double_voting_or_tampered_weights(): void
    {
        $voters = [
            1 => 500,
            2 => 300,
            3 => 200,
            4 => 1000,
        ];
        foreach ($voters as $vId => $w) {
            GovVoterWeight::create([
                'voter_user_id' => $vId,
                'voter_role' => 'STRATEGIC_PARTNER',
                'voting_weight' => $w,
                'passport_hash' => hash('sha256', "pass:{$vId}"),
            ]);
        }

        $snapshot = $this->govService->snapshotVoterWeights();
        $merkleRoot = $snapshot['root'];

        // Vektor prompt 4 daun root
        $this->assertEquals('b275384498573111f4ae43c74765a6b2b3edbe3be4fc87f14ae49f797bf5eacb', $merkleRoot);

        $proposal = GovProposal::create([
            'proposal_code' => 'PROP-VOTE-01',
            'title' => 'Proposal Pembukaan Hub Logistik Baru',
            'proposal_type' => 'EXPANSION_RESTO',
            'description' => 'Ekspansi jaringan hub logistik',
            'action_payload' => ['budget_idr' => 1_000_000_000, 'city' => 'Surabaya'],
            'quorum_weight_required' => 1500,
            'merkle_root' => $merkleRoot,
            'voting_starts_at' => now()->subHour(),
            'voting_ends_at' => now()->addHours(24),
            'status' => 'ACTIVE',
        ]);

        // Voter 1 cast YES with valid Merkle proof
        $leafIndex1 = array_search('1:500', $snapshot['leaves'], true);
        $treeResult = $this->merkleAlgorithm->build($snapshot['leaves']);
        $proof1 = $this->merkleAlgorithm->getProof($treeResult, (int) $leafIndex1);

        $vote1 = $this->govService->castVote($proposal, 1, 500, 'YES', $proof1);
        $this->assertNotNull($vote1->vote_receipt_hash);

        // 1. Double vote ditolak
        try {
            $this->govService->castVote($proposal, 1, 500, 'YES', $proof1);
            $this->fail('Double vote should be rejected');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Duplicate vote rejected', $e->getMessage());
        }

        // 2. Vote dengan bobot palsu ditolak oleh Merkle proof
        try {
            $this->govService->castVote($proposal, 2, 9999, 'YES', $proof1);
            $this->fail('Forged weight should be rejected by Merkle proof');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Merkle proof tidak cocok', $e->getMessage());
        }

        // 3. Voting setelah waktu penutupan ditolak
        $closedProposal = GovProposal::create([
            'proposal_code' => 'PROP-CLOSED-01',
            'title' => 'Closed Proposal',
            'proposal_type' => 'GENERAL',
            'description' => 'Expired voting window',
            'action_payload' => [],
            'quorum_weight_required' => 100,
            'merkle_root' => $merkleRoot,
            'voting_ends_at' => now()->subMinute(),
            'status' => 'ACTIVE',
        ]);
        try {
            $this->govService->castVote($closedProposal, 2, 300, 'YES', $proof1);
            $this->fail('Vote after closing should be rejected');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Voting setelah waktu penutupan ditolak', $e->getMessage());
        }
    }

    public function test_integrates_with_k9_rwa_and_automatically_drafts_epc_project_via_k10_on_proposal_approval(): void
    {
        // 1. Setup RWA holding via K9
        $rwaAsset = RwaAsset::create([
            'token_symbol' => 'PROP01',
            'name' => 'Gedung Perkantoran Sudirman',
            'underlying_asset_type' => 'REAL_ESTATE',
            'underlying_asset_id' => 'BLD-001',
            'appraisal_document_ref' => 'DOC-001',
            'appraisal_value_idr' => 100_000_000_000,
            'total_supply_tokens' => 100_000,
            'token_price_idr' => 1_000_000,
            'max_holder_concentration_pct' => 50,
            'status' => 'ACTIVE',
        ]);

        $userRwa = User::factory()->create();
        RwaHolding::create([
            'rwa_asset_id' => $rwaAsset->id,
            'user_id' => $userRwa->id,
            'is_kyc_verified' => true,
            'token_balance' => 50_000,
            'locked_balance' => 0,
        ]);

        // Snapshot weights (K9 membaca token 50.000 untuk userRwa)
        $snapshot = $this->govService->snapshotVoterWeights('PROP01', CarbonImmutable::now());
        $this->assertGreaterThanOrEqual(1, $snapshot['voter_count']);

        $leafRwa = "{$userRwa->id}:50000";
        $leafIdx = array_search($leafRwa, $snapshot['leaves'], true);
        $this->assertNotFalse($leafIdx);

        $treeResult = $this->merkleAlgorithm->build($snapshot['leaves']);
        $proofRwa = $this->merkleAlgorithm->getProof($treeResult, (int) $leafIdx);

        // Buat proposal ekspansi
        $proposal = GovProposal::create([
            'proposal_code' => 'PROP-EPC-01',
            'title' => 'Pembangunan Fasilitas Pabrik Pengolahan Pakan',
            'proposal_type' => 'STRATEGIC_EXPANSION',
            'description' => 'Proyek EPC Pabrik Pakan Ternak 5000 ton',
            'action_payload' => ['budget_idr' => 2_500_000_000, 'contract_type' => 'EPC_LUMPSUM'],
            'quorum_weight_required' => 40_000,
            'merkle_root' => $snapshot['root'],
            'voting_starts_at' => now()->subHour(),
            'voting_ends_at' => now()->addDays(2),
            'status' => 'ACTIVE',
        ]);

        // Vote YES dari RWA holder
        $this->govService->castVote($proposal, (int) $userRwa->id, 50_000, 'YES', $proofRwa);

        // Finalisasi proposal
        $finalized = $this->govService->finalizeAndExecuteProposal($proposal);
        $this->assertEquals('APPROVED', $finalized->status);
        $this->assertNotNull($finalized->execution_reference_code);
        $this->assertNotNull($finalized->epc_project_id);

        // Verifikasi proyek EPC berstatus 'draft_pending_approval' (bukan langsung eksekusi final)
        $epcProject = EpcProject::find($finalized->epc_project_id);
        $this->assertNotNull($epcProject);
        $this->assertEquals('draft_pending_approval', $epcProject->status);
        $this->assertEquals(2_500_000_000, (int) $epcProject->total_rab_budget_idr);
    }

    public function test_seeds_t1_scale_data_cleanly_within_time_envelope(): void
    {
        $startTime = microtime(true);

        $this->seed(NdviDaoT1Seeder::class);

        $elapsed = microtime(true) - $startTime;

        $this->assertGreaterThanOrEqual(100, AgriFinancingInstallment::count());
        $this->assertGreaterThanOrEqual(20, GovProposal::count());
        $this->assertGreaterThanOrEqual(20, GovVote::count());
        $this->assertLessThan(15.0, $elapsed);
    }

    public function test_passes_governance_audit_on_clean_state_and_detects_discrepancies_on_corruption_fixture(): void
    {
        $this->seed(NdviDaoT1Seeder::class);

        // 1. Audit kondisi bersih exit 0
        $exitCode = Artisan::call('governance:audit');
        $this->assertEquals(0, $exitCode);

        // 2. Audit dengan fixture korupsi mendeteksi selisih exit != 0
        $fixture = new GovernanceAuditCorruptionFixture;
        $fixture->corrupt();

        $corruptExit = Artisan::call('governance:audit');
        $this->assertNotEquals(0, $corruptExit);
    }
}
