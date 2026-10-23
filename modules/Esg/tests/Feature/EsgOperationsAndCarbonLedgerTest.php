<?php

declare(strict_types=1);

namespace Modules\Esg\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Esg\Application\Actions\CalculateScope123GhgEmissionAction;
use Modules\Esg\Application\Actions\EvaluateSupplierGriEsgScorecardAction;
use Modules\Esg\Application\Actions\PurchaseAndInventoryCarbonCreditLedgerAction;
use Modules\Esg\Application\Actions\RetireCarbonCreditForNetZeroOffsetAction;
use Modules\Esg\Domain\Models\CarbonCredit;
use Tests\TestCase;

class EsgOperationsAndCarbonLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    public function test_calculate_scope_1_direct_emission(): void
    {
        $action = app(CalculateScope123GhgEmissionAction::class);

        // 5,000 Liters Diesel * 2.68 kg CO2e/liter = 13,400 kg CO2e = 13.4 tons
        $res = $action->execute([
            'entity_id' => 'PLANT-CIKARANG-01',
            'scope' => 'scope_1',
            'activity_type' => 'diesel_generator',
            'activity_data_amount' => 5000.0,
            'activity_uom' => 'Liters',
            'emission_factor' => 2.68,
            'reporting_period' => '2026-10-01',
        ]);

        $this->assertNotNull($res['emission']->id);
        $this->assertSame(13400.0, $res['co2e_kg']);
        $this->assertSame(13.4, $res['co2e_tons']);
        $this->assertSame('scope_1', $res['emission']->scope);
    }

    public function test_calculate_scope_2_electricity_emission(): void
    {
        $action = app(CalculateScope123GhgEmissionAction::class);

        // 100,000 kWh * 0.85 kg CO2e/kWh = 85,000 kg CO2e = 85.0 tons
        $res = $action->execute([
            'entity_id' => 'HQ-JAKARTA-TOWER',
            'scope' => 'scope_2',
            'activity_type' => 'grid_electricity',
            'activity_data_amount' => 100000.0,
            'activity_uom' => 'kWh',
            'emission_factor' => 0.85,
            'reporting_period' => '2026-10-01',
        ]);

        $this->assertSame(85000.0, $res['co2e_kg']);
        $this->assertSame(85.0, $res['co2e_tons']);
    }

    public function test_calculate_scope_3_freight_emission(): void
    {
        $action = app(CalculateScope123GhgEmissionAction::class);

        // 20,000 ton-km freight * 0.082 kg CO2e/ton-km = 1,640 kg CO2e
        $res = $action->execute([
            'entity_id' => 'LOGISTICS-HUB-SURABAYA',
            'scope' => 'scope_3',
            'activity_type' => 'intermodal_freight',
            'activity_data_amount' => 20000.0,
            'activity_uom' => 'ton_km',
            'emission_factor' => 0.082,
            'reporting_period' => '2026-10-01',
        ]);

        $this->assertSame(1640.0, $res['co2e_kg']);
        $this->assertSame(1.64, $res['co2e_tons']);
    }

    public function test_calculate_scope_emission_validates_scope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $action = app(CalculateScope123GhgEmissionAction::class);

        $action->execute([
            'entity_id' => 'ENT-01',
            'scope' => 'scope_invalid',
            'activity_type' => 'fuel',
            'activity_data_amount' => 100.0,
            'activity_uom' => 'L',
            'emission_factor' => 1.0,
            'reporting_period' => '2026-10-01',
        ]);
    }

    public function test_purchase_carbon_credit_and_post_inventory_ledger(): void
    {
        $action = app(PurchaseAndInventoryCarbonCreditLedgerAction::class);

        // 2,000 tons @ 150,000 IDR/ton = 300,000,000 IDR
        $res = $action->execute([
            'certificate_number' => 'VCS-SUMATRA-2026-99',
            'registry' => 'VERRA',
            'project_name' => 'Rimba Raya Peatland Conservation',
            'project_type' => 'reforestation',
            'vintage_year' => 2025,
            'quantity_co2e_tons' => 2000.0,
            'cost_per_ton_idr' => 150000,
        ]);

        $this->assertNotNull($res['carbon_credit']->id);
        $this->assertSame(300000000, $res['total_cost_idr']);
        $this->assertTrue($res['ledger_posted']);
        $this->assertSame('active', $res['carbon_credit']->status);
    }

    public function test_retire_carbon_credit_for_net_zero_and_post_offset_ledger(): void
    {
        $purchaseAction = app(PurchaseAndInventoryCarbonCreditLedgerAction::class);
        $pRes = $purchaseAction->execute([
            'certificate_number' => 'IDX-CARBON-2026-01',
            'registry' => 'IDX_CARBON',
            'project_name' => 'Geothermal Clean Energy Pertamina',
            'project_type' => 'renewable_energy',
            'vintage_year' => 2025,
            'quantity_co2e_tons' => 1000.0,
            'cost_per_ton_idr' => 120000,
        ]);

        $retireAction = app(RetireCarbonCreditForNetZeroOffsetAction::class);
        // Retire 400 tons -> 400 * 120,000 = 48,000,000 IDR
        $retRes = $retireAction->execute(
            $pRes['carbon_credit'],
            'PLANT-CIKARANG-01',
            400.0,
            'Offset Scope 1 Generator Emissions for Net-Zero Reporting'
        );

        $this->assertNotNull($retRes['retirement']->id);
        $this->assertSame(48000000, $retRes['offset_cost_idr']);
        $this->assertSame(600.0, $retRes['remaining_credit_tons']);
        $this->assertFalse($retRes['is_fully_retired']);
        $this->assertSame('active', $pRes['carbon_credit']->fresh()->status);
    }

    public function test_evaluate_supplier_gri_esg_scorecard_rating_levels(): void
    {
        $action = app(EvaluateSupplierGriEsgScorecardAction::class);

        // Env: 90 (x0.4 = 36), Soc: 85 (x0.3 = 25.5), Gov: 85 (x0.3 = 25.5) -> Overall: 87.0 -> LEAD
        $scoreLead = $action->execute([
            'supplier_id' => 'SUPP-SUSTAINABLE-01',
            'evaluation_year' => '2026',
            'environmental_score' => 90,
            'social_score' => 85,
            'governance_score' => 85,
            'certification_list' => 'ISO14001,PROPER_EMAS',
        ]);

        $this->assertSame(87.0, (float) $scoreLead->overall_score);
        $this->assertSame('LEAD', $scoreLead->rating_level);

        // High risk scenario: Env 30, Soc 40, Gov 40 -> Overall: 36.0 -> HIGH_RISK
        $scoreRisk = $action->execute([
            'supplier_id' => 'SUPP-RISK-02',
            'evaluation_year' => '2026',
            'environmental_score' => 30,
            'social_score' => 40,
            'governance_score' => 40,
        ]);

        $this->assertSame('HIGH_RISK', $scoreRisk->rating_level);
    }

    public function test_esg_operations_controller_endpoints(): void
    {
        $this->actingAs($this->user);

        // 1. Cockpit Index
        $respDash = $this->get('/esg/operations');
        $respDash->assertOk();

        // 2. Calculate Emission API
        $respEm = $this->postJson('/esg/operations/emissions', [
            'entity_id' => 'FLEET-LOG-JKT',
            'scope' => 'scope_1',
            'activity_type' => 'gasoline_trucks',
            'activity_data_amount' => 1200.0,
            'activity_uom' => 'Liters',
            'emission_factor' => 2.31,
            'reporting_period' => '2026-10-15',
        ]);
        $respEm->assertStatus(201);
        $this->assertArrayHasKey('co2e_kg', $respEm->json('data'));

        // 3. Purchase Credit API
        $respBuy = $this->postJson('/esg/operations/credits/purchase', [
            'certificate_number' => 'GS-2026-SOLAR-09',
            'registry' => 'GOLD_STANDARD',
            'project_name' => 'Cirata Floating Solar PV',
            'project_type' => 'renewable_energy',
            'vintage_year' => 2026,
            'quantity_co2e_tons' => 500.0,
            'cost_per_ton_idr' => 180000,
        ]);
        $respBuy->assertStatus(201);
        $creditId = $respBuy->json('data.carbon_credit.id');

        // 4. Retire Credit API
        $credit = CarbonCredit::find($creditId);
        $respRet = $this->postJson("/esg/operations/credits/{$creditId}/retire", [
            'entity_id' => 'FLEET-LOG-JKT',
            'retired_quantity_tons' => 100.0,
            'reason' => 'Annual fleet offset',
        ]);
        $respRet->assertStatus(201);
        $this->assertSame(18000000, $respRet->json('data.offset_cost_idr'));

        // 5. Evaluate Supplier API
        $respSupp = $this->postJson('/esg/operations/supplier-score', [
            'supplier_id' => 'SUPP-ECO-99',
            'evaluation_year' => '2026',
            'environmental_score' => 75,
            'social_score' => 80,
            'governance_score' => 70,
        ]);
        $respSupp->assertStatus(201);
        $this->assertSame('ADVANCED', $respSupp->json('supplier_score.rating_level'));
    }
}
