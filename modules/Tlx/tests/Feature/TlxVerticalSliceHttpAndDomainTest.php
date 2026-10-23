<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Tlx\Application\Actions\ActivateBulkIotEsimProfileAction;
use Modules\Tlx\Application\Actions\AllocateDataCenterRackColocationAction;
use Modules\Tlx\Application\Actions\EnforceDataCenterPueCoolingSlaAction;
use Modules\Tlx\Application\Actions\RateAndBillTelecomCdrBatchAction;
use Modules\Tlx\Application\Actions\SettleInterCarrierRoamingClearingAction;
use Modules\Tlx\Domain\Models\TelecomDataCenter;
use Modules\Tlx\Domain\Models\TelecomLink;
use Modules\Tlx\Domain\Models\TelecomSite;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(LedgerAccountsSeeder::class);
});

function createSampleTlxDataCenter(int $totalCapacity = 50, int $occupied = 10): TelecomDataCenter
{
    return TelecomDataCenter::create([
        'id' => (string) Str::uuid(),
        'dc_code' => 'DC-JKT-'.Str::random(4),
        'name' => 'Jakarta Hyperscale Edge DC',
        'city' => 'Jakarta',
        'total_facility_power_kw' => 1500.0,
        'it_load_power_kw' => 1000.0,
        'measured_pue' => 1.45,
        'total_racks_capacity' => $totalCapacity,
        'occupied_racks' => $occupied,
    ]);
}

function createSampleTlxSites(): array
{
    $siteA = TelecomSite::create([
        'id' => (string) Str::uuid(),
        'site_code' => 'SITE-A-'.Str::random(4),
        'site_name' => 'Kuningan 5G Base Station',
        'site_type' => 'ROOFTOP',
        'latitude' => -6.22,
        'longitude' => 106.82,
        'region' => 'DKI_JAKARTA',
        'status' => 'ONLINE',
    ]);

    $siteB = TelecomSite::create([
        'id' => (string) Str::uuid(),
        'site_code' => 'SITE-B-'.Str::random(4),
        'site_name' => 'Sudirman 5G Micro Cell',
        'site_type' => 'MONOPOLE',
        'latitude' => -6.21,
        'longitude' => 106.81,
        'region' => 'DKI_JAKARTA',
        'status' => 'ONLINE',
    ]);

    return [$siteA, $siteB];
}

test('1 authorized user can access tlx network index', function () {
    createSampleTlxSites();
    $user = User::factory()->create(['role' => 'noc_engineer']);

    $response = $this->actingAs($user)->get(route('tlx.network.index'));
    $response->assertOk();
    $response->assertSeeText('Telecom & 5G Network Management');
    $response->assertSeeText('Aktivasi Bulk eSIM IoT');
});

test('2 provision 5g network slice via http', function () {
    [$siteA, $siteB] = createSampleTlxSites();
    $link = TelecomLink::create([
        'id' => (string) Str::uuid(),
        'link_code' => 'LNK-5G-TEST',
        'origin_site_id' => $siteA->site_code,
        'dest_site_id' => $siteB->site_code,
        'link_medium' => 'WIRELESS_5G',
        'bandwidth_capacity_gbps' => 10.0,
        'allocated_bandwidth_gbps' => 1.0,
        'target_sla_uptime_pct' => 99.99,
        'actual_sla_uptime_pct' => 99.99,
        'downtime_minutes_month' => 0,
        'status' => 'ACTIVE',
    ]);

    $user = User::factory()->create(['role' => 'tlx_admin']);

    $response = $this->actingAs($user)->post(route('tlx.network.slices.provision'), [
        'link_id' => $link->id,
        'slice_name' => 'Autonomous Shuttle URLLC',
        'enterprise_entity_id' => 'TNT-SMARTPORT-01',
        'slice_bandwidth_gbps' => 2.0,
        'monthly_fee_minor' => 35000000,
    ]);

    $response->assertRedirect(route('tlx.network.slices'));
    $response->assertSessionHas('success');

    $this->assertSame(3.0, $link->fresh()->allocated_bandwidth_gbps);
    $this->assertDatabaseHas('bank_ledger_transactions', [
        'type' => 'TELECOM_5G_SLICE_PROVISION',
    ]);
});

