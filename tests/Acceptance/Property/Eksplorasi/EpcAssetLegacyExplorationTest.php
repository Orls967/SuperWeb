<?php

declare(strict_types=1);

namespace Tests\Acceptance\Property\Eksplorasi;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Database\Seeders\RbacConglomerateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Epc\Application\Actions\CapitalizeCompletedCipToFixedAssetAction;
use Modules\Epc\Application\Actions\IssueProjectBast1WithRetentionHoldAction;
use Modules\Epc\Application\Actions\RecordWbsProgressAndCipLedgerCostAction;
use Modules\Epc\Application\Services\EpcService;
use Modules\Epc\Domain\Models\EpcProject;
use Modules\Epc\Domain\Models\EpcWbsNode;
use Tests\TestCase;

/**
 * LINI 6: Properti - Epc and Asset Legacy Codebase Exploration (L6.0).
 *
 * Membuktikan kondisi awal kode lama EPC sebelum MVP disempurnakan:
 * - Table schema: BENAR (tabel `epc_projects`, `epc_wbs_nodes`, `epc_progress_certificates`, `epc_cip_capitalizations` ada)
 * - RecordWbsProgressAndCipLedgerCostAction: SEBAGIAN (hitung claim & retensi, tapi tanpa PR2 bottom-up/top-down WBS verification certificate dan pakai akun lama asset:epc:construction_in_progress:IDR bukan ast:cip:{project}:IDR)
 * - IssueProjectBast1WithRetentionHoldAction: BENAR (menegakkan batas minimal 95% progres sebelum BAST-1 terbit)
 * - CapitalizeCompletedCipToFixedAssetAction: SALAH (membolehkan kapitalisasi CIP sebelum BAST final bila dipanggil langsung tanpa verifikasi status BAST)
 * - EpcService::auditEpc: BENAR (dua sumber verifikasi klaim vs saldo CIP dan progres <= 100%)
 */
class EpcAssetLegacyExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected User $projectManager;

    protected EpcProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacConglomerateSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);

        $this->projectManager = User::factory()->create([
            'email' => 'epc_manager@superweb.test',
            'name' => 'Ir. Hartono Sitepu',
        ]);

        $this->project = EpcProject::create([
            'id' => (string) Str::uuid(),
            'project_code' => 'EPC-PRJ-001',
            'project_name' => 'Mall Extension & Solar Canopy Tower B',
            'project_type' => 'mall_extension',
            'client_entity_id' => (string) Str::uuid(),
            'total_rab_budget_idr' => 1_000_000_000,
            'target_physical_progress_pct' => 100.0,
            'actual_physical_progress_pct' => 0.0,
            'accumulated_cip_cost_idr' => 0,
            'capitalized_asset_value_idr' => 0,
            'start_date' => now()->toDateString(),
            'target_completion_date' => now()->addMonths(6)->toDateString(),
            'status' => 'in_progress',
        ]);

        EpcWbsNode::create([
            'project_id' => $this->project->id,
            'wbs_code' => 'WBS-01-CIVIL',
            'task_name' => 'Struktur Pondasi & Kolom',
            'work_package' => 'civil_structure',
            'weight_percentage' => 40.0,
            'budget_allocation_idr' => 400_000_000,
            'completion_percentage' => 0.0,
            'status' => 'pending',
        ]);

        EpcWbsNode::create([
            'project_id' => $this->project->id,
            'wbs_code' => 'WBS-02-MEP',
            'task_name' => 'Mekanikal Elektrikal Plumbing',
            'work_package' => 'mep',
            'weight_percentage' => 60.0,
            'budget_allocation_idr' => 600_000_000,
            'completion_percentage' => 0.0,
            'status' => 'pending',
        ]);
    }

    public function test_legacy_epc_tables_exist(): void
    {
        expect(Schema::hasTable('epc_projects'))->toBeTrue();
        expect(Schema::hasTable('epc_wbs_nodes'))->toBeTrue();
        expect(Schema::hasTable('epc_progress_certificates'))->toBeTrue();
        expect(Schema::hasTable('epc_cip_capitalizations'))->toBeTrue();
    }

    public function test_record_wbs_progress_action_status_sebagian(): void
    {
        $action = app(RecordWbsProgressAndCipLedgerCostAction::class);

        $result = $action->execute([
            'project_id' => $this->project->id,
            'wbs_code' => 'WBS-01-CIVIL',
            'wbs_completion_pct' => 50.0,
            'certified_incremental_progress_pct' => 20.0,
        ]);

        expect($result['gross_claim_idr'])->toBe(200_000_000);
        expect($result['retention_idr'])->toBe(10_000_000); // 5% retention
        expect($result['net_payable_idr'])->toBe(190_000_000);

        // Proves status SEBAGIAN:
        // Input incremental percentage accepted without PR2 WBS bottom-up & top-down verification certificate
        expect((float) $result['certificate']->certified_incremental_progress_pct)->toBe(20.0);
        expect($result['project']->accumulated_cip_cost_idr)->toBe(200_000_000);
    }

    public function test_issue_bast_1_threshold_enforcement_status_benar(): void
    {
        $action = app(IssueProjectBast1WithRetentionHoldAction::class);

        // Actual progress is 0%, threshold requires >= 95%
        expect(fn () => $action->execute([
            'project_id' => $this->project->id,
        ]))->toThrow(InvalidArgumentException::class, 'below the minimum 95% threshold');

        // Set progress to 95%
        $this->project->update(['actual_physical_progress_pct' => 95.0, 'accumulated_cip_cost_idr' => 950_000_000]);

        $bast1 = $action->execute([
            'project_id' => $this->project->id,
            'warranty_period_days' => 180,
        ]);

        expect($bast1['bast_type'])->toBe('BAST_1_PARTIAL');
        expect($bast1['status'])->toBe('bast_issued');
    }

    public function test_capitalize_cip_before_bast_final_status_salah(): void
    {
        $action = app(CapitalizeCompletedCipToFixedAssetAction::class);

        // Project has CIP but status is in_progress (no BAST Final issued)
        $this->project->update([
            'actual_physical_progress_pct' => 50.0,
            'accumulated_cip_cost_idr' => 500_000_000,
            'status' => 'in_progress',
        ]);

        // Legacy action capitalizes directly without checking if BAST Final is issued! (Status SALAH)
        $capitalization = $action->execute([
            'project_id' => $this->project->id,
        ]);

        expect($capitalization->total_cip_cost_idr)->toBe(500_000_000);
        // This confirms the legacy flaw: premature capitalization before final handover was allowed
    }

    public function test_epc_audit_two_source_status_benar(): void
    {
        $service = app(EpcService::class);
        $audit = $service->auditEpc();

        expect($audit['status'])->toBe('HEALTHY');
        expect(count($audit['discrepancies']))->toBe(0);
    }
}
