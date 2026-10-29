<?php

declare(strict_types=1);

namespace Tests\Perilaku\R2;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Esg\Application\Services\EsgService;
use Modules\Hcm\Application\Services\HcmService;
use Modules\Telematics\Application\Services\TelematicsIngestService;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('perilaku')]
class R2_9Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_klausul_1_hcm_register_employee_menolak_gaji_dan_rekening_hilang_dengan_validasi_422(): void
    {
        $service = app(HcmService::class);

        // Klausul 1 Terbukti: Gaji dan data perbankan yang hilang wajib melempar ValidationException
        try {
            $service->registerEmployee([
                'name' => 'Karyawan Tanpa Gaji',
            ]);
            $this->fail('Harus melempar ValidationException.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('basic_salary_idr', $errors, 'Klausul 1: basic_salary_idr wajib divalidasi');
            $this->assertArrayHasKey('allowances_idr', $errors, 'Klausul 1: allowances_idr wajib divalidasi');
            $this->assertArrayHasKey('bank_name', $errors, 'Klausul 1: bank_name wajib divalidasi');
            $this->assertArrayHasKey('bank_account_number', $errors, 'Klausul 1: bank_account_number wajib divalidasi');
        }
    }

    public function test_klausul_2_telematics_menolak_metrik_sensor_hilang_dengan_validasi(): void
    {
        $service = app(TelematicsIngestService::class);

        // Klausul 2 Terbukti: Suhu oli, tegangan, BBM yang hilang tidak boleh diisi default diam-diam
        try {
            $service->ingestTick([
                'device_id' => 'DEV-SENSOR-TEST',
                'sequence' => 1,
                'recorded_at' => '2026-10-07 10:00:00',
            ]);
            $this->fail('Harus melempar ValidationException.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('oil_temp_c', $errors, 'Klausul 2: oil_temp_c wajib divalidasi');
            $this->assertArrayHasKey('battery_voltage', $errors, 'Klausul 2: battery_voltage wajib divalidasi');
            $this->assertArrayHasKey('fuel_level_pct', $errors, 'Klausul 2: fuel_level_pct wajib divalidasi');
        }
    }

    public function test_klausul_3_esg_menolak_aktivitas_tidak_dikenal_tanpa_default_faktor_1(): void
    {
        $service = app(EsgService::class);

        // Klausul 3 Terbukti: Menolak aktivitas tak dikenal dengan exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Jenis aktivitas emisi tidak dikenal: 'unknown_industrial_flaring'");

        $service->recordEmission([
            'entity_id' => 'PLANT-01',
            'scope' => 'scope_1',
            'activity_type' => 'unknown_industrial_flaring',
            'activity_data_amount' => 500.0,
            'activity_uom' => 'kg',
        ]);
    }
}