test('3 activate bulk iot esim profile', function () {
    $action = app(ActivateBulkIotEsimProfileAction::class);
    $sims = $action->execute([
        'wallet_id' => 'WLT-ENERGY-01',
        'plan_code' => 'IOT_SMART_METER',
        'count' => 5,
        'quota_allowance_gb' => 20.0,
    ]);

    $this->assertCount(5, $sims);
    $this->assertDatabaseCount('tlx_sim_subscriptions', 5);
});

test('4 allocate datacenter rack colocation and ledger', function () {
    $dc = createSampleTlxDataCenter(totalCapacity: 50, occupied: 10);
    $action = app(AllocateDataCenterRackColocationAction::class);

    $contract = $action->execute([
        'dc_id' => $dc->id,
        'tenant_party_id' => 'TNT-AI-RESEARCH',
        'rack_units_allocated' => 4,
        'monthly_rack_fee_minor' => 60000000,
    ]);

    $this->assertSame(14, $dc->fresh()->occupied_racks);
    $this->assertSame(4, $contract->rack_units_allocated);
    $this->assertSame('UP', $contract->port_status);

    $this->assertDatabaseHas('bank_ledger_transactions', [
        'type' => 'TELECOM_DC_RACK_COLOCATION',
        'reference_id' => $contract->contract_number,
    ]);
});

test('5 enforce datacenter pue cooling sla with penalty', function () {
    $dc = createSampleTlxDataCenter();
    $actionRack = app(AllocateDataCenterRackColocationAction::class);
    $actionRack->execute([
        'dc_id' => $dc->id,
        'tenant_party_id' => 'TNT-CORP-A',
        'rack_units_allocated' => 2,
        'monthly_rack_fee_minor' => 30000000,
    ]);

    $actionPue = app(EnforceDataCenterPueCoolingSlaAction::class);

    // Measured PUE 1.80 breaches SLA threshold 1.50
    $result = $actionPue->execute([
        'dc_id' => $dc->id,
        'measured_pue' => 1.80,
        'sla_threshold' => 1.50,
    ]);

    $this->assertTrue($result['sla_breached']);
    $this->assertGreaterThan(0, $result['penalty_minor']);
    $this->assertSame(1.80, $dc->fresh()->measured_pue);

    $this->assertDatabaseHas('bank_ledger_transactions', [
        'type' => 'TELECOM_PUE_SLA_PENALTY_CREDIT',
    ]);
});

test('6 rate and bill telecom cdr batch', function () {
    $action = app(RateAndBillTelecomCdrBatchAction::class);
    $invoice = $action->execute([
        'owner_entity_id' => 'TNT-SMARTCITY-TRANSPORT',
        'billing_period' => '2026-10',
        'active_device_count' => 15,
        'total_consumed_mb' => 10000.0,
        'rate_per_mb_minor' => 50,
    ]);

    $this->assertSame(500000, $invoice->total_charge_minor);

    $this->assertDatabaseHas('bank_ledger_transactions', [
        'type' => 'TELECOM_CDR_BATCH_BILLING',
        'reference_id' => $invoice->invoice_number,
    ]);
});

test('7 settle inter carrier roaming clearing and ledger', function () {
    $action = app(SettleInterCarrierRoamingClearingAction::class);
    $settlement = $action->execute([
        'settlement_batch_code' => 'ROAM-SINGTEL-202610',
        'partner_operator_code' => 'SINGTEL-SG',
        'period_month' => '2026-10',
        'inbound_minutes' => 10000,
        'outbound_minutes' => 6000,
        'inbound_rate_minor' => 300,  // 3,000,000
        'outbound_rate_minor' => 200, // 1,200,000
    ]);

    // Net settlement = 3,000,000 - 1,200,000 = 1,800,000
    $this->assertSame(1800000, $settlement->net_settlement_minor);
    $this->assertSame('SETTLED', $settlement->status);

    $this->assertDatabaseHas('bank_ledger_transactions', [
        'type' => 'TELECOM_ROAMING_CLEARING_SETTLE',
        'reference_id' => 'ROAM-SINGTEL-202610',
    ]);
});

test('8 tlx audit command and security rbac', function () {
    createSampleTlxSites();
    createSampleTlxDataCenter();

    $exitCode = Artisan::call('tlx:audit');
    $this->assertSame(0, $exitCode);
    $this->assertStringContainsString('Tlx audit PASSED with 0 discrepancy', Artisan::output());

    // Unauthorized user gets 403
    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->get(route('tlx.network.index'))->assertForbidden();

    // Unauthenticated gets 302 redirect to login
    auth()->logout();
    $this->get(route('tlx.network.index'))->assertRedirect(route('login'));
});
