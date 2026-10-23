<?php

declare(strict_types=1);

namespace Modules\Epc\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Epc\Application\Actions\CapitalizeCompletedCipToFixedAssetAction;
use Modules\Epc\Application\Actions\IssueProjectBast1WithRetentionHoldAction;
use Modules\Epc\Application\Actions\RecordWbsProgressAndCipLedgerCostAction;
use Modules\Epc\Application\Services\EpcService;
use Modules\Epc\Domain\Models\EpcCipCapitalization;
use Modules\Epc\Domain\Models\EpcProgressCertificate;
use Tests\TestCase;

class EpcOperationsSliceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_record_wbs_progress_and_cip_ledger_cost(): void
    {
        $epcService = app(EpcService::class);
        $action = app(RecordWbsProgressAndCipLedgerCostAction::class);

        $project = $epcService->createProject([
            'project_code' => 'EPC-HOTEL-BALI',
            'project_name' => 'Resort & Villa Sanur Extension',
            'client_entity_id' => 'PT-SANUR-HOTEL',
            'total_rab_budget_idr' => 40000000000, // 40 Milyar
        ]);

        // Incremental progress: 25% -> Gross claim: 10 Milyar, Retention (5%): 500 Juta, Net: 9.5 Milyar
        $result = $action->execute([
            'project_id' => (string) $project->id,
            'certificate_number' => 'MC-01-SANUR',
            'certified_incremental_progress_pct' => 25.0,
            'supervising_consultant_name' => 'PT Virama Karya',
        ]);

        $this->assertInstanceOf(EpcProgressCertificate::class, $result['certificate']);
        $this->assertEquals(10000000000, $result['gross_claim_idr']);
        $this->assertEquals(500000000, $result['retention_idr']);
        $this->assertEquals(9500000000, $result['net_payable_idr']);

        $project->refresh();
        $this->assertEquals(25.0, (float) $project->actual_physical_progress_pct);
        $this->assertEquals(10000000000, $project->accumulated_cip_cost_idr);

        // Verify ledger entries in single sign convention:
        // CIP Asset: debit 10 Milyar -> -10000000000
        $cipAcct = LedgerAccount::where('code', 'asset:epc:construction_in_progress:IDR')->first();
        $this->assertNotNull($cipAcct);
        $this->assertEquals('-10000000000', $cipAcct->cached_balance);

        // Retention liability: credit 500 Juta -> 500000000
        $retAcct = LedgerAccount::where('code', 'ap:epc:contractor_retention:IDR')->first();
        $this->assertEquals('500000000', $retAcct->cached_balance);

        // Contractor payable: credit 9.5 Milyar -> 9500000000
        $payAcct = LedgerAccount::where('code', 'ap:epc:contractor_payable:IDR')->first();
        $this->assertEquals('9500000000', $payAcct->cached_balance);
    }

    public function test_record_wbs_progress_validates_incremental_percentage(): void
    {
        $epcService = app(EpcService::class);
        $action = app(RecordWbsProgressAndCipLedgerCostAction::class);

        $project = $epcService->createProject([
            'project_code' => 'EPC-WAREHOUSE-TEST',
            'project_name' => 'Cikarang Logistics Hub',
            'client_entity_id' => 'PT-LOGISTICS',
            'total_rab_budget_idr' => 10000000000,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $action->execute([
            'project_id' => (string) $project->id,
            'certified_incremental_progress_pct' => 0.0,
        ]);
    }

    public function test_issue_project_bast_1_requires_95_percent_threshold(): void
    {
        $epcService = app(EpcService::class);
        $action = app(IssueProjectBast1WithRetentionHoldAction::class);

        $project = $epcService->createProject([
            'project_code' => 'EPC-PLANT-03',
            'project_name' => 'Cilegon Chemical Plant',
            'client_entity_id' => 'PT-KIMIA-01',
            'total_rab_budget_idr' => 50000000000,
        ]);

        // Physical progress is 0% -> must reject BAST-1
        $this->expectException(InvalidArgumentException::class);
        $action->execute([
            'project_id' => (string) $project->id,
            'bast_number' => 'BAST-1-TEST',
        ]);
    }

    public function test_issue_project_bast_1_succeeds_at_completion(): void
    {
        $epcService = app(EpcService::class);
        $recordAction = app(RecordWbsProgressAndCipLedgerCostAction::class);
        $bastAction = app(IssueProjectBast1WithRetentionHoldAction::class);

        $project = $epcService->createProject([
            'project_code' => 'EPC-PLANT-04',
            'project_name' => 'Subang Canning Facility',
            'client_entity_id' => 'PT-FOOD-01',
            'total_rab_budget_idr' => 10000000000,
        ]);

        // Reach 100% progress
        $recordAction->execute([
            'project_id' => (string) $project->id,
            'certified_incremental_progress_pct' => 100.0,
        ]);

        $bastResult = $bastAction->execute([
            'project_id' => (string) $project->id,
            'bast_number' => 'BAST-1-SUB-04',
            'warranty_period_days' => 180,
        ]);

        $this->assertEquals('bast_issued', $bastResult['status']);
        $this->assertEquals(180, $bastResult['warranty_period_days']);
        $this->assertEquals(500000000, $bastResult['retention_warranty_held_idr']);

        $project->refresh();
        $this->assertEquals('bast_issued', $project->status);
    }

    public function test_capitalize_completed_cip_to_fixed_asset_via_ledger(): void
    {
        $epcService = app(EpcService::class);
        $recordAction = app(RecordWbsProgressAndCipLedgerCostAction::class);
        $capAction = app(CapitalizeCompletedCipToFixedAssetAction::class);

        $project = $epcService->createProject([
            'project_code' => 'EPC-MALL-TWR',
            'project_name' => 'Grand Tower Banjarmasin',
            'client_entity_id' => 'PT-MALL-GRP',
            'total_rab_budget_idr' => 30000000000, // 30 Milyar
        ]);

        // Certify 100% progress -> CIP balance = 30 Milyar
        $recordAction->execute([
            'project_id' => (string) $project->id,
            'certified_incremental_progress_pct' => 100.0,
        ]);

        $project->refresh();
        $this->assertEquals(30000000000, $project->accumulated_cip_cost_idr);

        // Capitalize to Fixed Asset
        $capitalization = $capAction->execute([
            'project_id' => (string) $project->id,
            'bast_number' => 'BAST-FINAL-MALL',
            'target_asset_category' => 'BUILDING',
            'created_asset_id' => 'AST-BLD-GT-01',
        ]);

        $this->assertInstanceOf(EpcCipCapitalization::class, $capitalization);
        $this->assertEquals(30000000000, $capitalization->total_cip_cost_idr);

        $project->refresh();
        $this->assertEquals(0, $project->accumulated_cip_cost_idr);
        $this->assertEquals(30000000000, $project->capitalized_asset_value_idr);
        $this->assertEquals('capitalized', $project->status);

        // Verify ledger:
        // Fixed Asset Buildings: debited 30 Milyar -> -30000000000
        $fixedAcct = LedgerAccount::where('code', 'asset:fixed:capitalized_buildings:IDR')->first();
        $this->assertEquals('-30000000000', $fixedAcct->cached_balance);

        // CIP Asset: debited 30M, credited 30M -> 0
        $cipAcct = LedgerAccount::where('code', 'asset:epc:construction_in_progress:IDR')->first();
        $this->assertEquals('0', $cipAcct->cached_balance);
    }

    public function test_epc_operations_controller_views_and_actions(): void
    {
        $user = User::factory()->create(['role' => 'epc_manager']);
        $epcService = app(EpcService::class);

        $project = $epcService->createProject([
            'project_code' => 'EPC-CTRL-TEST',
            'project_name' => 'Test Control Center Project',
            'client_entity_id' => 'PT-CLIENT',
            'total_rab_budget_idr' => 10000000000,
        ]);

        // Dashboard
        $resDash = $this->actingAs($user)->get(route('epc.dashboard'));
        $resDash->assertStatus(200);
        $resDash->assertSee('EPC Construction');

        // Claims Index & Record
        $resClaims = $this->actingAs($user)->get(route('epc.claims.index'));
        $resClaims->assertStatus(200);

        $resPostClaims = $this->actingAs($user)->post(route('epc.claims.record'), [
            'project_id' => (string) $project->id,
            'certificate_number' => 'MC-TEST-99',
            'certified_incremental_progress_pct' => 10.0,
            'supervising_consultant_name' => 'PT Consultant',
        ]);
        $resPostClaims->assertRedirect(route('epc.claims.index'));
        $this->assertDatabaseHas('epc_progress_certificates', ['certificate_number' => 'MC-TEST-99']);

        // BAST Index
        $resBast = $this->actingAs($user)->get(route('epc.bast.index'));
        $resBast->assertStatus(200);

        // Capitalization Index
        $resCap = $this->actingAs($user)->get(route('epc.capitalization.index'));
        $resCap->assertStatus(200);
    }
}
