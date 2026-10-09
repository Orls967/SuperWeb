<?php

namespace Modules\Epc\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Epc\Application\Services\EpcService;
use Modules\Epc\Domain\Models\EpcCipCapitalization;
use Modules\Epc\Domain\Models\EpcProgressCertificate;
use Modules\Epc\Domain\Models\EpcProject;
use Modules\Epc\Domain\Models\EpcWbsNode;
use Tests\TestCase;

class EpcTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_project_and_wbs_nodes(): void
    {
        $service = app(EpcService::class);

        $project = $service->createProject([
            'project_code' => 'EPC-DUTA-EXT',
            'project_name' => 'Duta Mall Extension Tower B',
            'client_entity_id' => 'PT-DUTA-MALL',
            'total_rab_budget_idr' => 20000000000, // 20 Milyar
        ]);

        $this->assertInstanceOf(EpcProject::class, $project);

        $wbs = $service->addWbsNode($project->id, [
            'wbs_code' => 'WBS-1.1',
            'task_name' => 'Bored Pile Foundation & Earthworks',
            'work_package' => 'civil_structure',
            'weight_percentage' => 15.0,
            'budget_allocation_idr' => 3000000000,
        ]);

        $this->assertInstanceOf(EpcWbsNode::class, $wbs);
        $this->assertEquals(15.0, (float) $wbs->weight_percentage);

        // Cannot add node exceeding remaining weight (100 - 15 = 85%)
        $this->expectException(\InvalidArgumentException::class);
        $service->addWbsNode($project->id, [
            'wbs_code' => 'WBS-EXCESS',
            'task_name' => 'Overweight Work Package',
            'weight_percentage' => 90.0,
            'budget_allocation_idr' => 1000000000,
        ]);
    }

    public function test_can_issue_monthly_progress_certificate(): void
    {
        $service = app(EpcService::class);

        $project = $service->createProject([
            'project_code' => 'EPC-PLANT-02',
            'project_name' => 'Pabrik Pengolahan Pangan Cikande',
            'client_entity_id' => 'PT-SARI-RANAH-MFG',
            'total_rab_budget_idr' => 10000000000, // 10 Milyar
        ]);

        // MC-01: 20% incremental progress -> gross claim = 2 Milyar, 5% retention = 100 Juta, net = 1.9 Milyar
        $mc = $service->issueMonthlyCertificate($project->id, [
            'certificate_number' => 'MC-01-CKD',
            'certified_cumulative_progress_pct' => 20.0,
            'certified_incremental_progress_pct' => 20.0,
            'supervising_consultant_name' => 'PT Virama Karya',
        ]);

        $this->assertInstanceOf(EpcProgressCertificate::class, $mc);
        $this->assertEquals(2000000000, $mc->gross_claim_amount_idr);
        $this->assertEquals(100000000, $mc->retention_deduction_idr);
        $this->assertEquals(1900000000, $mc->net_payable_amount_idr);

        $project->refresh();
        $this->assertEquals(20.0, (float) $project->actual_physical_progress_pct);
        $this->assertEquals(2000000000, $project->accumulated_cip_cost_idr);
    }

    public function test_can_capitalize_cip_to_fixed_asset(): void
    {
        $service = app(EpcService::class);

        $project = $service->createProject([
            'project_code' => 'EPC-RESTO-CK02',
            'project_name' => 'Central Kitchen CK-02 Surabaya',
            'client_entity_id' => 'PT-SARI-RANAH-RESTO',
            'total_rab_budget_idr' => 5000000000,
        ]);

        $service->issueMonthlyCertificate($project->id, [
            'certified_cumulative_progress_pct' => 100.0,
            'certified_incremental_progress_pct' => 100.0,
        ]);

        $project->refresh();
        $this->assertEquals(5000000000, $project->accumulated_cip_cost_idr);

        $bast = $service->capitalizeCipToAsset($project->id, [
            'bast_number' => 'BAST-FINAL-CK02',
            'bast_type' => 'BAST_FINAL',
            'target_asset_category' => 'BUILDING',
            'created_asset_id' => 'AST-BLD-SUB-01',
        ]);

        $this->assertInstanceOf(EpcCipCapitalization::class, $bast);
        $this->assertEquals(5000000000, $bast->total_cip_cost_idr);

        $project->refresh();
        $this->assertEquals(0, $project->accumulated_cip_cost_idr);
        $this->assertEquals(5000000000, $project->capitalized_asset_value_idr);
        $this->assertEquals('capitalized', $project->status);
    }

    public function test_epc_audit_passes_cleanly(): void
    {
        $service = app(EpcService::class);

        $audit = $service->auditEpc();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEmpty($audit['discrepancies']);
    }

    public function test_epc_web_index_accessible(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('epc.index'));
        $response->assertStatus(200);
        $response->assertSee('EPC Construction, WBS S-Curve & Asset Capitalization');
    }
}
