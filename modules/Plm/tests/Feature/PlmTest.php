<?php

declare(strict_types=1);

namespace Modules\Plm\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Plm\Application\Services\PlmService;
use Tests\TestCase;

class PlmTest extends TestCase
{
    use RefreshDatabase;

    protected PlmService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlmService::class);
    }

    public function test_can_create_project_and_ebom(): void
    {
        $project = $this->service->createProject([
            'code' => 'PRJ-EV01',
            'name' => 'NextGen Electric Powertrain',
            'stage' => 'scoping',
            'budget_rd_idr' => 500000000,
            'projected_roi_percent' => 35.0,
        ]);

        $this->assertDatabaseHas('plm_projects', [
            'id' => $project->id,
            'code' => 'PRJ-EV01',
        ]);

        $ebom = $this->service->createEngineeringBom($project, 'EBOM-EV01-01', [
            ['part' => 'STATOR-01', 'qty' => 1, 'material' => 'Copper'],
            ['part' => 'ROTOR-01', 'qty' => 1, 'material' => 'Permanent Magnet'],
        ]);

        $this->assertDatabaseHas('plm_engineering_boms', [
            'id' => $ebom->id,
            'bom_number' => 'EBOM-EV01-01',
        ]);
    }

    public function test_can_advance_stage_and_release_ebom_to_mbom(): void
    {
        $project = $this->service->createProject([
            'code' => 'PRJ-STAGE-01',
            'name' => 'High Capacity Inverter',
            'stage' => 'ideation',
        ]);

        $advanced = $this->service->advanceStage($project, 'development');
        $this->assertSame('development', $advanced->stage);

        $ebom = $this->service->createEngineeringBom($project, 'EBOM-INV-01', [
            ['part' => 'MOSFET-ARRAY', 'qty' => 8],
            ['part' => 'ALUMINUM-HEATSINK', 'qty' => 1],
        ]);

        $mbomRecipe = $this->service->releaseEbomToMbom($ebom);
        $this->assertSame('MBOM-EBOM-INV-01', $mbomRecipe['mbom_code']);
        $this->assertSame('active_production_recipe', $mbomRecipe['status']);
        $this->assertSame(2, $mbomRecipe['items_count']);
        $this->assertSame('released', $ebom->fresh()->status);
    }

    public function test_can_submit_change_order_with_hash_chain(): void
    {
        $project = $this->service->createProject(['name' => 'Battery Cooling System']);
        $ebom = $this->service->createEngineeringBom($project, 'EBOM-COOL-01', []);

        $eco1 = $this->service->submitEngineeringChangeOrder($ebom, 'Upgrade Pump Spec', 'Higher Flow Required', 15000000, 'rework');
        $this->assertNotEmpty($eco1->hash);

        $eco2 = $this->service->submitEngineeringChangeOrder($ebom, 'Hose Clamp Redesign', 'Leakage prevention', 2000000, 'scrap');
        $this->assertSame($eco1->hash, $eco2->prev_hash);
    }

    public function test_can_record_lab_notebook(): void
    {
        $project = $this->service->createProject(['name' => 'Organic Lubricant Formula']);
        $notebook = $this->service->recordLabExperiment($project, 'EXP-LUB-01', 'Viscosity Blend 24', 'SecretAdditiveX=15%', 'pass', 9.0);

        $this->assertDatabaseHas('plm_lab_notebooks', [
            'id' => $notebook->id,
            'experiment_code' => 'EXP-LUB-01',
            'stability_test_result' => 'pass',
        ]);
    }

    public function test_plm_audit_passes_with_zero_discrepancy(): void
    {
        $project = $this->service->createProject(['name' => 'High Speed Transmission']);
        $ebom = $this->service->createEngineeringBom($project, 'EBOM-TRANS-01', []);
        $this->service->submitEngineeringChangeOrder($ebom, 'Gear Alloy Change', 'Fatigue reduction', 5000000);

        $exitCode = Artisan::call('plm:audit');
        $this->assertSame(0, $exitCode);
    }

    public function test_plm_web_index_accessible(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('plm.index'));
        $response->assertStatus(200);
        $response->assertSee('Product Lifecycle Management');
    }
}
