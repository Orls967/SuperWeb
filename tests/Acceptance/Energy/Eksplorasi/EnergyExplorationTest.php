<?php

declare(strict_types=1);

namespace Tests\Acceptance\Energy\Eksplorasi;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Egy\Application\Actions\DetectGridTransformerLoadAnomalyAction;
use Modules\Egy\Application\Actions\DispatchMicrogridBatteryPeakShavingAction;
use Modules\Egy\Application\Actions\IngestSmartGridMeterTelemetryAction;
use Modules\Egy\Application\Actions\MintRenewableEnergyCertificateRecAction;
use Modules\Egy\Application\Actions\SettlePowerPurchaseAgreementBillingAction;
use Modules\Egy\Application\Actions\TradePeerToPeerSolarSurplusAction;
use Modules\Egy\Application\Services\CarbonTradingAndEsgMarketService;
use Modules\Egy\Application\Services\DistrictUtilityAndEscoService;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Egy\Application\Services\MicrogridAndResilienceService;
use Modules\Egy\Domain\Models\GenerationAsset;
use Modules\Egy\Domain\Models\GridNode;
use Modules\Egy\Domain\Models\PpaContract;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Tests\TestCase;

class EnergyExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_explores_ingest_smart_grid_meter_telemetry_action(): void
    {
        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'SMTR-TEST-001',
            'consumer_property_type' => 'MALL',
            'consumer_property_id' => 'PROP-MALL-001',
            'total_kwh_accumulated' => 100.0,
            'tariff_category' => 'BUSINESS_TOU',
            'status' => 'ACTIVE',
        ]);

        $action = app(IngestSmartGridMeterTelemetryAction::class);

        // Call 1: Input takes kwh_consumed directly from payload instead of calculating from cumulative register
        $reading1 = $action->execute([
            'meter_number' => 'SMTR-TEST-001',
            'kwh_consumed' => 25,
        ]);

        // Evidence: reading stored raw interval kwh directly
        $this->assertEquals(25, $reading1->kwh_consumed);
        $this->assertEquals(1450, $reading1->rate_per_kwh_minor); // default fallback rate

        // Call 2: Duplicate call with identical parameters succeeds without collision check
        $reading2 = $action->execute([
            'meter_number' => 'SMTR-TEST-001',
            'kwh_consumed' => 25,
        ]);
        $this->assertNotEquals($reading1->id, $reading2->id); // Random UUID generated each time
    }

    public function test_explores_settle_power_purchase_agreement_billing_action(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-EXP-001',
            'producer_entity_id' => 'PTY-SOLAR-001',
            'grid_offtaker_id' => 'ENT-EGY-001',
            'feed_in_tariff_per_kwh_minor' => 1200,
            'surplus_kwh_exported' => 0,
            'total_settlement_minor' => 0,
            'status' => 'ACTIVE',
        ]);

        $action = app(SettlePowerPurchaseAgreementBillingAction::class);
        $totalBill = $action->execute((string) $ppa->id, 100);

        // Evidence: Settlement uses single-sided posting, does not mirror across intercompany entities
        $this->assertEquals(120000, $totalBill);
    }

    public function test_explores_dispatch_microgrid_battery_peak_shaving_action(): void
    {
        $action = app(DispatchMicrogridBatteryPeakShavingAction::class);
        $run = $action->execute([
            'bess_asset_id' => 'BESS-EXP-001',
            'energy_discharged_kwh' => 50,
        ]);

        // Evidence: constant hardcoded 99.8% SoH and formula-based discharge rates instead of dynamic programming
        $this->assertEquals(99.8, $run->state_of_health_pct);
        $this->assertEquals(50 * 2500, $run->discharging_revenue_minor);
        $this->assertEquals(50 * 1000, $run->charging_cost_minor);
    }

    public function test_explores_detect_grid_transformer_load_anomaly_action(): void
    {
        $node = GridNode::create([
            'id' => (string) Str::uuid(),
            'node_code' => 'NODE-EXP-001',
            'name' => 'Substation A',
            'substation_region' => 'REGION-WEST',
            'nominal_voltage_kv' => 20.0,
            'current_load_mw' => 9.8,
            'peak_capacity_mw' => 10.0,
        ]);

        $action = app(DetectGridTransformerLoadAnomalyAction::class);
        $result = $action->execute((string) $node->id);

        $this->assertTrue($result['is_anomaly']);
        $this->assertEquals('CRITICAL_OVERLOAD', $result['severity']);
        $this->assertEquals(98, $result['load_ratio_percent']);
    }

    public function test_explores_mint_renewable_energy_certificate_action(): void
    {
        $asset = GenerationAsset::create([
            'id' => (string) Str::uuid(),
            'asset_code' => 'GEN-EXP-001',
            'name' => 'Cirata Solar Farm',
            'asset_type' => 'SOLAR_FARM',
            'installed_capacity_mw' => 50.0,
            'current_output_mw' => 30.0,
            'status' => 'ONLINE',
        ]);

        $action = app(MintRenewableEnergyCertificateRecAction::class);
        $rec = $action->execute([
            'generation_asset_id' => (string) $asset->id,
            'energy_mwh' => 10.0,
        ]);

        $this->assertStringStartsWith('REC-', $rec->certificate_serial);
        $this->assertEquals(10.0, $rec->energy_mwh);
    }

    public function test_explores_trade_peer_to_peer_solar_surplus_action(): void
    {
        $action = app(TradePeerToPeerSolarSurplusAction::class);
        $result = $action->execute([
            'seller_party_id' => 'PTY-EXP-SELLER',
            'buyer_party_id' => 'PTY-EXP-BUYER',
            'surplus_kwh' => 50,
            'clearing_price_minor' => 1400,
        ]);

        $this->assertEquals('CLEARED', $result['status']);
        $this->assertEquals(70000, $result['total_amount_minor']);
    }

    public function test_explores_energy_services(): void
    {
        $this->assertInstanceOf(EnergyGridAndMeteringService::class, app(EnergyGridAndMeteringService::class));
        $this->assertInstanceOf(MicrogridAndResilienceService::class, app(MicrogridAndResilienceService::class));
        $this->assertInstanceOf(DistrictUtilityAndEscoService::class, app(DistrictUtilityAndEscoService::class));
        $this->assertInstanceOf(CarbonTradingAndEsgMarketService::class, app(CarbonTradingAndEsgMarketService::class));
    }

    public function test_explores_consolidated_invoice_table(): void
    {
        $invoice = UtilityConsolidatedInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV-EXP-001',
            'property_id' => 'PROP-001',
            'billing_period' => '2026-10',
            'electricity_charge_minor' => 500000,
            'water_charge_minor' => 100000,
            'district_cooling_charge_minor' => 50000,
            'gas_charge_minor' => 20000,
            'total_consolidated_minor' => 670000,
            'status' => 'PENDING',
        ]);

        // Evidence: Invoice amounts are directly passed in attributes rather than aggregated from raw factual intervals
        $this->assertEquals(670000, $invoice->total_consolidated_minor);
    }

    public function test_explores_audit_command(): void
    {
        $exitCode = Artisan::call('egy:audit');
        $this->assertEquals(0, $exitCode);
    }

    public function test_explores_route_authorization_by_role(): void
    {
        // Unauthenticated -> redirect to login (302)
        $response = $this->get('/energy');
        $this->assertEquals(302, $response->getStatusCode());

        // Authenticated with grid_operator -> 200
        $user = User::factory()->create(['role' => 'grid_operator']);
        $response = $this->actingAs($user)->get('/energy');
        $this->assertEquals(200, $response->getStatusCode());
    }
}
