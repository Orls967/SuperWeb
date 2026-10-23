<?php

declare(strict_types=1);

namespace Modules\Egy\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Egy\Application\Actions\DetectGridTransformerLoadAnomalyAction;
use Modules\Egy\Application\Actions\DispatchMicrogridBatteryPeakShavingAction;
use Modules\Egy\Application\Actions\MintRenewableEnergyCertificateRecAction;
use Modules\Egy\Application\Actions\SettlePowerPurchaseAgreementBillingAction;
use Modules\Egy\Application\Actions\TradePeerToPeerSolarSurplusAction;
use Modules\Egy\Domain\Models\GridNode;
use Modules\Egy\Domain\Models\PpaContract;
use Modules\Egy\Domain\Models\SmartMeter;
use Tests\TestCase;

class EgyVerticalSliceHttpAndDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    private function createSampleNodeAndMeter(): array
    {
        $node = GridNode::create([
            'id' => (string) Str::uuid(),
            'node_code' => 'NODE-'.Str::random(5),
            'name' => 'Substation Cikarang Barat 01',
            'substation_region' => 'WEST_JAVA',
            'nominal_voltage_kv' => 20.0,
            'current_load_mw' => 4.5,
            'peak_capacity_mw' => 10.0,
        ]);

        $meter = SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => 'MTR-TEST-'.Str::random(5),
            'consumer_property_type' => 'FACTORY',
            'consumer_property_id' => 'PROP-FAC-01',
            'grid_node_id' => $node->id,
            'total_kwh_accumulated' => 0.0,
            'tariff_category' => 'INDUSTRIAL',
            'status' => 'ACTIVE',
        ]);

        return [$node, $meter];
    }

    public function test_1_authorized_user_can_access_grid_index(): void
    {
        [$node, $meter] = $this->createSampleNodeAndMeter();
        $operator = User::factory()->create(['role' => 'grid_operator']);

        $response = $this->actingAs($operator)->get(route('egy.grid.index'));
        $response->assertOk();
        $response->assertSeeText('Smart Grid Operations & Transformer Load');
        $response->assertSeeText($node->node_code);
    }

    public function test_2_ingest_smart_meter_telemetry_via_http(): void
    {
        [$node, $meter] = $this->createSampleNodeAndMeter();
        $operator = User::factory()->create(['role' => 'grid_operator']);

        $response = $this->actingAs($operator)->post(route('egy.grid.telemetry'), [
            'meter_number' => $meter->meter_serial_number,
            'kwh_consumed' => 350,
            'peak_demand_kw' => 45,
            'power_factor' => 96,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('egy_meter_readings', [
            'meter_id' => $meter->id,
            'kwh_consumed' => 350,
        ]);
    }

    public function test_3_detect_grid_transformer_load_anomaly(): void
    {
        $node = GridNode::create([
            'id' => (string) Str::uuid(),
            'node_code' => 'NODE-OVERLOAD-'.Str::random(4),
            'name' => 'Kawasan Industri GI Karawang',
            'substation_region' => 'WEST_JAVA',
            'nominal_voltage_kv' => 20.0,
            'current_load_mw' => 9.8,
            'peak_capacity_mw' => 10.0, // 98% -> critical overload
        ]);

        $action = app(DetectGridTransformerLoadAnomalyAction::class);
        $result = $action->execute($node->id);

        $this->assertTrue($result['is_anomaly']);
        $this->assertSame('CRITICAL_OVERLOAD', $result['severity']);
        $this->assertSame(98, $result['load_ratio_percent']);
    }

    public function test_4_settle_ppa_billing_posts_ledger(): void
    {
        $ppa = PpaContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'PPA-SOLAR-'.Str::random(5),
            'producer_entity_id' => 'PTY-GENCO-SOLAR',
            'grid_offtaker_id' => 'PTY-PLN-DIST',
            'feed_in_tariff_per_kwh_minor' => 1200.0,
            'surplus_kwh_exported' => 0.0,
            'total_settlement_minor' => 0,
            'status' => 'ACTIVE',
        ]);

        $action = app(SettlePowerPurchaseAgreementBillingAction::class);
        $totalMinor = $action->execute($ppa->id, 50000); // 50,000 kWh * Rp 1,200 = Rp 60,000,000

        $this->assertSame(60000000, $totalMinor);
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'ENERGY_PPA_NET_METER_SETTLE',
            'reference_id' => $ppa->contract_number,
        ]);
    }

    public function test_5_dispatch_microgrid_battery_peak_shaving(): void
    {
        $action = app(DispatchMicrogridBatteryPeakShavingAction::class);
        $run = $action->execute([
            'bess_asset_id' => 'BESS-UNIT-NORTH-01',
            'energy_discharged_kwh' => 200,
            'discharging_revenue_minor' => 500000,
            'charging_cost_minor' => 200000,
        ]);

        $this->assertSame('BESS-UNIT-NORTH-01', $run->bess_asset_id);
        $this->assertSame(300000, $run->net_arbitrage_profit_minor);
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'ENERGY_BESS_ARBITRAGE_PROFIT',
            'reference_id' => $run->run_code,
        ]);
    }

    public function test_6_mint_renewable_energy_certificate_rec(): void
    {
        $action = app(MintRenewableEnergyCertificateRecAction::class);
        $rec = $action->execute([
            'generation_asset_id' => 'SOLAR-CIRATA-PH1',
            'owner_entity_id' => 'PTY-GENCO-CIRATA',
            'energy_mwh' => 150.0,
        ]);

        $this->assertFalse($rec->is_retired);
        $this->assertNotEmpty($rec->certificate_serial);
        $this->assertSame(150.0, (float) $rec->energy_mwh);
    }

    public function test_7_peer_to_peer_solar_surplus_trade(): void
    {
        $action = app(TradePeerToPeerSolarSurplusAction::class);
        $result = $action->execute([
            'seller_party_id' => 'PROSUMER-ROOFTOP-12',
            'buyer_party_id' => 'COMMUNITY-MEMBER-44',
            'surplus_kwh' => 80,
            'clearing_price_minor' => 1350,
        ]);

        $this->assertSame('CLEARED', $result['status']);
        $this->assertSame(108000, $result['total_amount_minor']); // 80 * 1350
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'ENERGY_METER_READING_BILL',
            'reference_id' => $result['trade_code'],
        ]);
    }

    public function test_8_egy_audit_command_and_security_rbac(): void
    {
        $this->createSampleNodeAndMeter();

        $exitCode = Artisan::call('egy:audit');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Energy audit PASSED with 0 discrepancy', Artisan::output());

        // Unauthorized user 403
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get(route('egy.grid.index'))->assertForbidden();

        // Unauthenticated user 302
        auth()->logout();
        $this->get(route('egy.grid.index'))->assertRedirect(route('login'));
    }
}
