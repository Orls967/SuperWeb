<?php

declare(strict_types=1);

namespace Modules\Egy\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Egy\Application\Actions\BalanceGencoDispatchAnd15MinSmartMeterSettlementAction;
use Modules\Egy\Application\Actions\ClearCarbonMarketplaceRecSpotTradeAndRetirementAction;
use Modules\Egy\Application\Actions\ExecuteIslandedMicrogridBlackStartResilienceAction;
use Modules\Egy\Application\Actions\ReconcileDistrictWaterTreatmentAndNonRevenueWaterLeakAction;
use Tests\TestCase;

class Fase126To129EnergyUtilitiesExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_fase_126_balance_genco_dispatch_merit_order_and_15min_meter_settlement(): void
    {
        $action = app(BalanceGencoDispatchAnd15MinSmartMeterSettlementAction::class);

        $params = [
            'settlement_code' => 'EGY-SETTLE-TEST-001',
            'total_demand_kw' => 5000,
            'generation_assets' => [
                ['asset_id' => 'SOLAR-01', 'name' => 'Solar Farm Cirata', 'capacity_kw' => 3000, 'dispatch_kw' => 2500, 'marginal_cost_minor' => 100],
                ['asset_id' => 'BATT-01', 'name' => 'BESS Substation', 'capacity_kw' => 2000, 'dispatch_kw' => 1500, 'marginal_cost_minor' => 500],
                ['asset_id' => 'GENSET-01', 'name' => 'Backup Peaker', 'capacity_kw' => 2000, 'dispatch_kw' => 1000, 'marginal_cost_minor' => 1500],
            ],
            'meter_readings' => [
                ['meter_id' => 'MTR-MALL-01', 'property_type' => 'MALL', 'kwh_consumed' => 200, 'is_peak_slot' => 1, 'tou_rate_minor' => 2500, 'solar_export_kwh' => 50, 'feed_in_tariff_minor' => 1000],
                ['meter_id' => 'MTR-HOSP-01', 'property_type' => 'HOSPITAL', 'kwh_consumed' => 300, 'is_peak_slot' => 1, 'tou_rate_minor' => 2500, 'solar_export_kwh' => 0, 'feed_in_tariff_minor' => 0],
            ],
        ];

        $res = $action->execute($params);

        $this->assertEquals(5000, $res['total_dispatched_kw']);
        $this->assertEquals(0, $res['curtailment_kw']);
        $this->assertEquals(1250000, $res['total_billed_minor']); // (200 * 2500) + (300 * 2500)
        $this->assertEquals(50000, $res['total_feed_in_minor']); // 50 * 1000

        // Verify ledger transactions balance
        $tx = LedgerTransaction::where('reference_id', 'EGY-SETTLE-TEST-001')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }

    public function test_fase_126_rejects_dispatch_exceeding_installed_asset_capacity(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('exceeds installed capacity');

        $action = app(BalanceGencoDispatchAnd15MinSmartMeterSettlementAction::class);
        $action->execute([
            'settlement_code' => 'EGY-ERR-001',
            'generation_assets' => [
                ['asset_id' => 'SOLAR-01', 'name' => 'Solar Farm', 'capacity_kw' => 1000, 'dispatch_kw' => 1500, 'marginal_cost_minor' => 100],
            ],
            'meter_readings' => [],
        ]);
    }

    public function test_fase_127_reconcile_water_treatment_and_non_revenue_water_leak_detection(): void
    {
        $action = app(ReconcileDistrictWaterTreatmentAndNonRevenueWaterLeakAction::class);

        $params = [
            'batch_code' => 'WTR-LEAK-TEST-01',
            'district_id' => 'DISTRICT-NORTH',
            'water_inflow_m3' => 10000,
            'water_billed_m3' => 8000, // 20% loss -> triggers leak alarm (>15%)
            'water_rate_per_m3_minor' => 12000,
            'cooling_thermal_kwh' => 5000,
            'cooling_rate_minor' => 1500,
        ];

        $res = $action->execute($params);

        $this->assertEquals(2000, $res['water_loss_m3']);
        $this->assertEquals(20, $res['loss_percentage']);
        $this->assertTrue($res['leak_alarm_triggered']);
        $this->assertEquals(96000000, $res['water_billed_minor']); // 8000 * 12000
        $this->assertEquals(7500000, $res['cooling_billed_minor']); // 5000 * 1500
    }

    public function test_fase_127_esco_performance_savings_split_and_consolidated_utility_billing(): void
    {
        $action = app(ReconcileDistrictWaterTreatmentAndNonRevenueWaterLeakAction::class);

        $params = [
            'batch_code' => 'WTR-ESCO-TEST-02',
            'district_id' => 'DISTRICT-SOUTH',
            'water_inflow_m3' => 5000,
            'water_billed_m3' => 4800, // 4% loss -> leak clear
            'water_rate_per_m3_minor' => 10000,
            'verified_energy_saving_minor' => 20000000,
            'esco_split_pct' => 35,
        ];

        $res = $action->execute($params);

        $this->assertFalse($res['leak_alarm_triggered']);
        $this->assertEquals(7000000, $res['esco_fee_minor']); // 35% of 20,000,000

        $tx = LedgerTransaction::where('reference_id', 'WTR-ESCO-TEST-02')->where('type', 'ENERGY_ESCO_SAVINGS_SPLIT')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }

    public function test_fase_128_clear_carbon_spot_trade_and_rec_retirement_without_double_counting(): void
    {
        $action = app(ClearCarbonMarketplaceRecSpotTradeAndRetirementAction::class);

        $params = [
            'trade_code' => 'CRB-TRADE-TEST-01',
            'buyer_entity_id' => 'ENT-HOSPITAL-JKT',
            'seller_entity_id' => 'ENT-SOLAR-FARM',
            'tonnes_co2' => 500,
            'price_per_tonne_minor' => 400000,
            'rec_mwh' => 250,
            'retirement_status' => 'RETIRED',
        ];

        $res = $action->execute($params);

        $this->assertEquals(200000000, $res['carbon_trade_amount_minor']); // 500 * 400000
        $this->assertEquals('RETIRED', $res['rec_retired_status']);
        $this->assertStringStartsWith('REC-2026-', $res['rec_serial_number']);

        $tx = LedgerTransaction::where('reference_id', 'CRB-TRADE-TEST-01')->where('type', 'ENERGY_CARBON_MARKET_TRADE')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }

    public function test_fase_128_cbam_border_tariff_calculation_and_green_lease_discount(): void
    {
        $action = app(ClearCarbonMarketplaceRecSpotTradeAndRetirementAction::class);

        $params = [
            'trade_code' => 'CRB-CBAM-TEST-02',
            'export_tonnage' => 100,
            'emission_intensity_kg_per_tonne' => 500,
            'cbam_tariff_per_kg_minor' => 300,
            'esg_score' => 85, // >= 80 -> 10% discount
            'base_utility_bill_minor' => 50000000,
        ];

        $res = $action->execute($params);

        $this->assertEquals(15000000, $res['cbam_tariff_minor']); // 100 * 500 * 300 = 15,000,000
        $this->assertEquals(5000000, $res['green_discount_minor']); // 10% of 50M
        $this->assertEquals(45000000, $res['net_utility_bill_minor']);
    }

    public function test_fase_129_execute_islanded_microgrid_shedding_with_critical_hospital_priority(): void
    {
        $action = app(ExecuteIslandedMicrogridBlackStartResilienceAction::class);

        $params = [
            'operation_code' => 'MCG-ISLAND-TEST-01',
            'site_id' => 'CAMPUS-ECO-01',
            'grid_status' => 'GRID_DOWN',
            'battery_available_kw' => 1000,
            'genset_available_kw' => 1500, // Total 2500 kW
            'connected_loads' => [
                ['facility' => 'Public Streetlights', 'type' => 'LIGHTING', 'priority' => 4, 'demand_kw' => 800],
                ['facility' => 'Hospital ICU', 'type' => 'CRITICAL_HEALTH', 'priority' => 1, 'demand_kw' => 1200],
                ['facility' => 'Pharmaceutical Cold Storage', 'type' => 'STORAGE', 'priority' => 2, 'demand_kw' => 1000],
                ['facility' => 'Mall Air Conditioning', 'type' => 'COMFORT', 'priority' => 3, 'demand_kw' => 1000],
            ],
        ];

        $res = $action->execute($params);

        $this->assertTrue($res['is_islanded']);
        $this->assertEquals(2500, $res['total_supply_kw']);
        $this->assertEquals(2200, $res['allocated_kw']); // Hospital (1200) + Pharma (1000) = 2200 kW

        // Verify priorities: Priority 1 (Hospital) and Priority 2 (Storage) are POWERED; Priority 3 and 4 are SHED
        $this->assertCount(2, $res['served_loads']);
        $this->assertCount(2, $res['shed_loads']);
        $this->assertEquals('Hospital ICU', $res['served_loads'][0]['facility']);
    }

    public function test_fase_129_battery_storage_arbitrage_and_backup_generator_compliance_audit(): void
    {
        $action = app(ExecuteIslandedMicrogridBlackStartResilienceAction::class);

        $params = [
            'operation_code' => 'MCG-BESS-TEST-02',
            'site_id' => 'SITE-POWER-02',
            'grid_status' => 'NORMAL',
            'discharge_kwh' => 1000,
            'discharge_revenue_minor' => 3500000,
            'charge_cost_minor' => 1200000,
            'backup_compliance_status' => 'PASS',
        ];

        $res = $action->execute($params);

        $this->assertEquals(2300000, $res['net_arbitrage_profit_minor']);
        $this->assertEquals('PASS', $res['backup_compliance_status']);

        $tx = LedgerTransaction::where('reference_id', 'MCG-BESS-TEST-02')->where('type', 'ENERGY_BESS_ARBITRAGE_PROFIT')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }
}
