<?php

namespace Modules\Esg\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Esg\Application\Services\EsgService;
use Modules\Esg\Domain\Models\CarbonCredit;
use Modules\Esg\Domain\Models\EsgEmission;
use Tests\TestCase;

class EsgTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_record_ghg_emissions(): void
    {
        $service = app(EsgService::class);

        $emission = $service->recordEmission([
            'entity_id' => 'PLANT-01',
            'scope' => 'scope_1',
            'activity_type' => 'fuel_diesel',
            'activity_data_amount' => 1000.0,
            'activity_uom' => 'liter',
            'source_module' => 'Manufacturing',
            'source_reference' => 'GENSET-A1',
        ]);

        $this->assertInstanceOf(EsgEmission::class, $emission);
        // 1000 * 2.68 = 2680 kg
        $this->assertEquals(2680.0, (float) $emission->co2e_kg);
    }

    public function test_can_purchase_and_retire_carbon_credits(): void
    {
        $service = app(EsgService::class);

        $credit = $service->registerCarbonCredit([
            'certificate_number' => 'VERRA-2026-999',
            'project_name' => 'Borneo Peatland Reforestation',
            'quantity_co2e_tons' => 500.0,
            'cost_per_ton_idr' => 200000.0,
        ]);

        $this->assertInstanceOf(CarbonCredit::class, $credit);
        $this->assertEquals(100000000, $credit->total_cost_idr);

        $retirement = $service->retireOffset($credit->id, [
            'entity_id' => 'HOLDING-ID',
            'retired_quantity_tons' => 200.0,
            'reason' => 'Scope 1 Net Zero Commitment',
        ]);

        $this->assertEquals(200.0, (float) $retirement->retired_quantity_tons);

        // Cannot over-retire
        $this->expectException(\InvalidArgumentException::class);
        $service->retireOffset($credit->id, [
            'entity_id' => 'HOLDING-ID',
            'retired_quantity_tons' => 400.0,
        ]);
    }

    public function test_can_score_supplier_sustainability(): void
    {
        $service = app(EsgService::class);

        $score = $service->evaluateSupplierSustainability([
            'supplier_id' => 'SUP-9988',
            'evaluation_year' => '2026',
            'environmental_score' => 90,
            'social_score' => 85,
            'governance_score' => 80,
            'certification_list' => 'ISO14001, FSC, RSPO',
        ]);

        // (90 * 0.4) + (85 * 0.3) + (80 * 0.3) = 36 + 25.5 + 24 = 85.5
        $this->assertEquals(85.5, (float) $score->overall_score);
        $this->assertEquals('LEAD', $score->rating_level);
    }

    public function test_esg_audit_passes_cleanly(): void
    {
        $service = app(EsgService::class);

        $service->recordEmission([
            'entity_id' => 'MALL-DUTA',
            'scope' => 'scope_2',
            'activity_type' => 'electricity_grid',
            'activity_data_amount' => 5000.0,
            'activity_uom' => 'kWh',
        ]);

        $credit = $service->registerCarbonCredit([
            'project_name' => 'Solar Farm Cirata',
            'quantity_co2e_tons' => 100.0,
        ]);

        $service->retireOffset($credit->id, [
            'entity_id' => 'MALL-DUTA',
            'retired_quantity_tons' => 50.0,
        ]);

        $audit = $service->auditEsg();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEmpty($audit['discrepancies']);
    }

    public function test_esg_web_index_accessible(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('esg.index'));
        $response->assertStatus(200);
        $response->assertSee('ESG, GHG Emissions & Carbon Accounting');
    }
}
