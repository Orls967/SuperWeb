<?php

namespace Modules\Proptech\tests\Feature\Bim;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Proptech\Application\Services\BimTwinService;
use Modules\Proptech\Domain\Models\Bim\TwinComponent;
use Tests\TestCase;

class BimTwinLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected BimTwinService $bimService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bimService = app(BimTwinService::class);

        LedgerAccount::create([
            'code' => 'ast:fixed_assets:IDR',
            'name' => 'Fixed Assets Capitalized',
            'asset_code' => 'IDR',
            'kind' => 'asset',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'epc:cip_liability:IDR',
            'name' => 'Construction in Progress CIP',
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
    }

    public function test_77_1_and_77_5_bim_versioning_and_tamper_evident_hash_chain(): void
    {
        $v1 = $this->bimService->createOrReviseModel(
            projectCode: 'PRJ-DUTA-EXT',
            name: 'Initial Structural & MEP Model',
            metadata: ['author' => 'Architect Corp', 'scale' => '1:100']
        );

        $this->assertEquals(1, $v1->version);
        $this->assertEquals('APPROVED', $v1->status);
        $this->assertNotNull($v1->current_hash);

        // Revise to version 2
        $v2 = $this->bimService->createOrReviseModel(
            projectCode: 'PRJ-DUTA-EXT',
            name: 'Revised Chiller Placement',
            metadata: ['author' => 'HVAC Lead', 'scale' => '1:100']
        );

        $this->assertEquals(2, $v2->version);
        $this->assertEquals($v1->current_hash, $v2->previous_hash);
        $this->assertEquals('SUPERSEDED', $v1->refresh()->status);
        $this->assertEquals('APPROVED', $v2->status);
    }

    public function test_77_2_wbs_physical_progress_and_cip_capitalization(): void
    {
        $model = $this->bimService->createOrReviseModel(
            projectCode: 'PRJ-HOTEL-01',
            name: 'Hotel Tower MEP',
            metadata: ['lod' => 300]
        );

        $comp1 = TwinComponent::create([
            'bim_model_id' => $model->id,
            'component_code' => 'CHL-001',
            'wbs_node_code' => 'WBS-MEP-HVAC',
            'category' => 'HVAC_CHILLER',
            'name' => 'Centrifugal Chiller 500TR',
            'completion_pct' => 0,
        ]);

        $comp2 = TwinComponent::create([
            'bim_model_id' => $model->id,
            'component_code' => 'DCT-001',
            'wbs_node_code' => 'WBS-MEP-HVAC',
            'category' => 'DUCTWORK',
            'name' => 'Main Air Duct Loop Floor 1',
            'completion_pct' => 0,
        ]);

        // Partial progress: comp1 = 100%, comp2 = 50% -> aggregate = 75%
        $this->bimService->updateComponentProgress($comp1, 100);
        $this->bimService->updateComponentProgress($comp2, 50);

        $agg = $this->bimService->calculateWbsPhysicalProgress('WBS-MEP-HVAC');
        $this->assertEquals(75.0, $agg);

        // Premature capitalization fails
        $this->expectException(\RuntimeException::class);
        $this->bimService->capitalizeBimMilestoneProgress('WBS-MEP-HVAC', 500_000_000);
    }

    public function test_77_2_full_completion_capitalizes_cip_to_fixed_assets(): void
    {
        $model = $this->bimService->createOrReviseModel(
            projectCode: 'PRJ-HOTEL-02',
            name: 'Hotel Tower MEP Completed',
            metadata: ['lod' => 400]
        );

        $comp = TwinComponent::create([
            'bim_model_id' => $model->id,
            'component_code' => 'CHL-002',
            'wbs_node_code' => 'WBS-CHILLER-DONE',
            'category' => 'HVAC_CHILLER',
            'name' => 'Chiller 002',
            'completion_pct' => 100,
            'status' => 'INSTALLED',
        ]);

        $this->bimService->capitalizeBimMilestoneProgress('WBS-CHILLER-DONE', 750_000_000);

        $asset = LedgerAccount::where('code', 'ast:fixed_assets:IDR')->first();
        $cip = LedgerAccount::where('code', 'epc:cip_liability:IDR')->first();
        $this->assertEquals('750000000', (string) $asset->cached_balance);
        $this->assertEquals('-750000000', (string) $cip->cached_balance);
    }

    public function test_77_3_and_77_4_work_order_tagging_and_twin_simulation_sandbox(): void
    {
        $model = $this->bimService->createOrReviseModel(
            projectCode: 'PRJ-MALL-TWIN',
            name: 'Mall Operations Twin',
            metadata: []
        );

        $comp = TwinComponent::create([
            'bim_model_id' => $model->id,
            'component_code' => 'PIP-CHW-01',
            'wbs_node_code' => 'WBS-01',
            'category' => 'PIPING',
            'name' => 'Chilled Water Supply Pipe',
            'completion_pct' => 100,
            'status' => 'OPERATIONAL',
        ]);

        TwinComponent::create([
            'bim_model_id' => $model->id,
            'component_code' => 'CHL-SIM-01',
            'wbs_node_code' => 'WBS-01',
            'category' => 'HVAC_CHILLER',
            'name' => 'Modular Chiller 300TR',
            'completion_pct' => 100,
            'status' => 'OPERATIONAL',
        ]);

        TwinComponent::create([
            'bim_model_id' => $model->id,
            'component_code' => 'DCT-SIM-01',
            'wbs_node_code' => 'WBS-01',
            'category' => 'DUCTWORK',
            'name' => 'Supply Air Duct',
            'completion_pct' => 100,
            'status' => 'OPERATIONAL',
        ]);

        // Tag work order
        $issue = $this->bimService->tagComponentWorkOrder(
            component: $comp,
            issueType: 'LEAK',
            description: 'Leak detected at elbow joint 4B',
            workOrderId: 9021
        );

        $this->assertEquals('LEAK', $issue->issue_type);
        $this->assertEquals(9021, $issue->facility_work_order_id);
        $this->assertEquals('OPEN', $issue->status);

        // Simulation sandbox: does not touch database records
        $sim = $this->bimService->runTwinAirflowSimulation($model, ambientTempC: 33.0);
        $this->assertTrue($sim['simulated']);
        $this->assertEquals(0, $sim['db_records_modified']);
        $this->assertLessThan(33.0, $sim['projected_room_temp_c']);
    }
}
