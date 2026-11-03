<?php

declare(strict_types=1);

namespace Tests\Acceptance\Telecom;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Shared\Domain\Numerics\UsageRater;
use Modules\Tlx\Application\Services\ColocationBillingService;
use Modules\Tlx\Application\Services\ConnectivityBillingService;
use Modules\Tlx\Application\Services\IotDeviceRegistryService;
use Modules\Tlx\Domain\Models\ColocationContract;
use Modules\Tlx\Domain\Models\DcPowerReading;
use Modules\Tlx\Domain\Models\IotConnectivityInvoice;
use Modules\Tlx\Domain\Models\NetworkElement;
use Modules\Tlx\Domain\Models\NocAlarm;
use Modules\Tlx\Domain\Models\TelecomCdr;
use Modules\Tlx\Domain\Models\TelecomDataCenter;
use Tests\TestCase;

/**
 * L14.5: Self-Sabotage & Mutation Verification Test for Lini 14 (Telko & Data Center).
 * Verifies that all sabotage mutations across CDR rating, topology suppression, PUE,
 * DC capacity, and billing are strictly caught by the two-source audit and invariant checks.
 */
class TelecomSabotageAndIntegrityVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected IotDeviceRegistryService $registryService;

    protected ConnectivityBillingService $connectivityBillingService;

    protected ColocationBillingService $colocationBillingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->registryService = app(IotDeviceRegistryService::class);
        $this->connectivityBillingService = app(ConnectivityBillingService::class);
        $this->colocationBillingService = app(ColocationBillingService::class);
    }

    public function test_sabotage_cdr_rating_below_transferred_bytes_is_caught_by_audit(): void
    {
        $device = $this->registryService->registerDevice([
            'device_code' => 'DEV-SAB-CDR-01',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-SAB-01',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-SAB-CDR-01',
        ]);

        $cdr = TelecomCdr::create([
            'id' => (string) Str::uuid(),
            'device_id' => $device['id'],
            'session_id' => 'SESS-SAB-CDR-01',
            'started_at' => '2026-10-01 10:00:00',
            'ended_at' => '2026-10-01 10:05:00',
            'bytes_transferred' => 5000,
            'rated_bytes' => 5120, // valid ceil
            'duration_seconds' => 300,
        ]);

        // Sabotage via raw DB update (bypassing model protection)
        DB::table('tlx_cdrs')->where('id', $cdr->id)->update(['rated_bytes' => 4000]);

        $exitCode = Artisan::call('tlx:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_sabotage_datacenter_occupied_racks_exceeding_capacity_is_caught_by_audit(): void
    {
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-SAB-CAP-01',
            'name' => 'Sabotage DC',
            'city' => 'Jakarta',
            'total_facility_power_kw' => 1000.0,
            'it_load_power_kw' => 800.0,
            'total_racks_capacity' => 50,
            'occupied_racks' => 20,
            'measured_pue' => 1.25,
        ]);

        // Sabotage: occupied racks exceeds total capacity
        DB::table('tlx_data_centers')->where('id', $dc->id)->update(['occupied_racks' => 55]);

        $exitCode = Artisan::call('tlx:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_sabotage_datacenter_occupied_racks_mismatch_with_contracts_sum_is_caught_by_audit(): void
    {
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-SAB-MIS-01',
            'name' => 'Mismatch DC',
            'city' => 'Jakarta',
            'total_facility_power_kw' => 1000.0,
            'it_load_power_kw' => 800.0,
            'total_racks_capacity' => 50,
            'occupied_racks' => 20,
            'measured_pue' => 1.25,
        ]);

        // Active contract allocates 25 racks
        ColocationContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'COLO-SAB-MIS-01',
            'tenant_party_id' => 'PARTY-MIS-01',
            'dc_id' => $dc->id,
            'rack_units_allocated' => 25,
            'power_commit_kw' => 50,
            'power_rate_per_kwh_minor' => 1500,
            'monthly_rack_fee_minor' => 75_000_000,
            'excess_power_rate_per_kwh_minor' => 1_500_000,
            'port_status' => 'UP',
            'is_overdue' => false,
        ]);

        // DC occupied racks is 20, but contracts sum is 25 -> discrepancy!
        $exitCode = Artisan::call('tlx:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_sabotage_fabricated_pue_differing_from_power_readings_is_caught_by_audit(): void
    {
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-SAB-PUE-01',
            'name' => 'Fabricated PUE DC',
            'city' => 'Jakarta',
            'total_facility_power_kw' => 1000.0,
            'it_load_power_kw' => 500.0,
            'total_racks_capacity' => 50,
            'occupied_racks' => 0,
            'measured_pue' => 1.20, // Fabricated low PUE
        ]);

        // Actual readings indicate PUE = 200000 / 100000 = 2.00
        DcPowerReading::create([
            'id' => (string) Str::uuid(),
            'dc_id' => $dc->id,
            'reading_at' => CarbonImmutable::parse('2026-10-01 12:00:00'),
            'total_facility_energy_kwh' => 200000,
            'it_equipment_energy_kwh' => 100000,
            'peak_power_kw' => 50,
        ]);

        $exitCode = Artisan::call('tlx:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_sabotage_topological_suppression_without_parent_is_caught_by_audit(): void
    {
        $rootElem = NetworkElement::create([
            'id' => (string) Str::uuid(),
            'element_code' => 'ELEM-SAB-ROOT',
            'element_type' => 'CORE_ROUTER',
            'parent_element_id' => null,
            'status' => 'OPERATIONAL',
        ]);

        // Sabotage: root element marked suppressed without ancestor
        NocAlarm::create([
            'id' => (string) Str::uuid(),
            'alarm_code' => 'ALM-SAB-01',
            'element_id' => $rootElem->id,
            'alarm_type' => 'LINK_DOWN',
            'severity' => 'MINOR',
            'is_suppressed' => true,
            'suppressed_by_element_id' => null,
            'raised_at' => CarbonImmutable::parse('2026-10-01 12:00:00'),
        ]);

        $exitCode = Artisan::call('tlx:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_sabotage_billed_device_count_differing_from_active_registry_is_caught_by_audit(): void
    {
        // 1 active device in registry
        $this->registryService->registerDevice([
            'device_code' => 'DEV-SAB-REG-01',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-SAB-REG',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-SAB-REG-01',
        ]);

        // Invoice claims 10 active devices
        IotConnectivityInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV-SAB-REG-001',
            'owner_entity_id' => 'ENT-SAB-REG',
            'billing_period' => '2026-10',
            'active_device_count' => 10,
            'total_device_fee_minor' => 300000,
            'total_quota_mb' => 10000,
            'total_consumed_mb' => 5000,
            'excess_mb' => 0,
            'excess_charge_minor' => 0,
            'total_charge_minor' => 300000,
            'status' => 'ISSUED',
        ]);

        $exitCode = Artisan::call('tlx:audit');
        expect($exitCode)->toBe(1);
    }

    public function test_sabotage_cdr_rating_with_floor_rounding_violates_ceil_invariant(): void
    {
        // Ceiling invariant: 1025 bytes MUST round up to 2048 bytes
        $result = UsageRater::rate([1025], 0, 0, 0);
        expect($result['total_rated_bytes'])->toBe(2048);

        // Floor sabotage check: 1025 must NOT be floored to 1024
        expect($result['total_rated_bytes'])->not->toBe(1024);
    }

    public function test_sabotage_pro_rata_billing_with_zero_active_days_yields_zero_fee(): void
    {
        $device = $this->registryService->registerDevice([
            'device_code' => 'DEV-SAB-PRORATA',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-SAB-PRORATA',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-SAB-PRORATA',
        ]);

        $invoice = $this->connectivityBillingService->billEntityConnectivity([
            'owner_entity_id' => 'ENT-SAB-PRORATA',
            'billing_period' => '2026-10',
            'active_days_override' => 0,
            'idempotency_key' => 'BILL:TLX:SAB:PRORATA',
        ]);

        expect($invoice->total_device_fee_minor)->toBe(0)
            ->and($invoice->total_charge_minor)->toBe(0);
    }
}
