<?php

declare(strict_types=1);

namespace Modules\Esg\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Esg\Application\Services\EsgService;
use Modules\Esg\Domain\Models\CarbonCredit;
use Modules\Esg\Domain\Models\EsgEmission;
use Tests\TestCase;

class EsgTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

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

    public function test_record_emission_rejects_unknown_activity_type_without_silent_default(): void
    {
        $service = app(EsgService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Jenis aktivitas emisi tidak dikenal: 'unregistered_factory_smoke'");

        $service->recordEmission([
            'entity_id' => 'PLANT-01',
            'scope' => 'scope_1',
            'activity_type' => 'unregistered_factory_smoke',
            'activity_data_amount' => 500.0,
            'activity_uom' => 'kg',
        ]);
    }

    public function test_document_numbering_uses_gapless_sequence_instead_of_random(): void
    {
        $service = app(EsgService::class);

        // 1. Emission number sequential generation
        $emission1 = $service->recordEmission([
            'entity_id' => 'PLANT-01',
            'scope' => 'scope_1',
            'activity_type' => 'fuel_diesel',
            'activity_data_amount' => 100.0,
            'activity_uom' => 'liter',
        ]);
        $emission2 = $service->recordEmission([
            'entity_id' => 'PLANT-01',
            'scope' => 'scope_1',
            'activity_type' => 'fuel_diesel',
            'activity_data_amount' => 200.0,
            'activity_uom' => 'liter',
        ]);

        $year = date('Y');
        $this->assertSame("EM/ESG/{$year}-00001", $emission1->emission_number);
        $this->assertSame("EM/ESG/{$year}-00002", $emission2->emission_number);

        // 2. Carbon Credit certificate sequential generation
        $credit1 = $service->registerCarbonCredit([
            'project_name' => 'Reforestation Unit A',
            'quantity_co2e_tons' => 100.0,
        ]);
        $credit2 = $service->registerCarbonCredit([
            'project_name' => 'Reforestation Unit B',
            'quantity_co2e_tons' => 150.0,
        ]);

        $this->assertSame("CC/ESG/{$year}-00001", $credit1->certificate_number);
        $this->assertSame("CC/ESG/{$year}-00002", $credit2->certificate_number);

        // 3. Retirement sequential generation
        $retire1 = $service->retireOffset($credit1->id, [
            'entity_id' => 'HOLDING-ID',
            'retired_quantity_tons' => 10.0,
        ]);
        $retire2 = $service->retireOffset($credit1->id, [
            'entity_id' => 'HOLDING-ID',
            'retired_quantity_tons' => 20.0,
        ]);

        $this->assertSame("RET/ESG/{$year}-00001", $retire1->retirement_number);
        $this->assertSame("RET/ESG/{$year}-00002", $retire2->retirement_number);
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
        $user = User::factory()->create(['role' => 'esg_officer']);

        $response = $this->actingAs($user)->get(route('esg.index'));
        $response->assertStatus(200);
        $response->assertSee('ESG, GHG Emissions & Carbon Accounting');
    }
}
