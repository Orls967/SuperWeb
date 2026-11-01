<?php

declare(strict_types=1);

namespace Tests\Acceptance\Telecom;

use App\Quality\Audit\Fixtures\TlxAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Tlx\Contracts\IotDeviceRegistry;
use Modules\Tlx\Domain\Models\ColocationContract;
use Modules\Tlx\Domain\Models\DcPowerReading;
use Modules\Tlx\Domain\Models\IotConnectivityInvoice;
use Modules\Tlx\Domain\Models\NetworkElement;
use Modules\Tlx\Domain\Models\NocAlarm;
use Modules\Tlx\Domain\Models\TelecomCdr;
use Modules\Tlx\Domain\Models\TelecomDataCenter;
use Tests\TestCase;

class TelecomAuditAndTwoSourceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected IotDeviceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->registry = app(IotDeviceRegistry::class);
    }

    public function test_audit_passes_on_clean_state_with_zero_discrepancies(): void
    {
        $exitCode = Artisan::call('tlx:audit');
        $output = Artisan::output();

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('Tlx audit PASSED with 0 discrepancy.');
    }

    public function test_audit_fails_on_corruption_fixture(): void
    {
        $fixture = new TlxAuditCorruptionFixture;
        $expectedError = $fixture->corrupt();

        $exitCode = Artisan::call('tlx:audit');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain($expectedError);
    }

    public function test_audit_detects_discrepancy_between_active_devices_and_billed_devices(): void
    {
        // 1 device in registry
        $device = $this->registry->registerDevice([
            'device_code' => 'DEV-AUD-01',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-LOGISTICS-01',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-AUD-01',
        ]);

        // Invoice claims 5 active devices (mismatch with 1 in registry)
        IotConnectivityInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV-TLX-202610-MISMATCH',
            'owner_entity_id' => 'ENT-LOGISTICS-01',
            'billing_period' => '2026-10',
            'active_device_count' => 5,
            'total_device_fee_minor' => 150000,
            'total_quota_mb' => 25000,
            'total_consumed_mb' => 20000,
            'excess_mb' => 0,
            'excess_charge_minor' => 0,
            'total_charge_minor' => 150000,
            'status' => 'ISSUED',
        ]);

        $exitCode = Artisan::call('tlx:audit');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('active device count mismatch');
    }

    public function test_audit_detects_discrepancy_in_cdr_rating_totals(): void
    {
        $device = $this->registry->registerDevice([
            'device_code' => 'DEV-AUD-CDR',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-LOGISTICS-02',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 30000,
            'idempotency_key' => 'TLX-REG:DEV-AUD-CDR',
        ]);

        // CDR has 5000 bytes transferred but rated_bytes is illegally smaller than transferred
        TelecomCdr::insert([
            'id' => (string) Str::uuid(),
            'device_id' => $device['id'],
            'session_id' => 'SESS-ILLEGAL-RATING',
            'started_at' => '2026-10-01 10:00:00',
            'ended_at' => '2026-10-01 10:05:00',
            'bytes_transferred' => 5000,
            'rated_bytes' => 4000, // ILLEGAL: rated_bytes < bytes_transferred
            'duration_seconds' => 300,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('tlx:audit');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('CDR rating discrepancy');
    }

    public function test_audit_detects_dc_rack_allocation_discrepancy(): void
    {
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-AUD-RACK',
            'name' => 'Rack Audit DC',
            'city' => 'Jakarta',
            'total_facility_power_kw' => 1000.0,
            'it_load_power_kw' => 800.0,
            'measured_pue' => 1.25,
            'total_racks_capacity' => 20,
            'occupied_racks' => 10,
        ]);

        // Sum of allocated racks in contracts is 4, but occupied_racks in DC says 10
        ColocationContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'COLO-AUD-01',
            'tenant_party_id' => 'PARTY-AUD-01',
            'dc_id' => $dc->id,
            'rack_units_allocated' => 4,
            'monthly_rack_fee_minor' => 20000000,
            'power_rate_per_kwh_minor' => 1500,
            'monthly_kwh_consumed' => 0.0,
            'port_status' => 'UP',
        ]);

        $exitCode = Artisan::call('tlx:audit');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('DC occupied rack discrepancy');
    }

    public function test_audit_detects_pue_discrepancy_when_reported_pue_differs_from_readings(): void
    {
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-AUD-PUE',
            'name' => 'PUE Audit DC',
            'city' => 'Surabaya',
            'total_facility_power_kw' => 1000.0,
            'it_load_power_kw' => 800.0,
            'measured_pue' => 1.10, // Claims 1.10
            'total_racks_capacity' => 20,
            'occupied_racks' => 0,
        ]);

        // Actual readings: 3000 / 2000 = 1.50 != 1.10
        DcPowerReading::insert([
            'id' => (string) Str::uuid(),
            'dc_id' => $dc->id,
            'reading_at' => '2026-10-01 12:00:00',
            'total_facility_energy_kwh' => 3000,
            'it_equipment_energy_kwh' => 2000,
            'peak_power_kw' => 80,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('tlx:audit');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('PUE calculation discrepancy');
    }

    public function test_audit_detects_invalid_topology_suppression_without_down_ancestor(): void
    {
        $node = NetworkElement::create([
            'id' => (string) Str::uuid(),
            'element_code' => 'NODE-AUD-01',
            'element_type' => 'SITE',
            'parent_element_id' => null,
            'status' => 'OPERATIONAL', // Node is operational, no parent
        ]);

        // Alarm marked suppressed but has no down ancestor
        NocAlarm::insert([
            'id' => (string) Str::uuid(),
            'alarm_code' => 'ALM-FALSE-SUPP',
            'element_id' => $node->id,
            'alarm_type' => 'LINK_DOWN',
            'severity' => 'MAJOR',
            'is_suppressed' => true,
            'suppressed_by_element_id' => null,
            'raised_at' => '2026-10-01 12:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('tlx:audit');
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('Invalid topology alarm suppression');
    }

    public function test_audit_command_contract_compliance(): void
    {
        $exitCode = Artisan::call('tlx:audit');
        expect(in_array($exitCode, [0, 1], true))->toBeTrue();
    }
}
