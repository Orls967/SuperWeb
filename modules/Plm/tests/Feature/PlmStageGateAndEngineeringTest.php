<?php

declare(strict_types=1);

namespace Modules\Plm\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Plm\Application\Actions\AdvancePlmStageGateWithStrictGuardAction;
use Modules\Plm\Application\Actions\ConvertEbomToManufacturingMbomAction;
use Modules\Plm\Application\Actions\ExecuteCryptographicEcoChangeOrderAction;
use Modules\Plm\Application\Actions\RecordEncryptedLabStabilityTestAction;
use Modules\Plm\Domain\Models\EngineeringBom;
use Modules\Plm\Domain\Models\PlmProject;
use Tests\TestCase;

class PlmStageGateAndEngineeringTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected PlmProject $project;

    protected EngineeringBom $ebom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);

        $this->project = PlmProject::create([
            'code' => 'PRJ-2026-BATTERY',
            'name' => 'High-Density Solid-State Battery Cell',
            'stage' => 'ideation',
            'budget_rd_idr' => 2500000000,
            'projected_roi_percent' => 45.0,
            'status' => 'active',
        ]);

        $this->ebom = EngineeringBom::create([
            'project_id' => $this->project->id,
            'bom_number' => 'EBOM-BATT-01',
            'version' => 'v1.0',
            'components' => [
                ['part_code' => 'CATHODE-NMC811', 'qty' => 50.0, 'unit' => 'KG'],
                ['part_code' => 'ANODE-SILICON', 'qty' => 25.0, 'unit' => 'KG'],
                ['part_code' => 'SOLID-ELECTROLYTE', 'qty' => 15.0, 'unit' => 'LITER'],
            ],
            'status' => 'draft',
        ]);
    }

    public function test_advance_stage_gate_strict_sequential_success(): void
    {
        $action = app(AdvancePlmStageGateWithStrictGuardAction::class);

        // Advance ideation -> scoping
        $res = $action->execute($this->project, 'scoping');
        $this->assertSame('scoping', $res['new_stage']);
        $this->assertSame('scoping', $this->project->fresh()->stage);

        // Advance scoping -> business_case
        $res2 = $action->execute($this->project->fresh(), 'business_case');
        $this->assertSame('business_case', $res2['new_stage']);
    }

    public function test_advance_stage_gate_rejects_jumping_stages_closing_k30(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stage-Gate jump rejected (K-30 violation)');

        $action = app(AdvancePlmStageGateWithStrictGuardAction::class);

        // Attempt jumping directly from 'ideation' to 'development' (skipping scoping & business_case)
        $action->execute($this->project, 'development');
    }

    public function test_advance_stage_gate_enforces_deliverables_before_testing_and_launch(): void
    {
        $action = app(AdvancePlmStageGateWithStrictGuardAction::class);

        $emptyProject = PlmProject::create([
            'code' => 'PRJ-EMPTY',
            'name' => 'Empty Project No EBOM',
            'stage' => 'development',
            'budget_rd_idr' => 50000000,
            'status' => 'active',
        ]);

        // Attempt to enter 'testing' without any EBOMs
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one Engineering BOM (EBOM) must be defined');

        $action->execute($emptyProject, 'testing');
    }

    public function test_convert_ebom_to_manufacturing_mbom_with_scrap_yield(): void
    {
        $action = app(ConvertEbomToManufacturingMbomAction::class);

        $mbom = $action->execute($this->ebom, [
            'routing_code' => 'ROUT-CELL-ASSEMBLY',
            'scrap_allowance_percent' => 5.0,
        ]);

        $this->assertSame('MBOM-EBOM-BATT-01', $mbom['mbom_code']);
        $this->assertSame('released_to_mbom', $this->ebom->fresh()->status);
        $this->assertSame('ROUT-CELL-ASSEMBLY', $mbom['routing_code']);
        $this->assertCount(3, $mbom['manufacturing_components']);

        // Cathode net 50 + 5% scrap = 52.5 gross
        $this->assertSame(52.5, $mbom['manufacturing_components'][0]['gross_qty']);
    }

    public function test_convert_ebom_to_mbom_rejects_already_released_ebom(): void
    {
        $this->ebom->update(['status' => 'released_to_mbom']);

        $this->expectException(InvalidArgumentException::class);
        $action = app(ConvertEbomToManufacturingMbomAction::class);

        $action->execute($this->ebom);
    }

    public function test_execute_cryptographic_eco_change_order_hash_chaining(): void
    {
        $action = app(ExecuteCryptographicEcoChangeOrderAction::class);

        // ECO 1
        $eco1 = $action->execute(
            $this->ebom,
            'Update Anode Ratio',
            'Improve thermal stability during rapid DC charging',
            15000000,
            'rework',
            'CHIEF-SCIENTIST-01'
        );

        $this->assertNotNull($eco1['change_order']->id);
        $this->assertSame('v1.1', $eco1['new_version']);
        $this->assertSame('v1.1', $this->ebom->fresh()->version);
        $this->assertNotEmpty($eco1['hash']);

        // ECO 2 (chains to ECO 1 hash)
        $eco2 = $action->execute(
            $this->ebom->fresh(),
            'Substitute Electrolyte Additive',
            'Regulatory REACH compliance upgrade',
            5000000,
            'run_out',
            'CHIEF-SCIENTIST-01'
        );

        $this->assertSame($eco1['hash'], $eco2['prev_hash']);
        $this->assertSame('v1.2', $eco2['new_version']);
    }

    public function test_record_encrypted_lab_stability_test_and_sensory_evaluation(): void
    {
        $action = app(RecordEncryptedLabStabilityTestAction::class);

        $res = $action->execute(
            $this->project,
            'Electrolyte Degradation under 60C Accelerated Aging',
            'Secret-Formula-Ratio: LiPF6 1.2M + EC/EMC 3:7 wt% + FEC 2%',
            'pass',
            8.7
        );

        $this->assertNotNull($res['notebook']->id);
        $this->assertSame('pass', $res['stability_test_result']);
        $this->assertSame(8.7, $res['sensory_score']);
        $this->assertTrue($res['is_passing']);

        // Verify transparent encryption / decryption
        $fresh = $res['notebook']->fresh();
        $this->assertSame('Secret-Formula-Ratio: LiPF6 1.2M + EC/EMC 3:7 wt% + FEC 2%', $fresh->formula_payload_encrypted);
    }

    public function test_plm_operations_controller_endpoints(): void
    {
        $this->actingAs($this->user);

        // 1. Cockpit Index
        $respDash = $this->get('/plm/operations');
        $respDash->assertOk();

        // 2. Advance Stage API
        $respStage = $this->postJson("/plm/operations/stage-gate/{$this->project->id}", [
            'target_stage' => 'scoping',
        ]);
        $respStage->assertOk();
        $this->assertSame('scoping', $respStage->json('data.new_stage'));

        // 3. Convert MBOM API
        $respMbom = $this->postJson("/plm/operations/mbom/{$this->ebom->id}", [
            'routing_code' => 'ROUT-ADV-01',
            'scrap_allowance_percent' => 3.0,
        ]);
        $respMbom->assertStatus(201);
        $this->assertSame('MBOM-EBOM-BATT-01', $respMbom->json('data.mbom_code'));

        // 4. Execute ECO API
        $respEco = $this->postJson("/plm/operations/eco/{$this->ebom->id}", [
            'title' => 'Packaging Specification Change',
            'reason' => 'Prevent puncture during robotic pouch handling',
            'cost_impact_idr' => 2000000,
            'disposition' => 'run_out',
        ]);
        $respEco->assertStatus(201);
        $this->assertNotNull($respEco->json('data.hash'));

        // 5. Lab Test API
        $respLab = $this->postJson("/plm/operations/lab-test/{$this->project->id}", [
            'title' => 'Drop Test & Puncture Resistance',
            'formula_payload' => 'Reinforced Pouch Foil 120um',
            'stability_result' => 'pass',
            'sensory_score' => 8.0,
        ]);
        $respLab->assertStatus(201);
        $this->assertSame('pass', $respLab->json('data.stability_test_result'));
    }
}
