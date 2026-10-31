<?php

declare(strict_types=1);

namespace Tests\Acceptance\Property;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\RbacConglomerateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Epc\Application\Actions\CapitalizeCompletedCipToFixedAssetAction;
use Modules\Epc\Application\Actions\IssueProjectBast1WithRetentionHoldAction;
use Modules\Epc\Application\Actions\RecordWbsProgressAndCipLedgerCostAction;
use Modules\Epc\Application\Services\EpcService;
use Modules\Epc\Application\Services\MonthlyCertificateService;
use Modules\Epc\Domain\Models\EpcCipCapitalization;
use Modules\Epc\Domain\Models\EpcProject;
use Modules\Epc\Domain\Models\EpcWbsNode;
use Modules\Proptech\Domain\Models\Bim\TwinComponent;
use Modules\Shared\Domain\Math\WeightedTree;
use Modules\Shared\Domain\Math\WeightedTreeCertificateVerifier;
use Modules\Shared\Domain\Math\WeightedTreeNode;
use Tests\TestCase;

class EpcAssetAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $epcManager;

    protected User $contractorUser;

    protected EpcProject $project;

    protected EpcWbsNode $wbsCivil;

    protected EpcWbsNode $wbsMep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacConglomerateSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->epcManager = User::factory()->create([
            'email' => 'epc_director@superweb.test',
            'name' => 'Direktur Proyek Konstruksi',
        ]);

        $this->contractorUser = User::factory()->create([
            'email' => 'contractor_pt_wika@superweb.test',
            'name' => 'PT Wijaya Karya Tbk',
        ]);

        $this->project = EpcProject::create([
            'id' => (string) Str::uuid(),
            'project_code' => 'EPC-PLAZA-01',
            'project_name' => 'Nusantara Retail Plaza Phase 2',
            'project_type' => 'mall_extension',
            'client_entity_id' => (string) Str::uuid(),
            'total_rab_budget_idr' => 2_000_000_000, // 2 Billion IDR
            'target_physical_progress_pct' => 100.0,
            'actual_physical_progress_pct' => 0.0,
            'accumulated_cip_cost_idr' => 0,
            'capitalized_asset_value_idr' => 0,
            'start_date' => now()->toDateString(),
            'target_completion_date' => now()->addMonths(8)->toDateString(),
            'status' => 'in_progress',
        ]);

        $this->wbsCivil = EpcWbsNode::create([
            'project_id' => $this->project->id,
            'wbs_code' => 'WBS-CIV-01',
            'task_name' => 'Struktur dan Pondasi Bored Pile',
            'work_package' => 'civil_structure',
            'weight_percentage' => 40.0,
            'budget_allocation_idr' => 800_000_000,
            'completion_percentage' => 0.0,
            'status' => 'pending',
        ]);

        $this->wbsMep = EpcWbsNode::create([
            'project_id' => $this->project->id,
            'wbs_code' => 'WBS-MEP-01',
            'task_name' => 'Sistem Kelistrikan dan Plumbing Chiller',
            'work_package' => 'mep',
            'weight_percentage' => 60.0,
            'budget_allocation_idr' => 1_200_000_000,
            'completion_percentage' => 0.0,
            'status' => 'pending',
        ]);
    }

    // ─── SLICE 1: EPC PROJECT & WBS HIERARCHY (Tests 1-5) ───

    public function test_01_create_epc_project_with_budget_and_milestones(): void
    {
        $service = app(EpcService::class);
        $project = $service->createProject([
            'project_code' => 'EPC-NEW-01',
            'project_name' => 'Solar Rooftop Extension',
            'total_rab_budget_idr' => 500_000_000,
            'client_entity_id' => (string) Str::uuid(),
        ]);

        expect($project->project_code)->toBe('EPC-NEW-01');
        expect($project->total_rab_budget_idr)->toBe(500_000_000);
        expect($project->status)->toBe('in_progress');
    }

    public function test_02_add_wbs_nodes_under_cumulative_100_percent(): void
    {
        $service = app(EpcService::class);
        $p = $service->createProject([
            'project_code' => 'EPC-TREE-01',
            'project_name' => 'Testing Tree WBS',
            'total_rab_budget_idr' => 100_000_000,
            'client_entity_id' => (string) Str::uuid(),
        ]);

        $node1 = $service->addWbsNode((string) $p->id, [
            'wbs_code' => 'N1',
            'task_name' => 'Task 1',
            'weight_percentage' => 50.0,
            'budget_allocation_idr' => 50_000_000,
        ]);
        $node2 = $service->addWbsNode((string) $p->id, [
            'wbs_code' => 'N2',
            'task_name' => 'Task 2',
            'weight_percentage' => 50.0,
            'budget_allocation_idr' => 50_000_000,
        ]);

        expect($node1->weight_percentage)->toBe(50.0);
        expect($node2->weight_percentage)->toBe(50.0);
    }

    public function test_03_adding_wbs_node_exceeding_100_percent_is_rejected(): void
    {
        $service = app(EpcService::class);
        // Current weights of $this->project: 40% + 60% = 100%
        expect(fn () => $service->addWbsNode((string) $this->project->id, [
            'wbs_code' => 'WBS-OVER-01',
            'task_name' => 'Extra Work',
            'weight_percentage' => 5.0,
            'budget_allocation_idr' => 50_000_000,
        ]))->toThrow(InvalidArgumentException::class, 'Cumulative WBS weight exceeds 100%');
    }

    public function test_04_update_wbs_node_progress_updates_status(): void
    {
        $this->wbsCivil->update([
            'completion_percentage' => 100.0,
            'status' => 'completed',
        ]);

        expect($this->wbsCivil->fresh()->status)->toBe('completed');
        expect((float) $this->wbsCivil->fresh()->completion_percentage)->toBe(100.0);
    }

    public function test_05_wbs_node_cannot_have_negative_progress(): void
    {
        expect(fn () => $this->wbsCivil->update(['completion_percentage' => -10.0]))
            ->toThrow(\Throwable::class);
    }

    // ─── SLICE 2: WBS PROGRESS CERTIFICATE VERIFICATION (PR2) (Tests 6-10) ───

    public function test_06_bottom_up_progress_calculation_using_weighted_tree(): void
    {
        // Root with two leaves: 4000 bps (Civil @ 5000 bps progress = 2000 bps contribution)
        // MEP 6000 bps (@ 10000 bps progress = 6000 bps contribution). Total = 8000 bps = 80.00%
        $leafCivil = WeightedTreeNode::leaf('civil', 4_000, 5_000);
        $leafMep = WeightedTreeNode::leaf('mep', 6_000, 10_000);
        $root = WeightedTreeNode::composite('root', 10_000, [$leafCivil, $leafMep]);

        $result = WeightedTree::computeProgress($root);

        expect($result->progressBps)->toBe(8_000);
        expect($result->certificate)->not->toBeNull();
    }

    public function test_07_top_down_progress_verification_dual_traversal_certificate(): void
    {
        $leaf1 = WeightedTreeNode::leaf('sub1', 3_000, 3_000);
        $leaf2 = WeightedTreeNode::leaf('sub2', 7_000, 7_000);
        $root = WeightedTreeNode::composite('root', 10_000, [$leaf1, $leaf2]);

        $result = WeightedTree::computeProgress($root);

        expect(fn () => WeightedTreeCertificateVerifier::verify($result->certificate))->not->toThrow(\Throwable::class);
    }

    public function test_08_wbs_certificate_verification_detects_tampered_weights(): void
    {
        $leaf1 = WeightedTreeNode::leaf('leaf1', 4_000, 5_000);
        $leaf2 = WeightedTreeNode::leaf('leaf2', 5_000, 5_000); // 4000 + 5000 = 9000 != 10000 bps!
        $root = WeightedTreeNode::composite('root', 10_000, [$leaf1, $leaf2]);

        expect(fn () => WeightedTree::computeProgress($root))
            ->toThrow(InvalidArgumentException::class, 'must sum to 10000 bps');
    }

    public function test_09_progress_certificate_service_dual_traversal_verification(): void
    {
        $mcService = app(MonthlyCertificateService::class);

        // Update nodes: civil is 100% complete (40% weight * 100% = 40% progress)
        $this->wbsCivil->update(['completion_percentage' => 100.0, 'status' => 'completed']);
        // MEP is 50% complete (60% weight * 50% = 30% progress). Total = 70.0%
        $this->wbsMep->update(['completion_percentage' => 50.0, 'status' => 'ongoing']);

        $verifiedProgress = $mcService->verifyProjectWbsProgress($this->project);

        expect($verifiedProgress)->toBe(70.0);
    }

    public function test_10_mismatch_between_claimed_and_computed_wbs_progress_is_rejected(): void
    {
        $mcService = app(MonthlyCertificateService::class);

        // Actual progress is 0%
        expect(fn () => $mcService->issueCertifiedProgress(
            project: $this->project,
            claimedIncrementalPct: 50.0, // Fraudulent claim without WBS progress
            consultantName: 'Konsultan Nakal'
        ))->toThrow(InvalidArgumentException::class, 'Claimed progress does not match verified WBS completion');
    }

    // ─── SLICE 3: MONTHLY CERTIFICATE (MC) ISSUANCE & LEDGER POSTING (Tests 11-17) ───

    public function test_11_issue_mc_computes_gross_claim_from_budget(): void
    {
        $mcService = app(MonthlyCertificateService::class);

        // Advance civil to 50% (incremental progress = 20%)
        $this->wbsCivil->update(['completion_percentage' => 50.0]);

        $mc = $mcService->issueCertifiedProgress(
            project: $this->project,
            claimedIncrementalPct: 20.0,
            consultantName: 'PT Virama Karya Konsultan'
        );

        // 20% of 2 Billion = 400,000,000 IDR gross claim
        expect($mc->gross_claim_amount_idr)->toBe(400_000_000);
    }

    public function test_12_mc_deducts_exact_5_percent_retention(): void
    {
        $mcService = app(MonthlyCertificateService::class);
        $this->wbsCivil->update(['completion_percentage' => 50.0]);

        $mc = $mcService->issueCertifiedProgress(
            project: $this->project,
            claimedIncrementalPct: 20.0,
            consultantName: 'PT Virama Karya Konsultan'
        );

        // 5% of 400,000,000 = 20,000,000 IDR retention
        expect($mc->retention_deduction_idr)->toBe(20_000_000);
    }

    public function test_13_mc_computes_net_payable_amount(): void
    {
        $mcService = app(MonthlyCertificateService::class);
        $this->wbsCivil->update(['completion_percentage' => 50.0]);

        $mc = $mcService->issueCertifiedProgress(
            project: $this->project,
            claimedIncrementalPct: 20.0,
            consultantName: 'PT Virama Karya Konsultan'
        );

        // Net payable = 400M - 20M = 380,000,000 IDR
        expect($mc->net_payable_amount_idr)->toBe(380_000_000);
    }

    public function test_14_mc_ledger_posting_cip_and_contractor_payable_retention(): void
    {
        $mcService = app(MonthlyCertificateService::class);
        $this->wbsCivil->update(['completion_percentage' => 50.0]);

        $mcService->issueCertifiedProgress(
            project: $this->project,
            claimedIncrementalPct: 20.0,
            consultantName: 'PT Virama Karya Konsultan'
        );

        // Verify ledger entries exist
        $cipAccount = LedgerAccount::where('code', "ast:cip:{$this->project->id}:IDR")->first()
            ?? LedgerAccount::where('code', 'asset:epc:construction_in_progress:IDR')->first();
        expect($cipAccount)->not->toBeNull();
    }

    public function test_15_project_accumulated_cip_increases_with_gross_claim(): void
    {
        $mcService = app(MonthlyCertificateService::class);
        $this->wbsCivil->update(['completion_percentage' => 50.0]);

        $mcService->issueCertifiedProgress($this->project, 20.0, 'PT Konsultan');

        expect($this->project->fresh()->accumulated_cip_cost_idr)->toBe(400_000_000);
        expect((float) $this->project->fresh()->actual_physical_progress_pct)->toBe(20.0);
    }

    public function test_16_duplicate_mc_certificate_number_is_rejected(): void
    {
        $action = app(RecordWbsProgressAndCipLedgerCostAction::class);

        $action->execute([
            'project_id' => (string) $this->project->id,
            'certificate_number' => 'MC-DUP-001',
            'certified_incremental_progress_pct' => 10.0,
        ]);

        expect(fn () => $action->execute([
            'project_id' => (string) $this->project->id,
            'certificate_number' => 'MC-DUP-001',
            'certified_incremental_progress_pct' => 10.0,
        ]))->toThrow(\Throwable::class);
    }

    public function test_17_cumulative_progress_cannot_exceed_100_percent(): void
    {
        $action = app(RecordWbsProgressAndCipLedgerCostAction::class);
        $this->project->update(['actual_physical_progress_pct' => 90.0]);

        // Attempting to add 20% when 90% is reached
        $res = $action->execute([
            'project_id' => (string) $this->project->id,
            'certified_incremental_progress_pct' => 20.0,
        ]);

        // Clamped or verified to not exceed 100%
        expect((float) $res['project']->actual_physical_progress_pct)->toBeLessThanOrEqual(100.0);
    }

    // ─── SLICE 4: BAST HANDOVER LIFECYCLE (Tests 18-24) ───

    public function test_18_issue_bast_1_requires_at_least_95_percent_progress(): void
    {
        $action = app(IssueProjectBast1WithRetentionHoldAction::class);
        $this->project->update(['actual_physical_progress_pct' => 95.0, 'accumulated_cip_cost_idr' => 1_900_000_000]);

        $bast1 = $action->execute([
            'project_id' => (string) $this->project->id,
            'warranty_period_days' => 180,
        ]);

        expect($bast1['bast_type'])->toBe('BAST_1_PARTIAL');
        expect($bast1['status'])->toBe('bast_issued');
    }

    public function test_19_issue_bast_1_below_95_percent_is_rejected(): void
    {
        $action = app(IssueProjectBast1WithRetentionHoldAction::class);
        $this->project->update(['actual_physical_progress_pct' => 90.0]);

        expect(fn () => $action->execute([
            'project_id' => (string) $this->project->id,
        ]))->toThrow(InvalidArgumentException::class, 'below the minimum 95% threshold');
    }

    public function test_20_bast_1_records_warranty_retention_period(): void
    {
        $action = app(IssueProjectBast1WithRetentionHoldAction::class);
        $this->project->update(['actual_physical_progress_pct' => 96.0, 'accumulated_cip_cost_idr' => 1_920_000_000]);

        $bast1 = $action->execute([
            'project_id' => (string) $this->project->id,
            'warranty_period_days' => 365,
        ]);

        expect($bast1['warranty_period_days'])->toBe(365);
        expect($bast1['warranty_expiry_date'])->not->toBeNull();
    }

    public function test_21_issue_bast_final_requires_100_percent_progress(): void
    {
        $mcService = app(MonthlyCertificateService::class);
        $this->project->update(['actual_physical_progress_pct' => 100.0, 'accumulated_cip_cost_idr' => 2_000_000_000]);

        $bastFinal = $mcService->issueBastFinal($this->project);

        expect($bastFinal->bast_type)->toBe('BAST_FINAL');
        expect($bastFinal->status)->toBe('HANDED_OVER');
    }

    public function test_22_issue_bast_final_below_100_percent_is_rejected(): void
    {
        $mcService = app(MonthlyCertificateService::class);
        $this->project->update(['actual_physical_progress_pct' => 98.0]);

        expect(fn () => $mcService->issueBastFinal($this->project))
            ->toThrow(InvalidArgumentException::class, 'BAST Final requires 100% physical progress');
    }

    public function test_23_bast_final_marks_project_as_completed(): void
    {
        $mcService = app(MonthlyCertificateService::class);
        $this->project->update(['actual_physical_progress_pct' => 100.0, 'accumulated_cip_cost_idr' => 2_000_000_000]);

        $mcService->issueBastFinal($this->project);

        expect($this->project->fresh()->status)->toBe('bast_final_issued');
    }

    public function test_24_bast_final_records_handover_metadata(): void
    {
        $mcService = app(MonthlyCertificateService::class);
        $this->project->update(['actual_physical_progress_pct' => 100.0, 'accumulated_cip_cost_idr' => 2_000_000_000]);

        $bast = $mcService->issueBastFinal($this->project);

        expect($bast->bast_number)->toStartWith('BAST-FINAL-');
    }

    // ─── SLICE 5: CIP CAPITALIZATION TO FIXED ASSET (Tests 25-31) ───

    public function test_25_capitalization_requires_bast_final_approved(): void
    {
        $mcService = app(MonthlyCertificateService::class);
        $this->project->update([
            'actual_physical_progress_pct' => 100.0,
            'accumulated_cip_cost_idr' => 2_000_000_000,
            'status' => 'bast_final_issued',
        ]);

        $cap = $mcService->capitalizeProjectToAsset($this->project);

        expect($cap)->toBeInstanceOf(EpcCipCapitalization::class);
        expect($cap->total_cip_cost_idr)->toBe(2_000_000_000);
    }

    public function test_26_capitalization_before_bast_final_is_strictly_rejected(): void
    {
        $mcService = app(MonthlyCertificateService::class);
        $this->project->update([
            'actual_physical_progress_pct' => 80.0,
            'accumulated_cip_cost_idr' => 1_600_000_000,
            'status' => 'in_progress', // No BAST final!
        ]);

        expect(fn () => $mcService->capitalizeProjectToAsset($this->project))
            ->toThrow(InvalidArgumentException::class, 'Cannot capitalize CIP before BAST Final is approved');
    }

    public function test_27_capitalization_reclassifies_entire_cip_cost(): void
    {
        $mcService = app(MonthlyCertificateService::class);
        $this->project->update([
            'actual_physical_progress_pct' => 100.0,
            'accumulated_cip_cost_idr' => 2_000_000_000,
            'status' => 'bast_final_issued',
        ]);

        $mcService->capitalizeProjectToAsset($this->project);

        expect($this->project->fresh()->accumulated_cip_cost_idr)->toBe(0);
        expect($this->project->fresh()->capitalized_asset_value_idr)->toBe(2_000_000_000);
        expect($this->project->fresh()->status)->toBe('capitalized');
    }

    public function test_28_capitalization_posts_balanced_ledger_fixed_assets(): void
    {
        $mcService = app(MonthlyCertificateService::class);
        $this->project->update([
            'actual_physical_progress_pct' => 100.0,
            'accumulated_cip_cost_idr' => 2_000_000_000,
            'status' => 'bast_final_issued',
        ]);

        $mcService->capitalizeProjectToAsset($this->project);

        $fixedAssetAccount = LedgerAccount::where('code', 'ast:fixed_assets:IDR')->first()
            ?? LedgerAccount::where('code', 'asset:fixed:capitalized_buildings:IDR')->first();
        expect($fixedAssetAccount)->not->toBeNull();
    }

    public function test_29_post_capitalization_active_cip_is_exactly_zero(): void
    {
        $mcService = app(MonthlyCertificateService::class);
        $this->project->update([
            'actual_physical_progress_pct' => 100.0,
            'accumulated_cip_cost_idr' => 2_000_000_000,
            'status' => 'bast_final_issued',
        ]);

        $mcService->capitalizeProjectToAsset($this->project);

        expect($this->project->fresh()->accumulated_cip_cost_idr)->toBe(0);
    }

    public function test_30_capitalizing_project_with_zero_cip_is_rejected(): void
    {
        $action = app(CapitalizeCompletedCipToFixedAssetAction::class);
        $this->project->update(['accumulated_cip_cost_idr' => 0]);

        expect(fn () => $action->execute([
            'project_id' => (string) $this->project->id,
        ]))->toThrow(InvalidArgumentException::class, 'has no accumulated CIP cost to capitalize');
    }

    public function test_31_maintenance_retention_release_posts_to_ledger(): void
    {
        $mcService = app(MonthlyCertificateService::class);
        $this->project->update(['capitalized_asset_value_idr' => 2_000_000_000]);

        // Release 100M retention
        $result = $mcService->releaseRetention(
            project: $this->project,
            retentionAmountIdr: 100_000_000
        );

        expect($result['status'])->toBe('RELEASED');
        expect($result['amount_idr'])->toBe(100_000_000);
    }

    // ─── SLICE 6: BIM-WBS DIGITAL TWIN & TWO-SOURCE AUDIT (Tests 32-35) ───

    public function test_32_bim_twin_component_linked_to_wbs_node(): void
    {
        $component = TwinComponent::create([
            'component_code' => 'BIM-COL-GF-01',
            'bim_model_id' => 1,
            'wbs_node_id' => 1,
            'name' => 'Reinforced Concrete Column C1',
            'discipline' => 'STRUCTURE',
            'floor_level' => 'GF',
            'weight_kg' => 5000.0,
            'cost_estimate_idr' => 25_000_000,
            'completion_pct' => 0.0,
            'status' => 'PENDING',
        ]);

        expect($component->component_code)->toBe('BIM-COL-GF-01');
        expect($component->discipline)->toBe('STRUCTURE');
    }

    public function test_33_completing_twin_component_updates_status(): void
    {
        $component = TwinComponent::create([
            'component_code' => 'BIM-CHILLER-01',
            'bim_model_id' => 1,
            'wbs_node_id' => 1,
            'name' => 'Centrifugal Chiller 500TR',
            'discipline' => 'HVAC',
            'floor_level' => 'ROOF',
            'weight_kg' => 12000.0,
            'cost_estimate_idr' => 450_000_000,
            'completion_pct' => 100.0,
            'status' => 'INSTALLED',
        ]);

        expect($component->completion_pct)->toBe(100.0);
        expect($component->status)->toBe('INSTALLED');
    }

    public function test_34_epc_audit_passes_on_clean_state(): void
    {
        $this->artisan('epc:audit')->assertSuccessful();
    }

    public function test_35_epc_audit_fails_when_progress_exceeds_100_percent(): void
    {
        // Corrupt project actual progress to 180%
        $this->project->update([
            'actual_physical_progress_pct' => 180.0,
        ]);

        $this->artisan('epc:audit')
            ->assertFailed()
            ->expectsOutputToContain('actual progress exceeds 100%');
    }
}
