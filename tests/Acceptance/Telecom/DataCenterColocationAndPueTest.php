<?php

declare(strict_types=1);

namespace Tests\Acceptance\Telecom;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Tlx\Application\Services\ColocationBillingService;
use Modules\Tlx\Domain\Models\ColocationContract;
use Modules\Tlx\Domain\Models\ColocationInvoice;
use Modules\Tlx\Domain\Models\TelecomDataCenter;
use RuntimeException;
use Tests\TestCase;

class DataCenterColocationAndPueTest extends TestCase
{
    use RefreshDatabase;

    protected ColocationBillingService $dcService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->dcService = app(ColocationBillingService::class);
    }

    public function test_allocate_rack_checks_capacity_limit(): void
    {
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-JKT-01',
            'name' => 'Jakarta Cyber 1 Data Center',
            'city' => 'Jakarta Selatan',
            'total_facility_power_kw' => 2000.0,
            'it_load_power_kw' => 1500.0,
            'measured_pue' => 1.33,
            'total_racks_capacity' => 20,
            'occupied_racks' => 18,
        ]);

        // Attempting to allocate 5 racks when only 2 remain should fail
        $this->expectException(InvalidArgumentException::class);
        $this->dcService->allocateRack([
            'dc_id' => $dc->id,
            'tenant_party_id' => 'PARTY-CORP-A',
            'rack_units_allocated' => 5,
            'power_commit_kw' => 10,
            'monthly_rack_fee_minor' => 25000000,
            'power_rate_per_kwh_minor' => 1800,
            'excess_power_rate_per_kwh_minor' => 2500,
            'idempotency_key' => 'TLX-COLO:ALLOC-01',
        ]);
    }

    public function test_power_readings_are_append_only_and_immutable(): void
    {
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-SBY-01',
            'name' => 'Surabaya Edge DC',
            'city' => 'Surabaya',
            'total_facility_power_kw' => 1000.0,
            'it_load_power_kw' => 800.0,
            'measured_pue' => 1.25,
            'total_racks_capacity' => 30,
            'occupied_racks' => 10,
        ]);

        $reading = $this->dcService->recordPowerReading([
            'dc_id' => $dc->id,
            'reading_at' => '2026-10-01 12:00:00',
            'total_facility_energy_kwh' => 2500,
            'it_equipment_energy_kwh' => 2000,
            'peak_power_kw' => 120,
        ]);

        expect($reading->total_facility_energy_kwh)->toBe(2500)
            ->and($reading->it_equipment_energy_kwh)->toBe(2000);

        // Update must be blocked
        $this->expectException(RuntimeException::class);
        $reading->update(['total_facility_energy_kwh' => 3000]);
    }

    public function test_power_reading_deletion_is_strictly_forbidden(): void
    {
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-BDG-01',
            'name' => 'Bandung Cloud DC',
            'city' => 'Bandung',
            'total_facility_power_kw' => 800.0,
            'it_load_power_kw' => 600.0,
            'measured_pue' => 1.33,
            'total_racks_capacity' => 20,
            'occupied_racks' => 5,
        ]);

        $reading = $this->dcService->recordPowerReading([
            'dc_id' => $dc->id,
            'reading_at' => '2026-10-01 13:00:00',
            'total_facility_energy_kwh' => 1500,
            'it_equipment_energy_kwh' => 1200,
            'peak_power_kw' => 80,
        ]);

        $this->expectException(RuntimeException::class);
        $reading->delete();
    }

    public function test_pue_is_calculated_dynamically_from_facts(): void
    {
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-MED-01',
            'name' => 'Medan Tier III DC',
            'city' => 'Medan',
            'total_facility_power_kw' => 1200.0,
            'it_load_power_kw' => 1000.0,
            'measured_pue' => 1.20,
            'total_racks_capacity' => 40,
            'occupied_racks' => 15,
        ]);

        // Power readings: Facility 3000 kWh, IT 2400 kWh -> PUE = 3000 / 2400 = 1.25
        $this->dcService->recordPowerReading([
            'dc_id' => $dc->id,
            'reading_at' => '2026-10-02 10:00:00',
            'total_facility_energy_kwh' => 3000,
            'it_equipment_energy_kwh' => 2400,
            'peak_power_kw' => 100,
        ]);

        $pue = $this->dcService->calculateDynamicPue($dc->id, '2026-10');
        expect($pue)->toBe(1.25);
    }

    public function test_pue_sla_breach_detection(): void
    {
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-BALI-01',
            'name' => 'Bali Hub DC',
            'city' => 'Denpasar',
            'total_facility_power_kw' => 500.0,
            'it_load_power_kw' => 400.0,
            'measured_pue' => 1.25,
            'total_racks_capacity' => 15,
            'occupied_racks' => 5,
        ]);

        // Facility 3600 kWh, IT 2000 kWh -> PUE = 1.80 (Breaches SLA 1.50)
        $this->dcService->recordPowerReading([
            'dc_id' => $dc->id,
            'reading_at' => '2026-10-02 11:00:00',
            'total_facility_energy_kwh' => 3600,
            'it_equipment_energy_kwh' => 2000,
            'peak_power_kw' => 90,
        ]);

        $slaCheck = $this->dcService->checkPueSla($dc->id, 1.50);
        expect($slaCheck['is_breached'])->toBeTrue()
            ->and($slaCheck['measured_pue'])->toBe(1.80)
            ->and($slaCheck['threshold'])->toBe(1.50);
    }

    public function test_colocation_billing_includes_rack_and_excess_power_charge(): void
    {
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-BILL-01',
            'name' => 'Data Center Billing Test',
            'city' => 'Jakarta',
            'total_facility_power_kw' => 3000.0,
            'it_load_power_kw' => 2500.0,
            'measured_pue' => 1.20,
            'total_racks_capacity' => 50,
            'occupied_racks' => 10,
        ]);

        $contract = $this->dcService->allocateRack([
            'dc_id' => $dc->id,
            'tenant_party_id' => 'PARTY-FINTECH-B',
            'rack_units_allocated' => 2,
            'power_commit_kw' => 10,
            'monthly_rack_fee_minor' => 10000000, // 10 jt
            'power_rate_per_kwh_minor' => 1500,
            'excess_power_rate_per_kwh_minor' => 1500000, // 1.5 jt per excess kW
            'idempotency_key' => 'TLX-COLO:ALLOC-FINTECH-B',
        ]);

        // Ingest reading with peak power 12 kW (excess 2 kW above commit 10 kW)
        $this->dcService->recordPowerReading([
            'dc_id' => $dc->id,
            'reading_at' => '2026-10-05 14:00:00',
            'total_facility_energy_kwh' => 10000,
            'it_equipment_energy_kwh' => 8000,
            'peak_power_kw' => 12,
        ]);

        $invoice = $this->dcService->billColocationContract([
            'contract_id' => $contract['id'],
            'billing_period' => '2026-10',
            'measured_peak_kw' => 12,
            'idempotency_key' => 'TLX-COLO:BILL-202610-B',
        ]);

        // Rack fee: 10,000,000. Excess power: 2 kW * 1,500,000 = 3,000,000. Total = 13,000,000.
        expect($invoice->rack_fee_minor)->toBe(10000000)
            ->and($invoice->excess_power_charge_minor)->toBe(3000000)
            ->and($invoice->total_minor)->toBe(13000000);
    }

    public function test_colocation_billing_is_idempotent(): void
    {
        $dc = TelecomDataCenter::create([
            'id' => (string) Str::uuid(),
            'dc_code' => 'DC-IDEMP-01',
            'name' => 'DC Idempotency Test',
            'city' => 'Jakarta',
            'total_facility_power_kw' => 1000.0,
            'it_load_power_kw' => 800.0,
            'measured_pue' => 1.25,
            'total_racks_capacity' => 20,
            'occupied_racks' => 2,
        ]);

        $contract = $this->dcService->allocateRack([
            'dc_id' => $dc->id,
            'tenant_party_id' => 'PARTY-IDEMP-01',
            'rack_units_allocated' => 1,
            'power_commit_kw' => 5,
            'monthly_rack_fee_minor' => 5000000,
            'power_rate_per_kwh_minor' => 1500,
            'excess_power_rate_per_kwh_minor' => 1000000,
            'idempotency_key' => 'TLX-COLO:ALLOC-IDEMP',
        ]);

        $params = [
            'contract_id' => $contract['id'],
            'billing_period' => '2026-10',
            'measured_peak_kw' => 5,
            'idempotency_key' => 'TLX-COLO:BILL-202610-IDEMP',
        ];

        $inv1 = $this->dcService->billColocationContract($params);
        $inv2 = $this->dcService->billColocationContract($params);

        expect($inv1->id)->toBe($inv2->id)
            ->and(ColocationInvoice::where('contract_id', $contract['id'])->where('billing_period', '2026-10')->count())->toBe(1);
    }

    public function test_tenant_anti_idor_protects_colocation_records(): void
    {
        $contract = ColocationContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'COLO-T-01',
            'tenant_party_id' => 'PARTY-TENANT-ALPHA',
            'dc_id' => (string) Str::uuid(),
            'rack_units_allocated' => 2,
            'monthly_rack_fee_minor' => 10000000,
            'power_rate_per_kwh_minor' => 1500,
            'monthly_kwh_consumed' => 0.0,
            'port_status' => 'UP',
        ]);

        $userBeta = User::factory()->create([
            'role' => 'datacenter_ops',
        ]);
        $userBeta->setAttribute('entity_id', 'PARTY-TENANT-BETA');

        $canAccess = $this->dcService->canAccessContract($userBeta, $contract->id);
        expect($canAccess)->toBeFalse();

        $userAlpha = User::factory()->create([
            'role' => 'datacenter_ops',
        ]);
        $userAlpha->setAttribute('entity_id', 'PARTY-TENANT-ALPHA');

        $canAccessAlpha = $this->dcService->canAccessContract($userAlpha, $contract->id);
        expect($canAccessAlpha)->toBeTrue();
    }

    public function test_colocation_contract_suspension_on_overdue_payment(): void
    {
        $contract = ColocationContract::create([
            'id' => (string) Str::uuid(),
            'contract_number' => 'COLO-T-OVERDUE',
            'tenant_party_id' => 'PARTY-OVERDUE',
            'dc_id' => (string) Str::uuid(),
            'rack_units_allocated' => 1,
            'monthly_rack_fee_minor' => 5000000,
            'power_rate_per_kwh_minor' => 1500,
            'monthly_kwh_consumed' => 0.0,
            'port_status' => 'UP',
            'is_overdue' => false,
        ]);

        $suspended = $this->dcService->suspendOverdueContract($contract->id, 'Overdue > 30 days', 'TLX-SUS-COLO:01');
        expect($suspended->port_status)->toBe('SUSPENDED')
            ->and($suspended->is_overdue)->toBeTrue();
    }

    public function test_bill_colocation_command_runs_successfully(): void
    {
        $exitCode = Artisan::call('tlx:bill-colocation', [
            '--period' => '2026-10',
        ]);

        expect($exitCode)->toBe(0);
    }
}
