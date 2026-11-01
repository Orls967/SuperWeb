<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Esg\Application\Services\EsgService;
use Modules\Hcm\Application\Services\HcmService;
use Modules\Mobility\Telematics\Application\Services\TelematicsIngestService;
use Modules\Mobility\Telematics\Domain\Models\TelematicsDevice;
use Tests\TestCase;

class SilentDefaultEliminationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hcm_register_employee_rejects_missing_salary_and_bank_details_with_validation_exception(): void
    {
        $service = app(HcmService::class);

        // Omitting basic_salary_idr, allowances_idr, bank_name, bank_account_number
        // Must NOT silently default to Rp5jt / Mandiri / 1230004567890 (K-30, X25)
        try {
            $service->registerEmployee([
                'name' => 'John Silent Defaults Tester',
            ]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('basic_salary_idr', $errors);
            $this->assertArrayHasKey('allowances_idr', $errors);
            $this->assertArrayHasKey('bank_name', $errors);
            $this->assertArrayHasKey('bank_account_number', $errors);
        }
    }

    public function test_telematics_ingest_tick_rejects_missing_sensor_metrics_with_validation_exception(): void
    {
        $service = app(TelematicsIngestService::class);

        // Omitting oil_temp_c, battery_voltage, fuel_level_pct
        // Must NOT silently default to 90.0C / 12.6V / 100% (K-30, X25)
        try {
            $service->ingestTick([
                'device_id' => 'DEV-SENSOR-TEST',
                'sequence' => 1,
                'recorded_at' => '2026-10-07 10:00:00',
            ]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('oil_temp_c', $errors);
            $this->assertArrayHasKey('battery_voltage', $errors);
            $this->assertArrayHasKey('fuel_level_pct', $errors);
        }
    }

    public function test_esg_record_emission_rejects_unknown_activity_without_silent_default_factor(): void
    {
        $service = app(EsgService::class);

        // Unknown activity type must NOT silently use emission factor 1.0 (K-30, X25)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Jenis aktivitas emisi tidak dikenal: 'unknown_industrial_flaring'");

        $service->recordEmission([
            'entity_id' => 'PLANT-02',
            'scope' => 'scope_1',
            'activity_type' => 'unknown_industrial_flaring',
            'activity_data_amount' => 500.0,
            'activity_uom' => 'm3',
        ]);
    }

    public function test_explicit_valid_payloads_create_records_cleanly_without_hidden_defaults(): void
    {
        $hcm = app(HcmService::class);
        $employee = $hcm->registerEmployee([
            'employee_number' => 'EMP-EXP-001',
            'name' => 'Bambang Valid',
            'basic_salary_idr' => 8500000,
            'allowances_idr' => 1750000,
            'bank_name' => 'Bank BCA',
            'bank_account_number' => '8881234567',
        ]);
        $this->assertSame(8500000, $employee->basic_salary_idr);
        $this->assertSame('Bank BCA', $employee->bank_name);

        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'plate_number' => 'B 9999 EXP',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'vin' => 'MHF99999999999999',
        ]);
        TelematicsDevice::create([
            'device_id' => 'DEV-EXP-001',
            'vehicle_id' => $vehicle->id,
            'serial_number' => 'SN-EXP-001',
            'protocol' => 'OBD2_CAN',
            'status' => 'active',
        ]);

        $tlm = app(TelematicsIngestService::class);
        $tick = $tlm->ingestTick([
            'device_id' => 'DEV-EXP-001',
            'sequence' => 10,
            'recorded_at' => '2026-10-07 10:00:00',
            'oil_temp_c' => 84.5,
            'battery_voltage' => 12.4,
            'fuel_level_pct' => 65.0,
        ]);
        $this->assertSame(84.5, (float) $tick->oil_temp_c);
        $this->assertSame(12.4, (float) $tick->battery_voltage);
        $this->assertSame(65.0, (float) $tick->fuel_level_pct);
    }
}
