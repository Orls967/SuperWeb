<?php

declare(strict_types=1);

namespace Tests\Acceptance\Telecom\Eksplorasi;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Tlx\Application\Actions\ActivateBulkIotEsimProfileAction;
use Modules\Tlx\Application\Actions\AllocateDataCenterRackColocationAction;
use Modules\Tlx\Application\Actions\EnforceDataCenterPueCoolingSlaAction;
use Modules\Tlx\Application\Actions\ProvisionEnterprise5GNetworkSliceAction;
use Modules\Tlx\Application\Actions\RateAndBillTelecomCdrBatchAction;
use Modules\Tlx\Application\Actions\SettleInterCarrierRoamingClearingAction;
use Modules\Tlx\Application\Services\DataCenterAndCloudOpsService;
use Modules\Tlx\Application\Services\IspAndMobileServices;
use Modules\Tlx\Application\Services\TelecomNetworkAndIotService;
use Modules\Tlx\Domain\Models\ColocationContract;
use Modules\Tlx\Domain\Models\TelecomDataCenter;
use Modules\Tlx\Domain\Models\TelecomLink;
use Modules\Tlx\Domain\Models\TelecomSite;
use Tests\TestCase;

/**
 * L14.0: Buktikan kondisi kode lama Lini 14 (Telko & Data Center).
 *
 * Klasifikasi Action Lama:
 * 1. ActivateBulkIotEsimProfileAction: SALAH (memakai rand() & microtime() untuk MSISDN & ICCID, tidak terikat ke perangkat IoT spesifik, tidak memiliki idempotency key).
 * 2. RateAndBillTelecomCdrBatchAction: SALAH (menerima total_consumed_mb langsung tanpa rating CDR mentah per-sesi 1 KB, tidak ada dedup, tidak lewat Intercompany).
 * 3. AllocateDataCenterRackColocationAction: SALAH (memakai microtime() untuk nomor kontrak, tanpa idempotency key pemanggil, mutasi okupansi tanpa lock).
 * 4. EnforceDataCenterPueCoolingSlaAction: SALAH (PUE diterima langsung sebagai parameter input float alih-alih dihitung dari bacaan daya fasilitas vs daya IT).
 * 5. ProvisionEnterprise5GNetworkSliceAction: SEBAGIAN (alokasi bandwidth tautan memeriksa batas, tetapi ledger posting langsung tanpa Intercompany port).
 * 6. SettleInterCarrierRoamingClearingAction: SALAH (Str::random() pada batch code, tanpa idempotency key pemanggil).
 */
class TelecomExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_explores_activate_bulk_iot_esim_profile_action(): void
    {
        $action = app(ActivateBulkIotEsimProfileAction::class);
        $result = $action->execute([
            'count' => 2,
            'plan_code' => 'IOT_EXPLORE_01',
            'quota_allowance_gb' => 5.0,
            'wallet_id' => 'WLT-TLX-EXP-01',
        ]);

        expect($result)->toHaveCount(2)
            ->and($result[0]->plan_code)->toBe('IOT_EXPLORE_01')
            ->and($result[0]->iccid)->toStartWith('896200')
            ->and($result[0]->status)->toBe('ACTIVE');

        // Bukti kelemahan kode lama: tidak ada verifikasi unik per perangkat IoT tunggal
        expect($result[0]->msisdn)->not->toBeNull();
    }

    public function test_explores_rate_and_bill_telecom_cdr_batch_action(): void
    {
        $action = app(RateAndBillTelecomCdrBatchAction::class);
        $invoice = $action->execute([
            'owner_entity_id' => 'ENT-LOGISTICS-01',
            'total_consumed_mb' => 250.5,
            'active_device_count' => 15,
            'billing_period' => '2026-10',
            'rate_per_mb_minor' => 50,
        ]);

        expect($invoice->invoice_number)->toStartWith('INV-CDR-')
            ->and($invoice->owner_entity_id)->toBe('ENT-LOGISTICS-01')
            ->and($invoice->total_charge_minor)->toBe((int) round(250.5 * 50));

        // Bukti kelemahan kode lama: menerima total agregat langsung, bukan dari CDR mentah yang dirating 1 KB
        expect($invoice->total_consumed_mb)->toBe(250.5);
    }

    public function test_explores_allocate_datacenter_rack_colocation_action(): void
    {
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-CGK-01',
            'name' => 'Cengkareng Tier IV Data Center',
            'city' => 'Jakarta Barat',
            'total_facility_power_kw' => 5000.0,
            'it_load_power_kw' => 4000.0,
            'measured_pue' => 1.25,
            'total_racks_capacity' => 100,
            'occupied_racks' => 20,
        ]);

        $action = app(AllocateDataCenterRackColocationAction::class);
        $contract = $action->execute([
            'dc_id' => $dc->id,
            'tenant_party_id' => 'PT-FINTECH-NASIONAL',
            'rack_units_allocated' => 5,
            'monthly_rack_fee_minor' => 75000000,
        ]);

        expect($contract->contract_number)->toStartWith('COLO-')
            ->and($contract->tenant_party_id)->toBe('PT-FINTECH-NASIONAL')
            ->and($contract->rack_units_allocated)->toBe(5)
            ->and($dc->fresh()->occupied_racks)->toBe(25);
    }

    public function test_explores_enforce_datacenter_pue_cooling_sla_action(): void
    {
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-SBY-01',
            'name' => 'Surabaya Edge Data Center',
            'city' => 'Surabaya',
            'total_facility_power_kw' => 2000.0,
            'it_load_power_kw' => 1200.0,
            'measured_pue' => 1.30,
            'total_racks_capacity' => 50,
            'occupied_racks' => 10,
        ]);

        ColocationContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'COLO-SBY-001',
            'tenant_party_id' => 'PARTY-CORP-A',
            'dc_id' => $dc->id,
            'rack_units_allocated' => 4,
            'monthly_rack_fee_minor' => 40000000,
            'power_rate_per_kwh_minor' => 1800,
            'monthly_kwh_consumed' => 5000.0,
            'port_status' => 'UP',
        ]);

        $action = app(EnforceDataCenterPueCoolingSlaAction::class);
        $result = $action->execute([
            'dc_id' => $dc->id,
            'measured_pue' => 1.80, // Breaching threshold 1.50
            'sla_threshold' => 1.50,
        ]);

        expect($result['sla_breached'])->toBeTrue()
            ->and($result['penalty_minor'])->toBeGreaterThan(0)
            ->and($dc->fresh()->measured_pue)->toBe(1.80);
    }

    public function test_explores_provision_enterprise_5g_network_slice_action(): void
    {
        $siteA = TelecomSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-JKT-01',
            'site_name' => 'Tower Sudirman',
            'site_type' => 'TOWER',
            'region' => 'DKI_JAKARTA',
        ]);

        $siteB = TelecomSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-JKT-02',
            'site_name' => 'POP Kuningan',
            'site_type' => 'POP',
            'region' => 'DKI_JAKARTA',
        ]);

        $link = TelecomLink::create([
            'id' => (string) Str::uuid(),
            'link_code' => 'LNK-SUD-KUN-01',
            'origin_site_id' => $siteA->id,
            'dest_site_id' => $siteB->id,
            'link_medium' => 'FIBER',
            'bandwidth_capacity_gbps' => 100.0,
            'allocated_bandwidth_gbps' => 20.0,
        ]);

        $action = app(ProvisionEnterprise5GNetworkSliceAction::class);
        $result = $action->execute([
            'link_id' => $link->id,
            'slice_name' => 'URLLC-HOSPITAL-AMBULANCE',
            'slice_bandwidth_gbps' => 10.0,
            'enterprise_entity_id' => 'ENT-HEALTH-01',
            'monthly_fee_minor' => 25000000,
        ]);

        expect($result['slice_code'])->toStartWith('SLICE-5G-')
            ->and($result['monthly_fee_minor'])->toBe(25000000)
            ->and($link->fresh()->allocated_bandwidth_gbps)->toBe(30.0);
    }

    public function test_explores_settle_inter_carrier_roaming_clearing_action(): void
    {
        $action = app(SettleInterCarrierRoamingClearingAction::class);
        $settlement = $action->execute([
            'settlement_batch_code' => 'ROAM-2026-10-TELKOM',
            'partner_operator_code' => 'TELKOM_ID',
            'period_month' => '2026-10',
            'inbound_minutes' => 100000,
            'outbound_minutes' => 80000,
            'inbound_rate_minor' => 250,
            'outbound_rate_minor' => 250,
        ]);

        expect($settlement->settlement_batch_code)->toBe('ROAM-2026-10-TELKOM')
            ->and($settlement->partner_operator_code)->toBe('TELKOM_ID')
            ->and($settlement->inbound_receivable_minor)->toBe(25000000)
            ->and($settlement->outbound_payable_minor)->toBe(20000000)
            ->and($settlement->net_settlement_minor)->toBe(5000000)
            ->and($settlement->status)->toBe('SETTLED');
    }

    public function test_explores_telecom_services(): void
    {
        $netService = app(TelecomNetworkAndIotService::class);
        $dcService = app(DataCenterAndCloudOpsService::class);
        $ispService = app(IspAndMobileServices::class);

        expect($netService)->toBeInstanceOf(TelecomNetworkAndIotService::class)
            ->and($dcService)->toBeInstanceOf(DataCenterAndCloudOpsService::class)
            ->and($ispService)->toBeInstanceOf(IspAndMobileServices::class);
    }

    public function test_explores_tlx_audit_command(): void
    {
        $exitCode = Artisan::call('tlx:audit');
        $output = Artisan::output();

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('Telecom Sites')
            ->and($output)->toContain('Data Center Facilities')
            ->and($output)->toContain('Tlx audit PASSED with 0 discrepancy.');
    }

    public function test_explores_route_authorization_by_role(): void
    {
        $admin = User::factory()->create(['role' => 'tlx_admin']);
        $guest = User::factory()->create(['role' => 'customer']);

        $responseAdmin = $this->actingAs($admin)->get('/telecom');
        expect($responseAdmin->status())->toBe(200);

        $responseGuest = $this->actingAs($guest)->get('/telecom');
        expect($responseGuest->status())->toBe(403);
    }
}
